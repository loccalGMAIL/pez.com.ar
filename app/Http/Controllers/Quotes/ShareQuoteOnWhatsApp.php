<?php

namespace App\Http\Controllers\Quotes;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Support\QuotePdf;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;

/**
 * Opened from the panel in a new tab: archives the PDF, marks the quote as sent
 * and hands over to WhatsApp with the message and the PDF link already written.
 */
class ShareQuoteOnWhatsApp extends Controller
{
    public function __invoke(Quote $quote): RedirectResponse
    {
        abort_unless($quote->isEditable(), 403);

        $quote->markAsSent();

        $client = $quote->client;
        $phone = $client->phone ?: $client->primaryContact?->phone;

        $text = "Hola! Te comparto el presupuesto {$quote->code} de ".config('pez.company.name')
            .' por '.QuotePdf::money($quote->total)
            .", válido hasta el {$quote->valid_until->format('d/m/Y')}:\n"
            .$quote->publicPdfUrl();

        return redirect()->away(WhatsApp::url($phone, $text));
    }
}
