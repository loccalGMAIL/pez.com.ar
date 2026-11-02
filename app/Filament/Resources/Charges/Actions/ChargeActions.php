<?php

namespace App\Filament\Resources\Charges\Actions;

use App\Enums\ChargeStatus;
use App\Filament\Resources\Invoices\Actions\InvoiceActions;
use App\Filament\Resources\Invoices\Schemas\InvoicePreviewForm;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Models\Charge;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Invoicing\InvoiceDraft;
use App\Support\Invoicing\InvoiceIssuer;
use App\Support\Invoicing\InvoicingException;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ChargeActions
{
    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('Confirmar')
            ->icon(Heroicon::OutlinedCheck)
            ->color('primary')
            ->visible(fn (Charge $record) => $record->status === ChargeStatus::Draft)
            ->modalHeading('Confirmar cargo')
            ->modalDescription(fn (Charge $record) => $record->notes)
            ->fillForm(fn (Charge $record) => ['amount' => $record->amount])
            ->schema([
                TextInput::make('amount')
                    ->label('Monto a cobrar')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->required(),
            ])
            ->action(fn (Charge $record, array $data) => $record->confirm((float) $data['amount']))
            ->successNotificationTitle('Cargo confirmado');
    }

    public static function registerPayment(): Action
    {
        return Action::make('registerPayment')
            ->label('Registrar pago')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Charge $record) => $record->status->isOpen())
            ->fillForm(fn (Charge $record) => ['amount' => $record->outstanding(), 'paid_on' => today()])
            ->schema(PaymentForm::fields())
            ->action(fn (Charge $record, array $data) => PaymentForm::notifyRegistered(
                Payment::register([...$data, 'client_id' => $record->client_id], [$record->id]),
            ));
    }

    /**
     * Annuls the charge. When it was invoiced before payment, its invoice is
     * annulled with a credit note first; if ARCA does not authorize the note,
     * the charge stays as it was.
     */
    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Anular')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Charge $record) => $record->status !== ChargeStatus::Cancelled)
            ->requiresConfirmation()
            ->modalDescription(fn (Charge $record) => collect([
                'El cargo deja de deberse. Si tenía pagos imputados, pasan a saldo a favor del cliente.',
                ($invoices = $record->invoicesInForce())->isEmpty() ? null
                    : 'Está facturado con la '.$invoices->pluck('title')->join(', ').': primero se emite la nota de crédito.',
            ])->filter()->join(' '))
            ->action(function (Charge $record) {
                $invoices = $record->invoicesInForce();
                $fromPayments = $invoices->reject(fn (Invoice $invoice) => $invoice->charge_id === $record->id);

                if ($fromPayments->isNotEmpty()) {
                    Notification::make()
                        ->title('No se puede anular')
                        ->body("Está facturado en la {$fromPayments->first()->title}, junto con su pago. Anulá esa factura con nota de crédito desde su página.")
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $notes = [];

                foreach ($invoices as $invoice) {
                    try {
                        $note = app(InvoiceIssuer::class)->creditNote($invoice);
                    } catch (InvoicingException $exception) {
                        Notification::make()->title('No se puede anular')->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    if (! $note->isAuthorized()) {
                        InvoiceActions::notifyResult($note);

                        return;
                    }

                    $notes[] = $note->title;
                }

                $record->cancel();

                Notification::make()
                    ->title('Cargo anulado')
                    ->body($notes === [] ? null : 'Con la '.implode(', ', $notes).'.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Factura C for the charge before it is paid, for clients who need the invoice to pay.
     */
    public static function issueInvoice(): Action
    {
        return Action::make('issueInvoice')
            ->label(fn (Charge $record) => $record->invoice && ! $record->invoice->isAuthorized() ? 'Reintentar factura' : 'Facturar cargo')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('primary')
            ->visible(fn (Charge $record) => Settings::invoicing()['enabled']
                && ! in_array($record->status, [ChargeStatus::Draft, ChargeStatus::Cancelled], true)
                && ((bool) $record->invoice?->isAwaitingArca() || $record->pendingToInvoice(self::retrying($record)) > 0))
            ->modalHeading(fn (Charge $record) => "Factura C de {$record->concept}")
            ->modalDescription(fn (Charge $record) => InvoicePreviewForm::description(
                $record->client,
                $record->pendingToInvoice(self::retrying($record)),
                $record->invoice,
                'Vencimiento del pago: '.$record->due_on->format('d/m/Y').'.',
            ))
            ->modalSubmitActionLabel('Pedir CAE')
            ->fillForm(fn (Charge $record) => InvoicePreviewForm::fill(InvoiceDraft::forCharge($record, self::retrying($record)), self::retrying($record)))
            ->schema(fn (Charge $record) => $record->invoice?->isAwaitingArca() ? [] : InvoicePreviewForm::fields())
            ->action(function (Charge $record, array $data) {
                try {
                    $invoice = app(InvoiceIssuer::class)->issueForCharge($record, $data);
                } catch (InvoicingException $exception) {
                    Notification::make()->title('No se puede facturar')->body($exception->getMessage())->danger()->send();

                    return;
                }

                // The page keeps the charge loaded: refresh it so its actions match the new invoice.
                $record->setRelation('invoice', $invoice);

                InvoiceActions::notifyResult($invoice);
            });
    }

    public static function viewInvoice(): Action
    {
        return InvoiceActions::view('viewInvoice', fn (Charge $record) => $record->invoicesInForce()->last() ?? $record->invoice);
    }

    /**
     * The charge's invoice that failed and is being retried, if any.
     */
    private static function retrying(Charge $record): ?Invoice
    {
        return $record->invoice?->isAuthorized() === false ? $record->invoice : null;
    }
}
