<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ServiceUnit: string implements HasLabel
{
    case Hour = 'hora';
    case Unit = 'unidad';
    case Month = 'mes';
    case Service = 'servicio';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hour => 'Hora',
            self::Unit => 'Unidad',
            self::Month => 'Mes',
            self::Service => 'Servicio',
        };
    }
}
