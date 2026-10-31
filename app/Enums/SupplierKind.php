<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SupplierKind: string implements HasLabel
{
    case Business = 'proveedor';
    case Individual = 'particular';

    public function getLabel(): string
    {
        return match ($this) {
            self::Business => 'Proveedor',
            self::Individual => 'Particular',
        };
    }
}
