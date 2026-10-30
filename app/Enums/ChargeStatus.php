<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChargeStatus: string implements HasColor, HasLabel
{
    case Draft = 'borrador';
    case Pending = 'pendiente';
    case Partial = 'parcial';
    case Paid = 'pagado';
    case Cancelled = 'anulado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Pending => 'Pendiente',
            self::Partial => 'Pago parcial',
            self::Paid => 'Pagado',
            self::Cancelled => 'Anulado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'warning',
            self::Partial => 'info',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Owed by the client: confirmed and not fully paid.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Partial], true);
    }

    /**
     * @return array<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Partial];
    }
}
