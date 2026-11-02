<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What the invoice is for, as ARCA codes it.
 */
enum InvoiceConcept: int implements HasLabel
{
    case Products = 1;
    case Services = 2;
    case Both = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Products => 'Productos',
            self::Services => 'Servicios',
            self::Both => 'Productos y servicios',
        };
    }

    /**
     * ARCA requires the service period and the payment due date unless only products are sold.
     */
    public function hasServicePeriod(): bool
    {
        return $this !== self::Products;
    }

    /**
     * How many days back ARCA accepts the invoice date.
     */
    public function maxDaysBack(): int
    {
        return $this === self::Products ? 5 : 10;
    }
}
