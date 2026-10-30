<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionType: string implements HasLabel
{
    case Fixed = 'fijo';
    case Variable = 'variable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Fijo',
            self::Variable => 'Variable',
        };
    }
}
