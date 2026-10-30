<?php

namespace App\Mail;

use App\Models\Quote;
use App\Support\QuotePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Quote $quote,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Presupuesto {$this->quote->code} — ".config('pez.company.name'),
            replyTo: [config('pez.company.email')],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.quote',
            with: [
                'body' => $this->body,
                'company' => config('pez.company'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(QuotePdf::DISK, $this->quote->pdf_path)
                ->as(QuotePdf::filename($this->quote))
                ->withMime('application/pdf'),
        ];
    }
}
