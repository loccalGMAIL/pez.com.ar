<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Models\Payment;
use App\Support\QuotePdf;
use App\Support\ReceiptPdf;
use App\Support\Settings;
use App\Support\WhatsApp;
use Filament\Actions\Action;
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
}
