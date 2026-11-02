<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Support\InvoicePdf;
use App\Support\Invoicing\InvoiceIssuer;
use App\Support\Invoicing\InvoicingException;
use App\Support\QuotePdf;
use App\Support\Settings;
use App\Support\WhatsApp;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Actions on an invoice or credit note, from its own page.
 */
class InvoiceActions
{
    public static function download(): Action
    {
        return Action::make('download')
            ->label('PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (Invoice $record) => $record->isAuthorized())
            ->action(fn (Invoice $record): StreamedResponse => response()->streamDownload(
                fn () => print (InvoicePdf::contents($record)),
                InvoicePdf::filename($record),
                ['Content-Type' => 'application/pdf'],
            ));
    }

    public static function sendByMail(): Action
    {
        return Action::make('sendByMail')
            ->label('Enviar por mail')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (Invoice $record) => $record->isAuthorized())
            ->modalHeading(fn (Invoice $record) => "Enviar {$record->title}")
            ->modalSubmitActionLabel('Enviar')
            ->fillForm(fn (Invoice $record) => [
                'to' => $record->client->email ?? $record->client->primaryContact?->email,
                'body' => self::message($record),
            ])
            ->schema([
                TextInput::make('to')
                    ->label('Para')
                    ->email()
                    ->required(),
                Textarea::make('body')
                    ->label('Mensaje')
                    ->rows(6)
                    ->required(),
            ])
            ->action(fn (Invoice $record, array $data) => Mail::to($data['to'])->send(new InvoiceMail($record, $data['body'])))
            ->successNotificationTitle('Enviada por mail');
    }

    public static function shareOnWhatsApp(): Action
    {
        return Action::make('shareOnWhatsApp')
            ->label('Enviar por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (Invoice $record) => $record->isAuthorized())
            ->url(fn (Invoice $record) => WhatsApp::url(
                $record->client->whatsappPhone(),
                "Hola! Te enviamos la {$record->title} de ".Settings::get('company.name')
                    .' por '.QuotePdf::money($record->total).".\n"
                    .$record->publicPdfUrl(),
            ), shouldOpenInNewTab: true);
    }

    /**
     * Annuls the invoice with a Nota de crédito C for its total, or retries one that failed.
     */
    public static function creditNote(): Action
    {
        return Action::make('creditNote')
            ->label(fn (Invoice $record) => $record->creditNote && ! $record->creditNote->isAuthorized() ? 'Reintentar nota de crédito' : 'Anular con nota de crédito')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('danger')
            ->visible(fn (Invoice $record) => $record->isInForce())
            ->requiresConfirmation()
            ->modalHeading(fn (Invoice $record) => "Anular la {$record->title}")
            ->modalDescription(fn (Invoice $record) => 'Se emite una nota de crédito C por '.QuotePdf::money($record->total)
                .' asociada a la factura. Los cargos que facturaba vuelven a quedar sin facturar.')
            ->modalSubmitActionLabel('Emitir nota de crédito')
            ->action(function (Invoice $record) {
                try {
                    $note = app(InvoiceIssuer::class)->creditNote($record);
                } catch (InvoicingException $exception) {
                    Notification::make()->title('No se puede anular')->body($exception->getMessage())->danger()->send();

                    return;
                }

                $record->setRelation('creditNote', $note);
                self::notifyResult($note);
            });
    }

    /**
     * Link to the invoice's page, from the payment or the charge it bills.
     *
     * @param  Closure(mixed): ?Invoice  $invoice
     */
    public static function view(string $name, Closure $invoice): Action
    {
        return Action::make($name)
            ->label(fn ($record) => $invoice($record)->title ?? 'Factura')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->visible(fn ($record) => $invoice($record) !== null)
            ->url(fn ($record) => ($found = $invoice($record)) ? InvoiceResource::getUrl('view', ['record' => $found]) : null);
    }

    public static function notifyResult(Invoice $invoice): void
    {
        $messages = implode("\n", $invoice->messages ?? []);
        $document = $invoice->isCreditNote() ? 'la nota de crédito' : 'la factura';

        match ($invoice->status) {
            InvoiceStatus::Authorized => Notification::make()
                ->title("{$invoice->title} autorizada")
                ->body("CAE {$invoice->cae}".($messages === '' ? '' : "\n{$messages}"))
                ->success()
                ->send(),
            InvoiceStatus::Rejected => Notification::make()
                ->title("ARCA rechazó {$document}")
                ->body($messages)
                ->danger()
                ->persistent()
                ->send(),
            InvoiceStatus::Pending => Notification::make()
                ->title(ucfirst($document).' quedó pendiente')
                ->body($messages)
                ->warning()
                ->persistent()
                ->send(),
        };
    }

    private static function message(Invoice $invoice): string
    {
        if ($invoice->isCreditNote()) {
            return "Hola,\n\nTe enviamos adjunta la {$invoice->title}, que anula la {$invoice->creditedInvoice?->title}.\n\nSaludos.";
        }

        $due = $invoice->charge_id !== null && $invoice->payment_due_on !== null
            ? ', con vencimiento el '.$invoice->payment_due_on->format('d/m/Y')
            : '';

        return "Hola,\n\nTe enviamos adjunta la {$invoice->title} por ".QuotePdf::money($invoice->total)."{$due}.\n\n"
            .($invoice->charge_id !== null ? 'Quedamos a disposición por cualquier consulta.' : '¡Gracias por tu pago!');
    }
}
