<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\Payments\ShowReceiptPdf;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\Quotes\ShowQuotePdf;
use App\Http\Controllers\WorkOrders\ShowWorkOrderPdf;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::domain(config('app.domain'))->group(function () {
    Route::get('/', function () {
        return view('landing');
    });

    Route::post('/contacto', [ContactController::class, 'send'])->name('contacto.send');

    Route::get('/gracias', function () {
        return view('gracias');
    })->name('gracias');

    Route::get('/presupuesto/{quote}', ShowQuotePdf::class)
        ->middleware('signed')
        ->name('quotes.pdf');

    Route::get('/recibo/{payment}', ShowReceiptPdf::class)
        ->middleware('signed')
        ->name('receipts.pdf');

    Route::get('/orden/{workOrder}', ShowWorkOrderPdf::class)
        ->middleware('signed')
        ->name('work-orders.pdf');
});

/*
 * Installable panel (PWA). Without session or CSRF: the offline page is kept
 * in the browser cache, and a Set-Cookie there would restore a stale session.
 */
Route::domain(config('app.admin_domain'))
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->group(function () {
        Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
        Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');
        Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');
        Route::get('/icons/splash/{width}x{height}.png', [PwaController::class, 'splash'])
            ->whereNumber(['width', 'height'])
            ->name('pwa.splash');
    });
