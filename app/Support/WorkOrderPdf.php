<?php

namespace App\Support;

use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;

class WorkOrderPdf
{
    public static function filename(WorkOrder $order): string
    {
        return "Orden-{$order->code}.pdf";
    }

    /**
     * Intake slip for the client: what was received and when it is due, without the internal resolution.
     */
    public static function render(WorkOrder $order): string
    {
        $order->loadMissing(['client', 'devices']);

        return Pdf::loadView('pdf.work-order', [
            'order' => $order,
            'company' => Settings::company(),
        ])->setPaper('a4')->output();
    }
}
