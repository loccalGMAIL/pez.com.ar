<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Resources\Quotes\Pages\ViewQuote;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Mail\QuoteMail;
use App\Models\Client;
use App\Models\Quote;
use App\Models\ServiceItem;
use App\Models\User;
use App\Support\QuotePdf;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lists_quotes_and_searches_by_number(): void
    {
        $quotes = Quote::factory()->count(3)->create();

        Livewire::test(ListQuotes::class)
            ->assertCanSeeTableRecords($quotes)
            ->searchTable('P-00101')
            ->assertCanSeeTableRecords([$quotes[1]])
            ->assertCanNotSeeTableRecords([$quotes[0], $quotes[2]]);
    }

    public function test_creates_a_quote_with_items(): void
    {
        $undoRepeaterFake = Repeater::fake();

        $client = Client::factory()->create();
        $service = ServiceItem::factory()->create(['price' => 30000]);

        Livewire::test(CreateQuote::class)
            ->fillForm([
                'client_id' => $client->id,
                'discount' => 10000,
                'items' => [
                    ['service_item_id' => $service->id, 'description' => 'Soporte', 'quantity' => 2, 'unit' => 'hora', 'unit_price' => 30000],
                    ['service_item_id' => null, 'description' => 'Cable UTP', 'quantity' => 1.5, 'unit' => 'm', 'unit_price' => 1000],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $quote = Quote::sole();
        $this->assertSame(100, $quote->number);
        $this->assertSame(QuoteStatus::Draft, $quote->status);
        $this->assertCount(2, $quote->items);
        $this->assertSame(61500.0, $quote->subtotal);
        $this->assertSame(51500.0, $quote->total);
        $this->assertSame('6 meses', $quote->warranty);
        $this->assertSame('Efectivo o transferencia', $quote->payment_terms);
        $this->assertSame(7, $quote->validDays());
    }

    public function test_the_form_has_no_title_and_keeps_extras_in_closed_accordions(): void
    {
        $page = Livewire::test(CreateQuote::class)
            ->assertFormFieldDoesNotExist('title');

        $sections = collect($page->instance()->getSchema('form')->getFlatComponents())
            ->filter(fn ($component) => $component instanceof Section && filled($component->getHeading()))
            ->keyBy(fn (Section $section) => (string) $section->getHeading());

        foreach (['Condiciones', 'Cuotas', 'Compra (uso interno, no se imprime)'] as $heading) {
            $this->assertTrue($sections[$heading]->isCollapsed(), "{$heading} should start closed");
        }
    }

    public function test_the_create_page_floats_create_and_cancel_without_create_another(): void
    {
        Livewire::test(CreateQuote::class)
            ->assertSee('Crear')
            ->assertSee('Cancelar')
            ->assertDontSee('Crear y crear otro');
    }

    public function test_choosing_a_catalog_item_fills_the_line(): void
    {
        $undoRepeaterFake = Repeater::fake();

        $service = ServiceItem::factory()->create(['name' => 'Visita técnica', 'description' => null, 'price' => 45000]);
        $detailed = ServiceItem::factory()->create(['name' => 'Servicio Técnico', 'description' => 'Instalación de sistema operativo', 'price' => 78000]);

        Livewire::test(CreateQuote::class)
            ->fillForm(['items' => [['service_item_id' => $service->id], ['service_item_id' => $detailed->id]]])
            ->assertSchemaStateSet([
                'items.0.title' => null,
                'items.0.description' => 'Visita técnica',
                'items.0.unit_price' => 45000,
                'items.1.title' => 'Servicio Técnico',
                'items.1.description' => 'Instalación de sistema operativo',
            ]);

        $undoRepeaterFake();
    }

    public function test_choosing_a_catalog_product_brings_its_cost_and_supplier(): void
    {
        $undoRepeaterFake = Repeater::fake();

        $product = ServiceItem::factory()->product()->withCost(50000)->create(['name' => 'SSD 480 GB', 'price' => 65000]);

        Livewire::test(CreateQuote::class)
            ->fillForm(['items' => [['service_item_id' => $product->id]]])
            ->assertSchemaStateSet([
                'items.0.unit_price' => 65000,
                'items.0.unit_cost' => 50000,
                'items.0.supplier_id' => $product->supplier_id,
                'items.0.markup' => 30,
            ]);

        $undoRepeaterFake();
    }

    public function test_a_quote_needs_at_least_one_item(): void
    {
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateQuote::class)
            ->fillForm(['client_id' => Client::factory()->create()->id, 'items' => []])
            ->call('create')
            ->assertHasFormErrors(['items']);

        $undoRepeaterFake();
    }

    public function test_sends_a_quote_from_its_page(): void
    {
        Storage::fake(QuotePdf::DISK);
        Mail::fake();

        $quote = Quote::factory()->withItems()->create();

        Livewire::test(ViewQuote::class, ['record' => $quote->getRouteKey()])
            ->callAction('send', data: ['to' => 'cliente@example.com', 'body' => 'Adjunto presupuesto'])
            ->assertHasNoActionErrors();

        $this->assertSame(QuoteStatus::Sent, $quote->refresh()->status);
        Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail) => $mail->hasTo('cliente@example.com'));
    }

    public function test_accepts_a_sent_quote(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Sent)->create();

        Livewire::test(ViewQuote::class, ['record' => $quote->getRouteKey()])
            ->assertActionHidden('markAsSent')
            ->callAction('accept');

        $this->assertSame(QuoteStatus::Accepted, $quote->refresh()->status);
    }

    public function test_answered_quotes_can_not_be_edited_or_deleted(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Accepted)->create();

        $this->assertFalse(QuoteResource::canEdit($quote));
        $this->assertFalse(QuoteResource::canDelete($quote));

        $this->get(QuoteResource::getUrl('edit', ['record' => $quote]))->assertForbidden();
    }

    public function test_only_drafts_can_be_deleted(): void
    {
        $this->assertTrue(QuoteResource::canDelete(Quote::factory()->create()));
        $this->assertFalse(QuoteResource::canDelete(Quote::factory()->status(QuoteStatus::Sent)->create()));
    }

    public function test_duplicates_and_opens_the_copy(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Rejected)->withItems()->create();

        Livewire::test(ViewQuote::class, ['record' => $quote->getRouteKey()])
            ->callAction('duplicate')
            ->assertRedirect(QuoteResource::getUrl('edit', ['record' => Quote::latest('id')->first()]));

        $this->assertSame(2, Quote::count());
    }

    public function test_downloads_the_pdf(): void
    {
        $quote = Quote::factory()->withItems()->create();

        Livewire::test(EditQuote::class, ['record' => $quote->getRouteKey()])
            ->callAction('downloadPdf')
            ->assertFileDownloaded('Presupuesto-P-00100.pdf');
    }

    public function test_view_and_edit_pages_render(): void
    {
        $quote = Quote::factory()->withItems()->create();

        $this->get(QuoteResource::getUrl('view', ['record' => $quote]))->assertOk()->assertSee('P-00100');
        $this->get(QuoteResource::getUrl('edit', ['record' => $quote]))->assertOk();
        $this->get(QuoteResource::getUrl('create'))->assertOk();
    }
}
