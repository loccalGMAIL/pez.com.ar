<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductCondition: string implements HasColor, HasLabel
{
    case New = 'nuevo';
    case Resale = 'reventa';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Resale => 'Reventa (usado)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'success',
            self::Resale => 'warning',
        };
    }
}
