<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Quotes\ShareQuoteOnWhatsApp;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->domain(config('app.admin_domain'))
            ->path('')
            ->login()
            ->profile(isSimple: false)
            ->brandName('PEZ Sistema')
            ->navigationGroups(array_map(
                fn (string $label) => NavigationGroup::make($label)->collapsed(),
                ['Ventas', 'Taller', 'Cobranza', 'Sistema'],
            ))
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.pwa.head'))
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.pwa.install-button'))
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.version'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.auth.google-button'))
            ->routes(function () {
                Route::get('auth/google/redirect', [GoogleLoginController::class, 'redirect'])->name('auth.google.redirect');
                Route::get('auth/google/callback', [GoogleLoginController::class, 'callback'])->name('auth.google.callback');
            })
            ->authenticatedRoutes(function () {
                Route::get('presupuestos/{quote}/whatsapp', ShareQuoteOnWhatsApp::class)->name('quotes.whatsapp');
            });
    }
}
