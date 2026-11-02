<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * ARCA voucher codes. As a monotributista the issuer only uses class C.
 */
enum InvoiceType: int implements HasLabel
{
    case FacturaC = 11;
    case NotaCreditoC = 13;

    public function getLabel(): string
    {
        return match ($this) {
            self::FacturaC => 'Factura C',
            self::NotaCreditoC => 'Nota de crédito C',
        };
    }

    public function letter(): string
    {
        return 'C';
    }

    /**
     * Title printed on the document, without the letter (it goes in its own box).
     */
    public function title(): string
    {
        return match ($this) {
            self::FacturaC => 'Factura',
            self::NotaCreditoC => 'Nota de crédito',
        };
    }
}
