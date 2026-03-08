<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::post('/contacto', [ContactController::class, 'send'])->name('contacto.send');

Route::get('/gracias', function () {
    return view('gracias');
})->name('gracias');
