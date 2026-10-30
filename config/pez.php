<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company
    |--------------------------------------------------------------------------
    |
    | Data printed on quotes and other documents sent to clients.
    |
    */

    'company' => [
        'name' => env('COMPANY_NAME', 'PEZ Servicios IT'),
        'cuit' => env('COMPANY_CUIT'),
        'email' => env('COMPANY_EMAIL', 'info@pez.com.ar'),
        'phone' => env('COMPANY_PHONE'),
        'address' => env('COMPANY_ADDRESS'),
        'website' => env('COMPANY_WEBSITE', 'pez.com.ar'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotes
    |--------------------------------------------------------------------------
    */

    'quotes' => [
        'first_number' => (int) env('QUOTES_FIRST_NUMBER', 100),
        'valid_days' => (int) env('QUOTES_VALID_DAYS', 15),
        'default_terms' => env('QUOTES_DEFAULT_TERMS', "Precios finales en pesos argentinos.\nForma de pago: a convenir."),
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
