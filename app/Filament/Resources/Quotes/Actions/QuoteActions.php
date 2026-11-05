<?php

namespace App\Filament\Resources\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Quote;
use App\Support\QuotePdf;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteActions
{
    /**
     * PDF with the current content (a preview while it is a draft).
     */
    public static function downloadPdf(): Action
    {
        return Action::make('downloadPdf')
            ->label('PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (Quote $record): StreamedResponse => response()->streamDownload(
                fn () => print (QuotePdf::render($record)),
                QuotePdf::filename($record),
                ['Content-Type' => 'application/pdf'],
            ));
    }

    /**
     * The PDF archived the last time the quote was sent.
     */
    public static function downloadSentPdf(): Action
    {
        return Action::make('downloadSentPdf')
            ->label('PDF enviado')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('gray')
            ->visible(fn (Quote $record) => $record->pdf_path && Storage::disk(QuotePdf::DISK)->exists($record->pdf_path))
            ->action(fn (Quote $record) => Storage::disk(QuotePdf::DISK)->download($record->pdf_path, QuotePdf::filename($record)));
    }

    public static function shareOnWhatsApp(): Action
    {
        return Action::make('shareOnWhatsApp')
            ->label('Compartir por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (Quote $record) => $record->isEditable())
            ->url(fn (Quote $record) => route('filament.admin.quotes.whatsapp', $record), shouldOpenInNewTab: true);
    }

    public static function send(): Action
    {
        return Action::make('send')
            ->label('Enviar por mail')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (Quote $record) => $record->isEditable())
            ->modalHeading(fn (Quote $record) => "Enviar presupuesto {$record->code}")
            ->modalSubmitActionLabel('Enviar')
            ->fillForm(fn (Quote $record) => [
                'to' => $record->client->email ?? $record->client->primaryContact?->email,
                'body' => "Hola,\n\nTe enviamos adjunto el presupuesto {$record->code}, válido hasta el {$record->valid_until->format('d/m/Y')}.\n\nQuedamos a disposición por cualquier consulta.",
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
            ->action(function (Quote $record, array $data) {
                $record->sendTo($data['to'], $data['body']);
            })
            ->successNotificationTitle('Presupuesto enviado');
    }

    /**
     * For quotes delivered by other means (in person, another chat): archives the PDF without mailing it.
     */
    public static function markAsSent(): Action
    {
        return Action::make('markAsSent')
            ->label('Marcar como enviado')
            ->icon(Heroicon::OutlinedCheck)
            ->color('info')
            ->visible(fn (Quote $record) => $record->status === QuoteStatus::Draft)
            ->requiresConfirmation()
            ->modalDescription('Se archiva el PDF tal como está ahora, sin enviar ningún mail.')
            ->action(fn (Quote $record) => $record->markAsSent())
            ->successNotificationTitle('Presupuesto marcado como enviado');
    }

    public static function accept(): Action
    {
        return Action::make('accept')
            ->label('Aceptado')
            ->icon(Heroicon::OutlinedHandThumbUp)
            ->color('success')
            ->visible(fn (Quote $record) => $record->status === QuoteStatus::Sent)
            ->requiresConfirmation()
            ->modalDescription('El presupuesto queda cerrado y ya no se puede editar.')
            ->action(fn (Quote $record) => $record->accept())
            ->successNotificationTitle('Presupuesto aceptado');
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Rechazado')
            ->icon(Heroicon::OutlinedHandThumbDown)
            ->color('danger')
            ->visible(fn (Quote $record) => $record->status === QuoteStatus::Sent)
            ->requiresConfirmation()
            ->modalDescription('El presupuesto queda cerrado y ya no se puede editar.')
            ->action(fn (Quote $record) => $record->reject())
            ->successNotificationTitle('Presupuesto rechazado');
    }

    public static function convertToWorkOrder(): Action
    {
        return Action::make('convertToWorkOrder')
            ->label(fn (Quote $record) => $record->workOrder()->exists() ? 'Ver orden de trabajo' : 'Convertir en orden de trabajo')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('primary')
            ->visible(fn (Quote $record) => $record->status === QuoteStatus::Accepted)
            ->action(function (Quote $record, Action $action) {
                $order = $record->convertToWorkOrder();

                $action->redirect(WorkOrderResource::getUrl('view', ['record' => $order]));
            });
    }

    /**
     * Charges the accepted quote in cash or in one of the installment plans it
     * offered; once charged, it opens the charge (or the client's account when
     * there are several installments).
     */
    public static function charge(): Action
    {
        return Action::make('charge')
            ->label(fn (Quote $record) => match ($record->charges()->count()) {
                0 => 'Generar cargo',
                1 => 'Ver cargo',
                default => 'Ver cargos',
            })
            ->icon(Heroicon::OutlinedReceiptPercent)
            ->color('gray')
            ->visible(fn (Quote $record) => $record->status === QuoteStatus::Accepted)
            ->modalHidden(fn (Quote $record) => $record->charges()->exists())
            ->modalHeading(fn (Quote $record) => "Generar cargo del presupuesto {$record->code}")
            ->modalDescription('Se carga en la cuenta corriente del cliente, en un cargo por cuota con vencimiento mensual.')
            ->modalSubmitActionLabel('Generar')
            ->schema(fn (Quote $record) => $record->charges()->exists() ? null : [
                Select::make('installments')
                    ->label('Plan')
                    ->options(self::chargePlans($record))
                    ->default(1)
                    ->selectablePlaceholder(false)
                    ->required(),
                DatePicker::make('first_due_on')
                    ->label('Vencimiento de la primera cuota')
                    ->default(today()->addDays((int) Settings::get('billing.due_days')))
                    ->required(),
            ])
            ->action(function (Quote $record, Action $action, array $data) {
                $charges = $record->charge(
                    (int) ($data['installments'] ?? 1),
                    isset($data['first_due_on']) ? Carbon::parse($data['first_due_on']) : null,
                );

                $action->redirect($charges->count() === 1
                    ? ChargeResource::getUrl('view', ['record' => $charges->first()])
                    : ClientResource::getUrl('view', ['record' => $record->client_id]));
            });
    }

    /**
     * Cash plus each installment plan the quote offered, keyed by installment count.
     *
     * @return array<int, string>
     */
    private static function chargePlans(Quote $record): array
    {
        return [1 => 'Contado — '.QuotePdf::money($record->total)]
            + $record->installments()
                ->mapWithKeys(fn (array $plan) => [
                    $plan['count'] => "{$plan['count']} cuotas de ".QuotePdf::money($plan['amount']).' (total '.QuotePdf::money($plan['total']).')',
                ])
                ->all();
    }

    public static function duplicate(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicar')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Se crea un borrador nuevo con el mismo cliente e ítems, con fecha de hoy.')
            ->action(function (Quote $record, Action $action) {
                $copy = $record->duplicate();

                $action->redirect(QuoteResource::getUrl('edit', ['record' => $copy]));
            });
    }
}
