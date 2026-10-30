<?php

namespace App\Http\Controllers\Quotes;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Support\QuotePdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public, signed link to the PDF as it was shared with the client.
 */
class ShowQuotePdf extends Controller
{
    public function __invoke(Quote $quote): StreamedResponse
    {
        abort_unless($quote->pdf_path && Storage::disk(QuotePdf::DISK)->exists($quote->pdf_path), 404);

        return Storage::disk(QuotePdf::DISK)->response(
            $quote->pdf_path,
            QuotePdf::filename($quote),
            ['Content-Type' => 'application/pdf'],
            'inline',
        );
    }
}
