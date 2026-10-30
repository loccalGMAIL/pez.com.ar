<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company and Quote Defaults
    |--------------------------------------------------------------------------
    |
    | Starting values only: they are edited from the panel (Sistema →
    | Configuración) and read through App\Support\Settings, never from .env.
    |
    */

    'company' => [
        'name' => 'PEZ Servicios IT',
        'cuit' => null,
        'email' => 'info@pez.com.ar',
        'phone' => null,
        'address' => null,
        'website' => 'pez.com.ar',
        'owner' => null,
        'slogan' => 'Transformamos ideas en realidades digitales',
    ],

    'quotes' => [
        // Numbering is set once when installing, so it stays out of the panel.
        'first_number' => (int) env('QUOTES_FIRST_NUMBER', 100),
        'valid_days' => 7,
        'default_warranty' => '6 meses',
        'default_payment_terms' => 'Efectivo o transferencia',
        'default_delivery_time' => '72 hs hábiles',
        'default_terms' => 'Precios sujetos a disponibilidad de stock y variaciones del mercado hasta la confirmación del pedido.',
        'installments' => [
            ['count' => 3, 'surcharge' => 0],
            ['count' => 6, 'surcharge' => 0],
            ['count' => 12, 'surcharge' => 0],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Work Orders
    |--------------------------------------------------------------------------
    */

    'work_orders' => [
        'first_number' => (int) env('WORK_ORDERS_FIRST_NUMBER', 1),
    ],

];
