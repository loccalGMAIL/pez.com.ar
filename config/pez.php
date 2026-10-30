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
        'owner' => env('COMPANY_OWNER'),
        'slogan' => env('COMPANY_SLOGAN', 'Transformamos ideas en realidades digitales'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotes
    |--------------------------------------------------------------------------
    */

    'quotes' => [
        'first_number' => (int) env('QUOTES_FIRST_NUMBER', 100),
        'valid_days' => (int) env('QUOTES_VALID_DAYS', 7),
        'default_warranty' => env('QUOTES_DEFAULT_WARRANTY', '6 meses'),
        'default_payment_terms' => env('QUOTES_DEFAULT_PAYMENT_TERMS', 'Efectivo o transferencia'),
        'default_delivery_time' => env('QUOTES_DEFAULT_DELIVERY_TIME', '72 hs hábiles'),
        // Plans proposed when "cuotas" is turned on in a quote. Editable from the
        // panel's settings page; QUOTES_DEFAULT_INSTALLMENTS ("cuotas:recargo%") seeds it.
        'installments' => collect(explode(',', (string) env('QUOTES_DEFAULT_INSTALLMENTS', '3:0,6:0,12:0')))
            ->map(fn (string $plan) => explode(':', trim($plan)) + [1 => 0])
            ->filter(fn (array $plan) => (int) $plan[0] > 1)
            ->map(fn (array $plan) => ['count' => (int) $plan[0], 'surcharge' => (float) $plan[1]])
            ->values()
            ->all(),
        'default_terms' => env('QUOTES_DEFAULT_TERMS', 'Precios sujetos a disponibilidad de stock y variaciones del mercado hasta la confirmación del pedido.'),
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
