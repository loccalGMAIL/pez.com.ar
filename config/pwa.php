<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installable App (PWA)
    |--------------------------------------------------------------------------
    |
    | Manifest, service worker and splash screens of the Filament panel,
    | served by App\Http\Controllers\PwaController on the panel domain.
    |
    | Leave the version empty so it follows the Vite build and the panel
    | offers the update after every `npm run build`. If it is pinned, it
    | has to be bumped on every release or the update notice never shows.
    |
    */

    'panel' => 'admin',

    'name' => env('PWA_NAME', 'PEZ Sistema'),

    'description' => 'Sistema de gestión de PEZ Servicios IT.',

    'lang' => 'es-AR',

    'version' => env('PWA_VERSION'),

    'cache_prefix' => 'pez-sistema-v',

    // Filament's topbar colors (light / dark), not the primary color.
    'theme_color' => '#ffffff',

    'theme_color_dark' => '#111827',

    // Background of the maskable icon and of the iOS splash screens.
    'background_color' => '#092E35',

    'shortcuts' => [
        ['name' => 'Crear orden de trabajo', 'short_name' => 'Nueva orden', 'url' => '/trabajos/create'],
        ['name' => 'Órdenes de trabajo', 'short_name' => 'Órdenes', 'url' => '/trabajos'],
        ['name' => 'Presupuestos', 'short_name' => 'Presupuestos', 'url' => '/presupuestos'],
    ],

    'screenshots' => [
        // ['src' => '/screenshots/escritorio.png', 'sizes' => '1280x720', 'form_factor' => 'wide', 'label' => 'Escritorio'],
    ],

];
