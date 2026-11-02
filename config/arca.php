<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ARCA Electronic Invoicing (WSAA + WSFEv1)
    |--------------------------------------------------------------------------
    |
    | "homologacion" is ARCA's test environment: its invoices have no fiscal
    | value. The issuer's CUIT is the company CUIT from Sistema → Configuración,
    | and the certificate must be issued for that CUIT and the "wsfe" service.
    | The certificate and key never go in the repository or the database.
    |
    */

    'environment' => env('ARCA_ENV', 'homologacion'),

    'certificate' => env('ARCA_CERT_PATH', storage_path('app/private/arca/arca.crt')),

    'key' => env('ARCA_KEY_PATH', storage_path('app/private/arca/arca.key')),

    'key_passphrase' => env('ARCA_KEY_PASSPHRASE'),

    // Seconds to wait for each answer from ARCA, short enough for the page to
    // finish within PHP's time limit. Without an answer the invoice stays
    // pending and retrying checks with ARCA before asking for a new CAE.
    'timeout' => (int) env('ARCA_TIMEOUT', 15),

    // From this total ARCA requires identifying a consumidor final (CUIT or DNI).
    // Set by ARCA; update it here if it changes.
    'unidentified_limit' => (float) env('ARCA_UNIDENTIFIED_LIMIT', 10000000),

    'endpoints' => [
        'homologacion' => [
            'wsaa' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
            'wsfe' => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx',
        ],
        'produccion' => [
            'wsaa' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
            'wsfe' => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx',
        ],
    ],

];
