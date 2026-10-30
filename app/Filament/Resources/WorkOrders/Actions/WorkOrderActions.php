<?php

namespace App\Filament\Resources\WorkOrders\Actions;

use App\Models\Device;
use App\Models\WorkOrder;
use App\Support\Settings;
use App\Support\WhatsApp;
use App\Support\WorkOrderPdf;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkOrderActions
{
    public static function downloadPdf(): Action
    {
        return Action::make('downloadPdf')
            ->label('PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (WorkOrder $record): StreamedResponse => response()->streamDownload(
                fn () => print (WorkOrderPdf::render($record)),
                WorkOrderPdf::filename($record),
                ['Content-Type' => 'application/pdf'],
            ));
    }

    /**
     * Opens WhatsApp with the intake summary and the slip link written.
     */
    public static function shareOnWhatsApp(): Action
    {
        return Action::make('shareOnWhatsApp')
            ->label('Compartir por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->url(fn (WorkOrder $record) => WhatsApp::url($record->client->whatsappPhone(), self::summary($record)), shouldOpenInNewTab: true);
    }

    public static function summary(WorkOrder $order): string
    {
        $lines = [
            'Hola! Registramos el ingreso de tu equipo en '.Settings::get('company.name').'.',
            "Orden de trabajo {$order->code} del {$order->opened_on->format('d/m/Y')}.",
        ];

        $devices = $order->devices->map(fn (Device $device) => $device->label);

        if ($devices->isNotEmpty()) {
            $lines[] = 'Equipos: '.$devices->join(', ').'.';
        }

        $lines[] = "Pedido / Falla: {$order->title}.";

        $lines[] = 'Comprobante:';
        $lines[] = $order->publicPdfUrl();

        return implode("\n", $lines);
    }
}
