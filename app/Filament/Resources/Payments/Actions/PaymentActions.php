<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Filament\Resources\Invoices\Actions\InvoiceActions;
use App\Filament\Resources\Invoices\Schemas\InvoicePreviewForm;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Invoicing\InvoiceDraft;
use App\Support\Invoicing\InvoiceIssuer;
use App\Support\Invoicing\InvoicingException;
use App\Support\QuotePdf;
use App\Support\ReceiptPdf;
use App\Support\Settings;
use App\Support\WhatsApp;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
     * Factura C for what the payment settles that is not invoiced yet, after a
     * preview of what goes in it. Also retries one that ARCA rejected or did not answer.
     */
    public static function issueInvoice(): Action
    {
        return Action::make('issueInvoice')
            ->label(fn (Payment $record) => $record->invoice && ! $record->invoice->isAuthorized() ? 'Reintentar factura' : 'Facturar en ARCA')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('primary')
            ->visible(fn (Payment $record) => Settings::invoicing()['enabled'] && (
                (bool) $record->invoice?->isAwaitingArca()
                || (! $record->invoice?->isInForce() && InvoiceDraft::forPayment($record, self::retrying($record))['total'] > 0)
            ))
            ->modalHeading(fn (Payment $record) => "Factura C del recibo {$record->code}")
            ->modalDescription(fn (Payment $record) => InvoicePreviewForm::description(
                $record->client,
                InvoiceDraft::forPayment($record, self::retrying($record))['total'],
                $record->invoice,
                $record->coveringInvoices()->isEmpty() ? null : 'Lo que ya se facturó antes del pago no se vuelve a facturar.',
            ))
            ->modalSubmitActionLabel('Pedir CAE')
            ->fillForm(fn (Payment $record) => InvoicePreviewForm::fill(InvoiceDraft::forPayment($record, self::retrying($record)), self::retrying($record)))
            ->schema(fn (Payment $record) => $record->invoice?->isAwaitingArca() ? [] : InvoicePreviewForm::fields())
            ->action(function (Payment $record, array $data) {
                try {
                    $invoice = app(InvoiceIssuer::class)->issueForPayment($record, $data);
                } catch (InvoicingException $exception) {
                    Notification::make()->title('No se puede facturar')->body($exception->getMessage())->danger()->send();

                    return;
                }

                // The page keeps the payment loaded: refresh it so its actions match the new invoice.
                $record->setRelation('invoice', $invoice);

                InvoiceActions::notifyResult($invoice);
            });
    }

    public static function viewInvoice(): Action
    {
        return InvoiceActions::view('viewInvoice', fn (Payment $record) => $record->invoice);
    }

    /**
     * The payment's invoice that failed and is being retried, if any.
     */
    private static function retrying(Payment $record): ?Invoice
    {
        return $record->invoice?->isAuthorized() === false ? $record->invoice : null;
    }
}
