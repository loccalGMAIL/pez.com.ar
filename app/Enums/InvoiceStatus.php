<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    /** Created, or sent to ARCA without an answer: retrying checks ARCA first. */
    case Pending = 'pendiente';
    case Authorized = 'autorizada';
    case Rejected = 'rechazada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Authorized => 'Autorizada',
            self::Rejected => 'Rechazada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Authorized => 'success',
            self::Rejected => 'danger',
        };
    }
}
