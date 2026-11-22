<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FollowUpStatus: string implements HasColor, HasLabel
{
    case Pending = 'pendiente';
    case Sent = 'enviado';
    case Skipped = 'omitido';
    case Cancelled = 'anulado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Sent => 'Enviado',
            self::Skipped => 'Omitido',
            self::Cancelled => 'Anulado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'success',
            self::Skipped => 'gray',
            self::Cancelled => 'danger',
        };
    }
}
