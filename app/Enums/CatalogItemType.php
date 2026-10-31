<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CatalogItemType: string implements HasLabel
{
    case Service = 'servicio';
    case Product = 'producto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Service => 'Servicio',
            self::Product => 'Producto',
        };
    }
}
