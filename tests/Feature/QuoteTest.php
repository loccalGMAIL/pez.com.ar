<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Mail\QuoteMail;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Support\QuotePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbering_starts_at_100_and_is_correlative(): void
    {
        $first = Quote::factory()->create();
        $second = Quote::factory()->create();

        $this->assertSame(100, $first->number);
        $this->assertSame('P-00100', $first->code);
        $this->assertSame(101, $second->number);
    }

    public function test_numbering_never_reuses_numbers_of_deleted_quotes(): void
    {
        Quote::factory()->create();
        Quote::factory()->create()->delete();

        $this->assertSame(102, Quote::factory()->create()->number);
    }

    public function test_totals_follow_items_and_discount(): void
    {
        $quote = Quote::factory()->create();
        $quote->items()->create(['description' => 'Hora de soporte', 'quantity' => 3, 'unit_price' => 25000]);
        $item = $quote->items()->create(['description' => 'Router', 'quantity' => 1, 'unit_price' => 80000.50]);

        $quote->refresh();
        $this->assertSame(155000.5, $quote->subtotal);
        $this->assertSame(155000.5, $quote->total);

        $quote->update(['discount' => 5000.5]);
        $this->assertSame(150000.0, $quote->refresh()->total);

        $item->delete();
        $this->assertSame(69999.5, $quote->refresh()->total);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'subtotal' => 7500000, 'total' => 6999950]);
    }

    public function test_discount_never_makes_the_total_negative(): void
    {
        $quote = Quote::factory()->create(['discount' => 1000]);
        $quote->items()->create(['description' => 'Algo', 'quantity' => 1, 'unit_price' => 500]);

        $this->assertSame(0.0, $quote->refresh()->total);
    }

    public function test_renders_a_pdf(): void
    {
        $quote = Quote::factory()->withItems()->create();

        $this->assertStringStartsWith('%PDF', QuotePdf::render($quote));
    }

    public function test_sending_archives_the_pdf_and_mails_it(): void
    {
        Storage::fake(QuotePdf::DISK);
        Mail::fake();

        $quote = Quote::factory()->withItems()->create();
        $quote->sendTo('cliente@example.com', 'Hola');

        $quote->refresh();
        $this->assertSame(QuoteStatus::Sent, $quote->status);
        $this->assertNotNull($quote->sent_at);
        $this->assertSame('quotes/P-00100.pdf', $quote->pdf_path);
        Storage::disk(QuotePdf::DISK)->assertExists('quotes/P-00100.pdf');

        Mail::assertSent(QuoteMail::class, function (QuoteMail $mail) {
            return $mail->hasTo('cliente@example.com')
                && $mail->hasAttachment(
                    \Illuminate\Mail\Mailables\Attachment::fromStorageDisk(QuotePdf::DISK, 'quotes/P-00100.pdf')
                        ->as('Presupuesto-P-00100.pdf')
                        ->withMime('application/pdf')
                );
        });
    }

    public function test_answered_quotes_are_frozen(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Sent)->create();
        $this->assertTrue($quote->isEditable());

        $quote->accept();

        $this->assertSame(QuoteStatus::Accepted, $quote->refresh()->status);
        $this->assertNotNull($quote->decided_at);
        $this->assertFalse($quote->isEditable());
    }

    public function test_duplicate_creates_a_new_draft_with_the_same_items(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Rejected)->withItems(3)->create(['discount' => 100]);

        $copy = $quote->duplicate();

        $this->assertNotSame($quote->number, $copy->number);
        $this->assertSame(QuoteStatus::Draft, $copy->status);
        $this->assertSame($quote->client_id, $copy->client_id);
        $this->assertCount(3, $copy->items);
        $this->assertSame($quote->refresh()->total, $copy->total);
    }

    public function test_expire_command_only_touches_sent_quotes_past_their_validity(): void
    {
        $expired = Quote::factory()->status(QuoteStatus::Sent)->create(['valid_until' => today()->subDay()]);
        $stillValid = Quote::factory()->status(QuoteStatus::Sent)->create(['valid_until' => today()]);
        $draft = Quote::factory()->create(['valid_until' => today()->subDay()]);

        $this->artisan('quotes:expire')->assertSuccessful();

        $this->assertSame(QuoteStatus::Expired, $expired->refresh()->status);
        $this->assertSame(QuoteStatus::Sent, $stillValid->refresh()->status);
        $this->assertSame(QuoteStatus::Draft, $draft->refresh()->status);
    }

    public function test_item_subtotal_uses_decimal_quantities(): void
    {
        $item = QuoteItem::factory()->create(['quantity' => 1.5, 'unit_price' => 1000]);

        $this->assertSame(1500.0, $item->subtotal);
    }
}
