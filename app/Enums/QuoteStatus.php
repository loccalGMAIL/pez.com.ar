<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuoteStatus: string implements HasColor, HasLabel
{
    case Draft = 'borrador';
    case Sent = 'enviado';
    case Accepted = 'aceptado';
    case Rejected = 'rechazado';
    case Expired = 'vencido';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Sent => 'Enviado',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
            self::Expired => 'Vencido',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Expired => 'warning',
        };
    }

    /**
     * Once the client answered (or the quote expired) its content is frozen;
     * changes go into a duplicate.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Sent], true);
    }

    public function isPending(): bool
    {
        return $this === self::Sent;
    }
}
