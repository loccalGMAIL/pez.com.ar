<?php

namespace App\Support;

use NumberFormatter;

/**
 * Amounts written out as on Argentine receipts:
 * 1250.5 → "Pesos mil doscientos cincuenta con 50/100".
 */
class AmountInWords
{
    public static function pesos(float $amount): string
    {
        $whole = (int) floor(round($amount, 2));
        $cents = (int) round(($amount - $whole) * 100);

        $words = (new NumberFormatter('es', NumberFormatter::SPELLOUT))->format($whole);

        return 'Pesos '.$words.' con '.str_pad((string) $cents, 2, '0', STR_PAD_LEFT).'/100';
    }
}
