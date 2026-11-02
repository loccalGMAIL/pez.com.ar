<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaxCondition: string implements HasLabel
{
    case ResponsableInscripto = 'responsable_inscripto';
    case Monotributista = 'monotributista';
    case Exento = 'exento';
    case ConsumidorFinal = 'consumidor_final';

    public function getLabel(): string
    {
        return match ($this) {
            self::ResponsableInscripto => 'Responsable inscripto',
            self::Monotributista => 'Monotributista',
            self::Exento => 'Exento',
            self::ConsumidorFinal => 'Consumidor final',
        };
    }

    /**
     * Code ARCA expects for the recipient's VAT condition (RG 5616).
     */
    public function arcaId(): int
    {
        return match ($this) {
            self::ResponsableInscripto => 1,
            self::Exento => 4,
            self::ConsumidorFinal => 5,
            self::Monotributista => 6,
        };
    }
}
