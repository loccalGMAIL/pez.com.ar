<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    public function test_contact_form_sends_mail_and_redirects(): void
    {
        Mail::fake();
        config(['mail.contact_address' => 'contact@example.com']);

        $data = [
            'nombre' => 'Cliente de prueba',
            'email' => 'cliente@example.com',
            'mensaje' => 'Necesito información sobre los servicios.',
        ];

        $this->post(route('contacto.send'), $data)
            ->assertRedirect(route('gracias'))
            ->assertSessionHasNoErrors();

        Mail::assertSent(ContactFormMail::class, function (ContactFormMail $mail) use ($data): bool {
            return $mail->hasTo('contact@example.com')
                && $mail->data === $data
                && $mail->envelope()->replyTo[0]->address === $data['email'];
        });
        Mail::assertSentCount(1);
    }

    public function test_contact_form_rejects_invalid_data_without_sending_mail(): void
    {
        Mail::fake();

        $this->from('/')->post(route('contacto.send'), [
            'nombre' => '',
            'email' => 'invalid-email',
            'mensaje' => '',
        ])->assertRedirect('/')
            ->assertSessionHasErrors(['nombre', 'email', 'mensaje']);

        Mail::assertNothingSent();
    }

    public function test_thank_you_page_is_available(): void
    {
        $this->get(route('gracias'))->assertOk()->assertSee('¡Mensaje recibido!');
    }
}
