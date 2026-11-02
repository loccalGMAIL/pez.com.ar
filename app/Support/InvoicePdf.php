<?php

namespace App\Support;

use App\Models\Invoice;
use App\Support\Arca\InvoiceQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoicePdf
{
    public const DISK = 'local';

    public static function filename(Invoice $invoice): string
    {
        return str_replace(' ', '-', Str::ascii($invoice->title)).'.pdf';
    }

    public static function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'payment', 'creditedInvoice']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => Settings::company(),
            'issuer' => Settings::invoicing(),
            'qr' => InvoiceQr::dataUri($invoice),
        ])->setPaper('a4')->output();
    }

    /**
     * Archives the PDF as authorized: later changes to the settings never alter it.
     */
    public static function store(Invoice $invoice): string
    {
        $path = "invoices/{$invoice->environment}/".self::filename($invoice);

        Storage::disk(self::DISK)->put($path, self::render($invoice));

        return $path;
    }

    /**
     * The archived PDF, or a fresh one if the file is missing.
     */
    public static function contents(Invoice $invoice): string
    {
        $disk = Storage::disk(self::DISK);

        return $invoice->pdf_path && $disk->exists($invoice->pdf_path)
            ? (string) $disk->get($invoice->pdf_path)
            : self::render($invoice);
    }
}
