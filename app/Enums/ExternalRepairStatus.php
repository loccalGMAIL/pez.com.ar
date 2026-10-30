<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExternalRepairStatus: string implements HasColor, HasLabel
{
    case Sent = 'enviado';
    case InRepair = 'en_reparacion';
    case Ready = 'listo';
    case PickedUp = 'retirado';
    case NotRepaired = 'sin_reparacion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sent => 'Enviado',
            self::InRepair => 'En reparación',
            self::Ready => 'Listo para retirar',
            self::PickedUp => 'Retirado',
            self::NotRepaired => 'Sin reparación',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sent => 'gray',
            self::InRepair => 'warning',
            self::Ready => 'info',
            self::PickedUp => 'success',
            self::NotRepaired => 'danger',
        };
    }

    /**
     * The device is still at the supplier's.
     */
    public function isOut(): bool
    {
        return in_array($this, [self::Sent, self::InRepair, self::Ready], true);
    }

    /**
     * @return array<self>
     */
    public static function out(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isOut()));
    }
}
