<?php

namespace App\Support;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptPdf
{
    public static function filename(Payment $payment): string
    {
        return "Recibo-{$payment->code}.pdf";
    }

    /**
     * Generated on demand: it always lists the charges the payment is applied to now.
     */
    public static function render(Payment $payment): string
    {
        $payment->loadMissing(['client', 'allocations.charge']);

        return Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'company' => Settings::company(),
        ])->setPaper('a4')->output();
    }
}
