<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\InvoicePdf;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->invoice->title} — ".Settings::get('company.name'),
            replyTo: array_filter([Settings::get('company.email')]),
        );
    }

    public function content(): Content
    {
        // Same plain message with the company signature as the quotes.
        return new Content(
            markdown: 'emails.quote',
            with: [
                'body' => $this->body,
                'company' => Settings::company(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => InvoicePdf::contents($this->invoice), InvoicePdf::filename($this->invoice))
                ->withMime('application/pdf'),
        ];
    }
}
