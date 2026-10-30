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
}
