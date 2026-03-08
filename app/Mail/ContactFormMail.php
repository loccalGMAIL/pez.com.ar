<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $data,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo mensaje de contacto — ' . $this->data['nombre'],
            replyTo: [
                new \Illuminate\Mail\Mailables\Address($this->data['email'], $this->data['nombre']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact',
            with: [
                'nombre'  => $this->data['nombre'],
                'email'   => $this->data['email'],
                'mensaje' => $this->data['mensaje'],
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
