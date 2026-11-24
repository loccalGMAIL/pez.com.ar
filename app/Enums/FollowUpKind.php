<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FollowUpKind: string implements HasLabel
{
    case WorkOrder = 'trabajo';
    case Sale = 'venta';

    public function getLabel(): string
    {
        return match ($this) {
            self::WorkOrder => 'Trabajo entregado',
            self::Sale => 'Venta',
        };
    }

    /**
     * Settings key of the message sent for this kind.
     */
    public function messageKey(): string
    {
        return match ($this) {
            self::WorkOrder => 'follow_ups.work_order_message',
            self::Sale => 'follow_ups.sale_message',
        };
    }
}
