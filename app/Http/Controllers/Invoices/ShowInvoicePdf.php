<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\InvoicePdf;
use Illuminate\Http\Response;

/**
 * Public, signed link to an authorized invoice.
 */
class ShowInvoicePdf extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        abort_unless($invoice->isAuthorized(), 404);

        return response(InvoicePdf::contents($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.InvoicePdf::filename($invoice).'"',
        ]);
    }
}
