<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'efectivo';
    case Transfer = 'transferencia';
    case MercadoPago = 'mercadopago';
    case Card = 'tarjeta';
    case Other = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Transfer => 'Transferencia',
            self::MercadoPago => 'Mercado Pago',
            self::Card => 'Tarjeta',
            self::Other => 'Otro',
        };
    }
}
