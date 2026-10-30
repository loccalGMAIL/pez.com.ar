<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\Payments\ShowReceiptPdf;
use App\Http\Controllers\Quotes\ShowQuotePdf;
use Illuminate\Support\Facades\Route;

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
});
