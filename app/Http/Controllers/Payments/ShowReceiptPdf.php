<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\ReceiptPdf;
use Illuminate\Http\Response;

/**
 * Public, signed link to a payment receipt.
 */
class ShowReceiptPdf extends Controller
{
    public function __invoke(Payment $payment): Response
    {
        return response(ReceiptPdf::render($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.ReceiptPdf::filename($payment).'"',
        ]);
    }
}
