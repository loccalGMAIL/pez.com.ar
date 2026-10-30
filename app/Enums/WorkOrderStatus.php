<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pendiente';
    case InProgress = 'en_curso';
    case AwaitingExternal = 'esperando_service';
    case Finished = 'terminado';
    case Delivered = 'entregado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InProgress => 'En curso',
            self::AwaitingExternal => 'Esperando service externo',
            self::Finished => 'Terminado',
            self::Delivered => 'Entregado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'info',
            self::AwaitingExternal => 'warning',
            self::Finished => 'success',
            self::Delivered => 'primary',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Finished, self::Delivered], true);
    }

    /**
     * @return array<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isOpen()));
    }
}
