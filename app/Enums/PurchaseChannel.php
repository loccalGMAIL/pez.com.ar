<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PurchaseChannel: string implements HasLabel
{
    case Facebook = 'facebook';
    case MercadoLibre = 'mercadolibre';
    case Other = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::MercadoLibre => 'MercadoLibre',
            self::Other => 'Otro',
        };
    }
}
