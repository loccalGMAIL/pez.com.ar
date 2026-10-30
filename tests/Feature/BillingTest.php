<?php

namespace Tests\Feature;

use App\Enums\ChargeStatus;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function pay(Client $client, float $amount, array $chargeIds = []): Payment
    {
        return Payment::register(['client_id' => $client->id, 'paid_on' => today(), 'amount' => $amount, 'method' => 'efectivo'], $chargeIds);
    }

    public function test_charges_are_due_after_the_configured_days(): void
    {
        $charge = Charge::factory()->create(['issued_on' => '2026-11-01']);

        $this->assertSame('2026-11-11', $charge->due_on->toDateString());
        $this->assertSame(ChargeStatus::Pending, $charge->status);
    }

    public function test_a_payment_settles_the_oldest_charges_first(): void
    {
        $client = Client::factory()->create();
        $old = Charge::factory()->for($client)->create(['amount' => 1000, 'issued_on' => today()->subMonth()]);
        $new = Charge::factory()->for($client)->create(['amount' => 1000]);

        $this->pay($client, 1500);

        $this->assertSame(ChargeStatus::Paid, $old->refresh()->status);
        $this->assertSame(ChargeStatus::Partial, $new->refresh()->status);
        $this->assertSame(500.0, $new->outstanding());
        $this->assertSame(500.0, $client->balance());
    }

    public function test_a_payment_can_target_specific_charges(): void
    {
        $client = Client::factory()->create();
        $old = Charge::factory()->for($client)->create(['amount' => 1000, 'issued_on' => today()->subMonth()]);
        $new = Charge::factory()->for($client)->create(['amount' => 1000]);

        $this->pay($client, 1000, [$new->id]);

        $this->assertSame(ChargeStatus::Pending, $old->refresh()->status);
        $this->assertSame(ChargeStatus::Paid, $new->refresh()->status);
    }

    public function test_overpayments_become_credit_used_by_the_next_charge(): void
    {
        $client = Client::factory()->create();
        Charge::factory()->for($client)->create(['amount' => 1000]);

        $this->pay($client, 1500);
        $this->assertSame(500.0, $client->credit());
        $this->assertSame(-500.0, $client->balance());

        $next = Charge::factory()->for($client)->create(['amount' => 800]);

        $this->assertSame(500.0, $next->refresh()->paid);
        $this->assertSame(ChargeStatus::Partial, $next->status);
        $this->assertSame(0.0, $client->credit());
        $this->assertSame(300.0, $client->balance());
    }

    public function test_deleting_a_payment_reopens_its_charges(): void
    {
        $client = Client::factory()->create();
        $charge = Charge::factory()->for($client)->create(['amount' => 1000]);
        $payment = $this->pay($client, 1000);

        $payment->delete();

        $this->assertSame(ChargeStatus::Pending, $charge->refresh()->status);
        $this->assertSame(0.0, $charge->paid);
    }

    public function test_cancelling_a_charge_returns_its_payments_as_credit(): void
    {
        $client = Client::factory()->create();
        $charge = Charge::factory()->for($client)->create(['amount' => 1000]);
        $this->pay($client, 600);

        $charge->cancel();

        $this->assertSame(ChargeStatus::Cancelled, $charge->refresh()->status);
        $this->assertSame(600.0, $client->credit());
        $this->assertSame(-600.0, $client->balance());
    }

    public function test_drafts_are_not_owed_until_confirmed(): void
    {
        $client = Client::factory()->create();
        $this->pay($client, 1000);
        $draft = Charge::factory()->for($client)->create(['amount' => 700, 'status' => ChargeStatus::Draft]);

        $this->assertSame(0.0, $draft->refresh()->paid);
        $this->assertSame(-1000.0, $client->balance());

        $draft->confirm(900);

        $this->assertSame(ChargeStatus::Paid, $draft->refresh()->status);
        $this->assertSame(-100.0, $client->balance());
    }

    public function test_overdue_charges(): void
    {
        $overdue = Charge::factory()->create(['issued_on' => today()->subDays(20)]);
        $current = Charge::factory()->create();

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($current->isOverdue());
        $this->assertEquals([$overdue->id], Charge::overdue()->pluck('id')->all());
    }

    public function test_fixed_subscriptions_are_billed_once_a_month(): void
    {
        Carbon::setTestNow('2026-11-05');
        $subscription = Subscription::factory()->create(['billing_day' => 5, 'amount' => 80000]);
        Subscription::factory()->create(['billing_day' => 10]);
        Subscription::factory()->create(['billing_day' => 1, 'is_active' => false]);

        $this->artisan('subscriptions:bill')->expectsOutput('Cargos generados: 1')->assertSuccessful();
        $this->artisan('subscriptions:bill')->expectsOutput('Cargos generados: 0');

        $charge = $subscription->charges()->sole();
        $this->assertSame('Abono Mantenimiento mensual — Noviembre 2026', $charge->concept);
        $this->assertSame(ChargeStatus::Pending, $charge->status);
        $this->assertSame(80000.0, $charge->amount);
        $this->assertSame('2026-11-01', $charge->period->toDateString());
    }

    public function test_subscriptions_respect_start_and_end_dates(): void
    {
        Carbon::setTestNow('2026-11-15');
        Subscription::factory()->create(['starts_on' => '2026-12-01']);
        Subscription::factory()->create(['ends_on' => '2026-10-31']);
        $endingThisMonth = Subscription::factory()->create(['ends_on' => '2026-11-10']);

        $this->artisan('subscriptions:bill')->expectsOutput('Cargos generados: 1');
        $this->assertSame(1, $endingThisMonth->charges()->count());
    }

    public function test_variable_subscriptions_start_as_drafts_listing_last_months_work(): void
    {
        Carbon::setTestNow('2026-11-01');
        $subscription = Subscription::factory()->variable()->create();
        WorkOrder::factory()->for($subscription->client)->create(['title' => 'Configurar impresora', 'status' => WorkOrderStatus::Finished, 'hours' => 2])
            ->forceFill(['finished_at' => '2026-10-12 10:00'])->saveQuietly();
        WorkOrder::factory()->for($subscription->client)->create(['title' => 'Trabajo viejo', 'status' => WorkOrderStatus::Finished])
            ->forceFill(['finished_at' => '2026-08-01 10:00'])->saveQuietly();

        $charge = $subscription->bill(today());

        $this->assertSame(ChargeStatus::Draft, $charge->status);
        $this->assertStringContainsString('T-00001 Configurar impresora (2.00 h)', $charge->notes);
        $this->assertStringNotContainsString('Trabajo viejo', $charge->notes);
    }

    public function test_an_accepted_quote_is_charged_once(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Accepted)->create(['title' => 'Cámaras']);
        $quote->items()->create(['description' => 'Kit', 'quantity' => 1, 'unit_price' => 250000]);

        $charge = $quote->refresh()->charge();

        $this->assertSame('Presupuesto P-00100 — Cámaras', $charge->concept);
        $this->assertSame(250000.0, $charge->amount);
        $this->assertTrue($charge->is($quote->charge()));
    }
}
