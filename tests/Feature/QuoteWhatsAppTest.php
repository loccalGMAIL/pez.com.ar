<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Quotes\Pages\ViewQuote;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use App\Support\QuotePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(QuotePdf::DISK);
    }

    private function shareUrl(Quote $quote): string
    {
        return route('filament.admin.quotes.whatsapp', $quote);
    }

    public function test_sharing_marks_the_quote_as_sent_and_opens_whatsapp(): void
    {
        $client = Client::factory()->create(['phone' => '011 2345-6789']);
        $quote = Quote::factory()->for($client)->withItems()->create();

        $response = $this->actingAs(User::factory()->create())->get($this->shareUrl($quote));

        $quote->refresh();
        $this->assertSame(QuoteStatus::Sent, $quote->status);
        Storage::disk(QuotePdf::DISK)->assertExists($quote->pdf_path);

        $location = $response->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/5491123456789?text=', $location);

        $text = rawurldecode(substr($location, strpos($location, '=') + 1));
        $this->assertStringContainsString('P-00100', $text);
        $this->assertStringContainsString(QuotePdf::money($quote->total), $text);
        $this->assertStringContainsString($quote->publicPdfUrl(), $text);
    }

    public function test_without_a_usable_phone_whatsapp_asks_for_the_contact(): void
    {
        $quote = Quote::factory()->for(Client::factory()->create(['phone' => null]))->create();

        $location = $this->actingAs(User::factory()->create())
            ->get($this->shareUrl($quote))
            ->headers->get('Location');

        $this->assertStringStartsWith('https://wa.me/?text=', $location);
    }

    public function test_closed_quotes_can_not_be_shared(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Accepted)->create();

        $this->actingAs(User::factory()->create())
            ->get($this->shareUrl($quote))
            ->assertForbidden();
    }

    public function test_guests_can_not_share(): void
    {
        $quote = Quote::factory()->create();

        $this->get($this->shareUrl($quote))->assertRedirect();
        $this->assertSame(QuoteStatus::Draft, $quote->refresh()->status);
    }

    public function test_the_client_opens_the_pdf_with_the_signed_link(): void
    {
        $quote = Quote::factory()->withItems()->create();
        $quote->markAsSent();

        $this->get($quote->publicPdfUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_pdf_link_can_not_be_guessed(): void
    {
        $quote = Quote::factory()->withItems()->create();
        $quote->markAsSent();

        $this->get('http://'.config('app.domain').'/presupuesto/'.$quote->id)->assertForbidden();
    }

    public function test_the_view_page_offers_whatsapp_sharing(): void
    {
        $this->actingAs(User::factory()->create());
        $quote = Quote::factory()->create();

        Livewire::test(ViewQuote::class, ['record' => $quote->getRouteKey()])
            ->assertActionVisible('shareOnWhatsApp')
            ->assertActionHasUrl('shareOnWhatsApp', $this->shareUrl($quote))
            ->assertActionShouldOpenUrlInNewTab('shareOnWhatsApp');
    }
}
