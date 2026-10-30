<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings as SettingsPage;
use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Models\Quote;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_settings_fall_back_to_config_until_saved(): void
    {
        $this->assertSame(7, Settings::get('quotes.valid_days'));
        $this->assertSame([3, 6, 12], array_column(Settings::get('quotes.installments'), 'count'));
    }

    public function test_shows_current_values(): void
    {
        Livewire::test(SettingsPage::class)
            ->assertSchemaStateSet([
                'quotes_valid_days' => 7,
                'quotes_default_warranty' => '6 meses',
            ]);

        $this->get(SettingsPage::getUrl())->assertOk()->assertSee('Cuotas');
    }

    public function test_saves_installment_surcharges_and_quote_defaults(): void
    {
        Livewire::test(SettingsPage::class)
            ->fillForm([
                'quotes_installments' => [
                    ['count' => 12, 'surcharge' => 40.65],
                    ['count' => 3, 'surcharge' => 15],
                ],
                'quotes_valid_days' => 10,
                'quotes_default_warranty' => '12 meses',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Configuración guardada');

        $this->assertSame([
            ['count' => 3, 'surcharge' => 15.0],
            ['count' => 12, 'surcharge' => 40.65],
        ], Quote::defaultInstallmentPlans());
        $this->assertSame(10, Settings::get('quotes.valid_days'));
        $this->assertSame('12 meses', Settings::get('quotes.default_warranty'));
    }

    public function test_rejects_repeated_or_invalid_plans(): void
    {
        Livewire::test(SettingsPage::class)
            ->fillForm([
                'quotes_installments' => [
                    ['count' => 3, 'surcharge' => 10],
                    ['count' => 3, 'surcharge' => 20],
                    ['count' => 1, 'surcharge' => -5],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame(0, \App\Models\Setting::count());
    }

    public function test_new_quotes_start_from_the_saved_settings(): void
    {
        Settings::set([
            'quotes.valid_days' => 10,
            'quotes.default_warranty' => '1 año',
            'quotes.installments' => [['count' => 6, 'surcharge' => 25]],
        ]);

        Livewire::test(CreateQuote::class)
            ->assertSchemaStateSet([
                'warranty' => '1 año',
                'valid_until' => today()->addDays(10)->toDateString(),
            ])
            ->set('data.offer_installments', true)
            ->assertSchemaStateSet(fn (array $state) => [
                'installment_plans' => array_values($state['installment_plans']) === [['count' => 6, 'surcharge' => 25.0]]
                    ? $state['installment_plans']
                    : 'unexpected',
            ]);
    }

    public function test_company_data_is_edited_in_the_panel_and_printed_on_quotes(): void
    {
        Livewire::test(SettingsPage::class)
            ->fillForm([
                'company_name' => 'PEZ Servicios IT',
                'company_owner' => 'Claudio López',
                'company_phone' => '03541-549674',
                'company_email' => 'loccal@hotmail.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Claudio López', Settings::company()['owner']);

        $quote = Quote::factory()->create();
        $html = view('pdf.quote', ['quote' => $quote, 'company' => Settings::company()])->render();
        $this->assertStringContainsString('CLAUDIO LÓPEZ', $html);
        $this->assertStringContainsString('03541-549674', $html);
        $this->assertStringContainsString('loccal@hotmail.com', $html);

        $mail = new \App\Mail\QuoteMail($quote, 'Hola');
        $this->assertTrue($mail->hasReplyTo('loccal@hotmail.com'));
    }

    public function test_company_name_is_required_and_cuit_is_validated(): void
    {
        Livewire::test(SettingsPage::class)
            ->fillForm(['company_name' => '', 'company_cuit' => '20-12345678-5'])
            ->call('save')
            ->assertHasFormErrors(['company_name' => 'required', 'company_cuit']);
    }

    public function test_company_data_does_not_come_from_env(): void
    {
        putenv('COMPANY_NAME=Otra empresa');

        $this->assertSame('PEZ Servicios IT', Settings::get('company.name'));

        putenv('COMPANY_NAME');
    }
}
