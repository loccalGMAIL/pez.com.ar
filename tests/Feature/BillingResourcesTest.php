<?php

namespace Tests\Feature;

use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\QuoteStatus;
use App\Enums\SubscriptionType;
use App\Filament\Pages\Settings as SettingsPage;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Charges\Pages\CreateCharge;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Charges\Pages\ViewCharge;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Quotes\Pages\ViewQuote;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrder;
use App\Filament\Widgets\BusinessOverview;
use App\Filament\Widgets\OverdueCharges;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ExternalRepair;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_creates_a_subscription_from_the_client_page(): void
    {
        $client = Client::factory()->create();

        Livewire::test(SubscriptionsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->callTableAction('create', data: [
                'concept' => 'Soporte mensual',
                'type' => SubscriptionType::Variable->value,
                'amount' => 120000,
                'billing_day' => 5,
            ])
            ->assertHasNoTableActionErrors();

        $subscription = $client->subscriptions()->sole();
        $this->assertSame(SubscriptionType::Variable, $subscription->type);
        $this->assertSame(120000.0, $subscription->amount);
    }

    public function test_creates_a_manual_charge(): void
    {
        $client = Client::factory()->create();

        Livewire::test(CreateCharge::class)
            ->fillForm(['client_id' => $client->id, 'concept' => 'Visita técnica', 'amount' => 35000])
            ->call('create')
            ->assertHasNoFormErrors();

        $charge = Charge::sole();
        $this->assertSame(ChargeStatus::Pending, $charge->status);
        $this->assertSame(today()->addDays(10)->toDateString(), $charge->due_on->toDateString());
    }

    public function test_a_resale_charge_without_cost_is_pending_until_the_cost_is_loaded(): void
    {
        $client = Client::factory()->create();
        $supplier = Supplier::factory()->individual()->create();

        Livewire::test(CreateCharge::class)
            ->fillForm(['client_id' => $client->id, 'concept' => 'Notebook usada', 'amount' => 900000, 'is_resale' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $charge = Charge::sole();
        $this->assertNull($charge->cost);

        Livewire::test(ListCharges::class)
            ->filterTable('cost_pending')
            ->assertCanSeeTableRecords([$charge]);

        $charge->update(['cost' => 600000, 'supplier_id' => $supplier->id]);

        $this->assertSame(300000.0, $charge->refresh()->margin());
        Livewire::test(ListCharges::class)
            ->filterTable('cost_pending')
            ->assertCanNotSeeTableRecords([$charge]);
    }

    public function test_cancelled_charges_are_not_cost_pending(): void
    {
        $charge = Charge::factory()->resale()->create(['status' => ChargeStatus::Cancelled]);

        $this->assertFalse(Charge::costPending()->whereKey($charge->id)->exists());
    }

    public function test_registers_a_payment_choosing_the_charges(): void
    {
        $client = Client::factory()->create();
        $old = Charge::factory()->for($client)->create(['amount' => 1000, 'issued_on' => today()->subMonth()]);
        $new = Charge::factory()->for($client)->create(['amount' => 2000]);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'client_id' => $client->id,
                'amount' => 2000,
                'method' => PaymentMethod::Transfer->value,
                'charge_ids' => [$new->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(ChargeStatus::Pending, $old->refresh()->status);
        $this->assertSame(ChargeStatus::Paid, $new->refresh()->status);
    }

    public function test_registers_a_payment_from_the_client_page(): void
    {
        $client = Client::factory()->create();
        $charge = Charge::factory()->for($client)->create(['amount' => 1500]);

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->mountAction('registerPayment')
            ->assertActionDataSet(['amount' => 1500.0])
            ->setActionData(['method' => PaymentMethod::Cash->value])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(ChargeStatus::Paid, $charge->refresh()->status);
        $this->assertSame(0.0, $client->balance());
    }

    public function test_pays_a_charge_from_its_row(): void
    {
        $charge = Charge::factory()->create(['amount' => 1000]);

        Livewire::test(ListCharges::class)
            ->callTableAction('registerPayment', $charge, data: ['amount' => 400, 'method' => PaymentMethod::Cash->value])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ChargeStatus::Partial, $charge->refresh()->status);
        $this->assertSame(600.0, $charge->outstanding());
    }

    public function test_charges_list_shortens_the_concept_of_subscriptions_and_work_orders(): void
    {
        $subscription = Subscription::factory()->create(['concept' => 'Sistema VERIFICADOR PRO']);
        $subscriptionCharge = Charge::factory()->create([
            'client_id' => $subscription->client_id,
            'subscription_id' => $subscription->id,
            'period' => '2026-10-01',
            'concept' => 'Abono Sistema VERIFICADOR PRO — Octubre 2026',
        ]);
        $workOrder = WorkOrder::factory()->create();
        $workOrderCharge = Charge::factory()->create([
            'client_id' => $workOrder->client_id,
            'work_order_id' => $workOrder->id,
            'concept' => "Orden de trabajo {$workOrder->code} — Reparación larga",
        ]);

        Livewire::test(ListCharges::class)
            ->assertTableColumnStateSet('concept', 'Abono — Octubre 2026', $subscriptionCharge)
            ->assertTableColumnStateSet('concept', 'OT '.str_pad((string) $workOrder->number, 5, '0', STR_PAD_LEFT), $workOrderCharge);
    }

    public function test_confirms_a_variable_subscription_draft_with_another_amount(): void
    {
        $charge = Charge::factory()->create(['amount' => 1000, 'status' => ChargeStatus::Draft]);

        Livewire::test(ViewCharge::class, ['record' => $charge->getRouteKey()])
            ->assertActionHidden('registerPayment')
            ->callAction('confirm', data: ['amount' => 1350])
            ->assertHasNoActionErrors();

        $this->assertSame(ChargeStatus::Pending, $charge->refresh()->status);
        $this->assertSame(1350.0, $charge->amount);
    }

    public function test_cancels_a_charge(): void
    {
        $charge = Charge::factory()->create();

        Livewire::test(ViewCharge::class, ['record' => $charge->getRouteKey()])
            ->callAction('cancel');

        $this->assertSame(ChargeStatus::Cancelled, $charge->refresh()->status);
    }

    public function test_paid_charges_can_not_be_edited_and_none_deleted(): void
    {
        $charge = Charge::factory()->create(['amount' => 1000]);
        $this->assertTrue(ChargeResource::canEdit($charge));

        Payment::register(['client_id' => $charge->client_id, 'paid_on' => today(), 'amount' => 100, 'method' => 'efectivo']);

        $this->assertFalse(ChargeResource::canEdit($charge->refresh()));
        $this->assertFalse(ChargeResource::canDelete($charge));
    }

    public function test_charges_an_accepted_quote(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Accepted)->withItems()->create();

        Livewire::test(ViewQuote::class, ['record' => $quote->getRouteKey()])
            ->callAction('charge')
            ->assertRedirect(ChargeResource::getUrl('view', ['record' => Charge::sole()]));

        $this->assertSame($quote->refresh()->total, Charge::sole()->amount);
    }

    public function test_charges_a_work_order_proposing_the_external_service_price(): void
    {
        $order = WorkOrder::factory()->create(['title' => 'Impresora']);
        ExternalRepair::factory()->for($order)->create(['price' => 18000]);

        Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()])
            ->mountAction('charge')
            ->assertActionDataSet(['amount' => 18000.0, 'concept' => 'Orden de trabajo T-00001 — Impresora'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $charge = $order->charges()->sole();
        $this->assertSame($order->client_id, $charge->client_id);
        $this->assertSame(18000.0, $charge->amount);
    }

    public function test_due_days_are_set_in_the_settings_page(): void
    {
        Livewire::test(SettingsPage::class)
            ->fillForm(['billing_due_days' => 15])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(15, Settings::get('billing.due_days'));
        $this->assertSame(today()->addDays(15)->toDateString(), Charge::factory()->create()->due_on->toDateString());
    }

    public function test_dashboard_shows_what_is_owed_and_overdue(): void
    {
        Charge::factory()->create(['amount' => 1000, 'issued_on' => today()->subDays(30), 'concept' => 'Cargo atrasado']);
        Charge::factory()->create(['amount' => 500]);

        Livewire::test(BusinessOverview::class)
            ->assertSee('$ 1.500,00')
            ->assertSee('Vencido: $ 1.000,00');

        Livewire::test(OverdueCharges::class)
            ->assertSee('Cargo atrasado');

        $this->get('http://'.config('app.admin_domain').'/')->assertOk();
    }

    public function test_pages_render(): void
    {
        $client = Client::factory()->create();
        $charge = Charge::factory()->for($client)->create();
        $payment = Payment::register(['client_id' => $client->id, 'paid_on' => today(), 'amount' => 100, 'method' => 'efectivo']);
        Subscription::factory()->for($client)->create();

        foreach ([
            ChargeResource::getUrl('index'),
            ChargeResource::getUrl('create'),
            ChargeResource::getUrl('view', ['record' => $charge]),
            ChargeResource::getUrl('edit', ['record' => Charge::factory()->create()]),
            PaymentResource::getUrl('index'),
            PaymentResource::getUrl('create'),
            PaymentResource::getUrl('view', ['record' => $payment]),
            PaymentResource::getUrl('edit', ['record' => $payment]),
            SubscriptionResource::getUrl('index'),
            SubscriptionResource::getUrl('create'),
            ClientResource::getUrl('view', ['record' => $client]),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
