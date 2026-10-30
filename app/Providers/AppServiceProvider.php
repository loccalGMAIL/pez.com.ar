<?php

namespace App\Providers;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prebuilt with `npm run build:scanner`; published by `php artisan filament:assets`.
        FilamentAsset::register([
            Js::make('serial-scanner', resource_path('js/dist/serial-scanner.js'))->loadedOnRequest(),
        ]);
    }
}
