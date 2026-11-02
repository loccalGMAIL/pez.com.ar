<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Enums\InvoiceConcept;
use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\InvoicePdf;
use App\Support\Invoicing\InvoiceDraft;
use App\Support\Invoicing\InvoiceIssuer;
use App\Support\Invoicing\InvoicingException;
use App\Support\QuotePdf;
use App\Support\ReceiptPdf;
use App\Support\Settings;
use App\Support\WhatsApp;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentActions
{
    public static function downloadReceipt(): Action
    {
        return Action::make('downloadReceipt')
            ->label('Recibo')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (Payment $record): StreamedResponse => response()->streamDownload(
                fn () => print (ReceiptPdf::render($record)),
                ReceiptPdf::filename($record),
                ['Content-Type' => 'application/pdf'],
            ));
    }

    /**
     * Opens WhatsApp with the message and the receipt link written.
     */
    public static function shareReceiptOnWhatsApp(): Action
    {
        return Action::make('shareReceiptOnWhatsApp')
            ->label('Enviar recibo por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->url(fn (Payment $record) => WhatsApp::url(
                $record->client->whatsappPhone(),
                "Hola! Te enviamos el recibo {$record->code} de ".Settings::get('company.name')
                    .' por '.QuotePdf::money($record->amount).". ¡Gracias por tu pago!\n"
                    .$record->publicReceiptUrl(),
            ), shouldOpenInNewTab: true);
    }

    /**
     * Factura C for the payment, after a preview of what goes in it. Also retries
     * one that ARCA rejected or did not answer.
     */
    public static function issueInvoice(): Action
    {
        return Action::make('issueInvoice')
            ->label(fn (Payment $record) => $record->invoice ? 'Reintentar factura' : 'Facturar en ARCA')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('primary')
            ->visible(fn (Payment $record) => Settings::invoicing()['enabled'] && ! $record->invoice?->isAuthorized())
            ->modalHeading(fn (Payment $record) => "Factura C del recibo {$record->code}")
            ->modalDescription(fn (Payment $record) => self::invoicePreviewDescription($record))
            ->modalSubmitActionLabel('Pedir CAE')
            ->fillForm(fn (Payment $record) => self::invoiceFormData($record))
            ->schema(fn (Payment $record) => self::isAwaitingArca($record) ? [] : [
                ToggleButtons::make('concept')
                    ->label('Concepto')
                    ->options(InvoiceConcept::class)
                    ->inline()
                    ->required()
                    ->live(),
                DatePicker::make('service_from')
                    ->label('Período desde')
                    ->visible(fn (Get $get) => self::conceptOf($get('concept'))->hasServicePeriod())
                    ->required(),
                DatePicker::make('service_to')
                    ->label('Período hasta')
                    ->visible(fn (Get $get) => self::conceptOf($get('concept'))->hasServicePeriod())
                    ->afterOrEqual('service_from')
                    ->required(),
                Repeater::make('items')
                    ->label('Detalle')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(3)
                    ->schema([
                        TextInput::make('description')
                            ->label('Concepto')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('detail')
                            ->label('Aclaración')
                            ->maxLength(255),
                        TextInput::make('amount')
                            ->label('Importe')
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (Payment $record, array $data) {
                try {
                    $invoice = app(InvoiceIssuer::class)->issue($record, $data);
                } catch (InvoicingException $exception) {
                    Notification::make()->title('No se puede facturar')->body($exception->getMessage())->danger()->send();

                    return;
                }

                // The page keeps the payment loaded: refresh it so its actions match the new invoice.
                $record->setRelation('invoice', $invoice);

                self::notifyInvoiceResult($invoice);
            });
    }

    public static function downloadInvoice(): Action
    {
        return Action::make('downloadInvoice')
            ->label('Descargar factura')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (Payment $record) => (bool) $record->invoice?->isAuthorized())
            ->action(fn (Payment $record): StreamedResponse => response()->streamDownload(
                fn () => print (InvoicePdf::contents($record->invoice)),
                InvoicePdf::filename($record->invoice),
                ['Content-Type' => 'application/pdf'],
            ));
    }

    public static function sendInvoice(): Action
    {
        return Action::make('sendInvoice')
            ->label('Enviar factura por mail')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (Payment $record) => (bool) $record->invoice?->isAuthorized())
            ->modalHeading(fn (Payment $record) => "Enviar {$record->invoice->title}")
            ->modalSubmitActionLabel('Enviar')
            ->fillForm(fn (Payment $record) => [
                'to' => $record->client->email ?? $record->client->primaryContact?->email,
                'body' => "Hola,\n\nTe enviamos adjunta la {$record->invoice->title} por "
                    .QuotePdf::money($record->invoice->total).".\n\n¡Gracias por tu pago!",
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
            ->action(fn (Payment $record, array $data) => Mail::to($data['to'])->send(new InvoiceMail($record->invoice, $data['body'])))
            ->successNotificationTitle('Factura enviada');
    }

    public static function shareInvoiceOnWhatsApp(): Action
    {
        return Action::make('shareInvoiceOnWhatsApp')
            ->label('Enviar factura por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (Payment $record) => (bool) $record->invoice?->isAuthorized())
            ->url(fn (Payment $record) => $record->invoice?->isAuthorized() ? WhatsApp::url(
                $record->client->whatsappPhone(),
                "Hola! Te enviamos la {$record->invoice->title} de ".Settings::get('company.name')
                    .' por '.QuotePdf::money($record->invoice->total).".\n"
                    .$record->invoice->publicPdfUrl(),
            ) : null, shouldOpenInNewTab: true);
    }

    public static function notifyInvoiceResult(Invoice $invoice): void
    {
        $messages = implode("\n", $invoice->messages ?? []);

        match ($invoice->status) {
            InvoiceStatus::Authorized => Notification::make()
                ->title("{$invoice->title} autorizada")
                ->body("CAE {$invoice->cae}".($messages === '' ? '' : "\n{$messages}"))
                ->success()
                ->send(),
            InvoiceStatus::Rejected => Notification::make()
                ->title('ARCA rechazó la factura')
                ->body($messages)
                ->danger()
                ->persistent()
                ->send(),
            InvoiceStatus::Pending => Notification::make()
                ->title('La factura quedó pendiente')
                ->body($messages)
                ->warning()
                ->persistent()
                ->send(),
        };
    }

    /**
     * Sent without an answer: it is looked up in ARCA exactly as it went, so nothing can be changed.
     */
    private static function isAwaitingArca(Payment $record): bool
    {
        return $record->invoice?->number !== null && $record->invoice->environment === config('arca.environment');
    }

    private static function invoicePreviewDescription(Payment $record): string
    {
        $client = $record->client;
        $document = match (true) {
            filled($client->cuit) => "CUIT {$client->cuit}",
            filled($client->dni) => "DNI {$client->dni}",
            default => 'consumidor final sin identificar',
        };

        return collect([
            "Para {$client->business_name} ({$document}) por ".QuotePdf::money($record->amount).'.',
            self::isAwaitingArca($record) ? 'ARCA no respondió el pedido anterior: primero se consulta si llegó a autorizarla.' : null,
            config('arca.environment') === 'produccion' ? null : 'Ambiente de prueba (homologación): la factura no tiene validez fiscal.',
        ])->filter()->join(' ');
    }

    /**
     * @return array<string, mixed>
     */
    private static function invoiceFormData(Payment $record): array
    {
        $draft = InvoiceDraft::for($record);
        $invoice = $record->invoice;
        $invoice?->loadMissing('items');
        $previous = $invoice?->items->values();

        return [
            'concept' => $invoice->concept ?? $draft['concept'],
            'service_from' => ($invoice->service_from ?? $draft['service_from'])->toDateString(),
            'service_to' => ($invoice->service_to ?? $draft['service_to'])->toDateString(),
            'items' => collect($draft['items'])
                ->map(fn (array $item, int $index) => [
                    'description' => $previous?->get($index)->description ?? $item['description'],
                    'detail' => $previous !== null && $previous->has($index) ? $previous->get($index)->detail : $item['detail'],
                    'amount' => number_format($item['amount'], 2, ',', '.'),
                ])
                ->all(),
        ];
    }

    private static function conceptOf(mixed $value): InvoiceConcept
    {
        return $value instanceof InvoiceConcept ? $value : (InvoiceConcept::tryFrom((int) $value) ?? InvoiceConcept::Services);
    }
}
