<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre'  => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:200'],
            'mensaje' => ['required', 'string', 'max:2000'],
        ], [
            'nombre.required'  => 'El nombre es obligatorio.',
            'email.required'   => 'El email es obligatorio.',
            'email.email'      => 'Ingresá un email válido.',
            'mensaje.required' => 'El mensaje no puede estar vacío.',
        ]);

        Mail::to(config('mail.contact_address', 'info@pez.com.ar'))
            ->send(new ContactFormMail($validated));

        return redirect()->route('gracias');
    }
}
