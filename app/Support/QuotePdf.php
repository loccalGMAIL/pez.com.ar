<?php

namespace App\Support;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class QuotePdf
{
    public const DISK = 'local';

    public static function filename(Quote $quote): string
    {
        return "Presupuesto-{$quote->code}.pdf";
    }

    public static function render(Quote $quote): string
    {
        $quote->loadMissing(['client', 'items']);

        return Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'company' => Settings::company(),
        ])->setPaper('a4')->output();
    }

    /**
     * Archives the PDF exactly as it was sent to the client.
     */
    public static function store(Quote $quote): string
    {
        $path = "quotes/{$quote->code}.pdf";

        Storage::disk(self::DISK)->put($path, self::render($quote));

        return $path;
    }

    public static function money(float $amount): string
    {
        return '$ '.number_format($amount, 2, ',', '.');
    }

    public static function quantity(float|string $quantity): string
    {
        $quantity = (float) $quantity;

        return floor($quantity) == $quantity
            ? number_format($quantity, 0, ',', '.')
            : number_format($quantity, 2, ',', '.');
    }
}
