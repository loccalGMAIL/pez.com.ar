<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * ARCA document codes for the invoice recipient.
 */
enum RecipientDocumentType: int implements HasLabel
{
    case Cuit = 80;
    case Dni = 96;
    /** Consumidor final without identification (document number 0). */
    case Unidentified = 99;

    public function getLabel(): string
    {
        return match ($this) {
            self::Cuit => 'CUIT',
            self::Dni => 'DNI',
            self::Unidentified => 'Sin identificar',
        };
    }
}
