<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeviceType: string implements HasLabel
{
    case Desktop = 'pc';
    case Notebook = 'notebook';
    case Server = 'servidor';
    case Printer = 'impresora';
    case Network = 'red';
    case Phone = 'celular';
    case Other = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Desktop => 'PC',
            self::Notebook => 'Notebook',
            self::Server => 'Servidor',
            self::Printer => 'Impresora',
            self::Network => 'Equipo de red',
            self::Phone => 'Celular / tablet',
            self::Other => 'Otro',
        };
    }
}
