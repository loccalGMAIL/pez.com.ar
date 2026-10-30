<?php

namespace Tests\Feature;

use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use App\Support\Settings;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteInstallmentsTest extends TestCase
{
    use RefreshDatabase;

    private function quoteTotaling(float $amount, ?array $plans = null): Quote
    {
        $quote = Quote::factory()->create(['installment_plans' => $plans]);
        $quote->items()->create(['description' => 'Equipo', 'quantity' => 1, 'unit_price' => $amount]);

        return $quote->refresh();
    }

    public function test_computes_each_plan_with_its_surcharge(): void
    {
        // Same numbers as the old spreadsheet: $4.225 with a 40,65 % surcharge.
        $quote = $this->quoteTotaling(4225, [
            ['count' => 12, 'surcharge' => 40.65],
            ['count' => 3, 'surcharge' => 40.65],
            ['count' => 6, 'surcharge' => 0],
        ]);

        $plans = $quote->installments();

        $this->assertSame([3, 6, 12], $plans->pluck('count')->all());
        $this->assertSame(['count' => 3, 'surcharge' => 40.65, 'total' => 5942.46, 'amount' => 1980.82], $plans[0]);
        $this->assertSame(['count' => 6, 'surcharge' => 0.0, 'total' => 4225.0, 'amount' => 704.17], $plans[1]);
        $this->assertSame(495.21, $plans[2]['amount']);
    }

    public function test_quotes_without_plans_have_no_installments(): void
    {
        $this->assertTrue($this->quoteTotaling(1000)->installments()->isEmpty());
        $this->assertTrue($this->quoteTotaling(1000, [])->installments()->isEmpty());
    }

    public function test_amounts_are_stored_with_the_quote(): void
    {
        $quote = $this->quoteTotaling(1000, [['count' => 4, 'surcharge' => 20]]);

        $this->assertEquals([['count' => 4, 'surcharge' => 20, 'total' => 1200, 'amount' => 300]], $quote->installment_plans);

        // Editing the items re-prices the plans while the quote is open.
        $quote->items()->create(['description' => 'Otro', 'quantity' => 1, 'unit_price' => 1000]);
        $this->assertSame(600.0, $quote->refresh()->installments()->first()['amount']);
    }

    public function test_changing_the_settings_does_not_touch_existing_quotes(): void
    {
        Settings::set(['quotes.installments' => [['count' => 3, 'surcharge' => 10]]]);
        $quote = $this->quoteTotaling(3000, Quote::defaultInstallmentPlans());

        Settings::set(['quotes.installments' => [['count' => 3, 'surcharge' => 50]]]);

        $this->assertSame(1100.0, $quote->refresh()->installments()->first()['amount']);
        $this->assertSame(50.0, Quote::defaultInstallmentPlans()[0]['surcharge']);
    }

    public function test_pdf_lists_installments_only_when_offered(): void
    {
        $render = fn (Quote $quote) => view('pdf.quote', ['quote' => $quote, 'company' => config('pez.company')])->render();

        $this->assertStringNotContainsString('EN CUOTAS', $render($this->quoteTotaling(1000)));

        $html = $render($this->quoteTotaling(1200, [['count' => 3, 'surcharge' => 0], ['count' => 6, 'surcharge' => 50]]));
        $this->assertStringContainsString('EN CUOTAS', $html);
        $this->assertStringContainsString('3 cuotas de', $html);
        $this->assertStringContainsString('$ 400,00', $html);
        $this->assertStringContainsString('sin recargo', $html);
        $this->assertStringContainsString('total $ 1.800,00', $html);
    }

    public function test_installments_are_optional_when_creating_a_quote(): void
    {
        $this->actingAs(User::factory()->create());
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateQuote::class)
            ->assertFormFieldHidden('installment_plans')
            ->fillForm([
                'client_id' => Client::factory()->create()->id,
                'items' => [['description' => 'Notebook', 'quantity' => 1, 'unit_price' => 900000]],
            ])
            ->set('data.offer_installments', true)
            ->assertFormFieldVisible('installment_plans')
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $quote = Quote::sole();
        $this->assertSame([3, 6, 12], $quote->installments()->pluck('count')->all());
        $this->assertSame(75000.0, $quote->installments()->last()['amount']);
    }

    public function test_turning_installments_off_removes_them(): void
    {
        $this->actingAs(User::factory()->create());
        $quote = $this->quoteTotaling(1000, [['count' => 3, 'surcharge' => 0]]);

        Livewire::test(EditQuote::class, ['record' => $quote->getRouteKey()])
            ->assertSchemaStateSet(['offer_installments' => true])
            ->set('data.offer_installments', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($quote->refresh()->installments()->isEmpty());
    }
}
