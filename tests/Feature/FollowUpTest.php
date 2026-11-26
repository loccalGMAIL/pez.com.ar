<?php

namespace Tests\Feature;

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Pages\Settings as SettingsPage;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\FollowUpsRelationManager;
use App\Filament\Widgets\DueFollowUps;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\FollowUp;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Settings;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FollowUpTest extends TestCase
{
    use RefreshDatabase;

    private function deliver(WorkOrder $order): void
    {
        $order->update(['status' => WorkOrderStatus::Delivered]);
    }

    private function acceptedQuote(Client $client): Quote
    {
        $quote = Quote::factory()->for($client)->status(QuoteStatus::Accepted)->create([
            'installment_plans' => [['count' => 3, 'surcharge' => 0]],
        ]);
        $quote->items()->create(['description' => 'Notebook', 'quantity' => 1, 'unit_price' => 900000]);

        return $quote->refresh();
    }

    public function test_delivering_a_job_schedules_a_follow_up_seven_business_days_later(): void
    {
        $this->travelTo('2026-11-20 10:00'); // Friday
        $order = WorkOrder::factory()->status(WorkOrderStatus::InProgress)->create();

        $this->deliver($order);

        $followUp = $order->followUps()->sole();
        $this->assertSame(FollowUpKind::WorkOrder, $followUp->kind);
        $this->assertSame(FollowUpStatus::Pending, $followUp->status);
        $this->assertSame('2026-11-20', $followUp->occurred_on->toDateString());
        $this->assertSame('2026-12-01', $followUp->due_on->toDateString());
    }

    public function test_the_waiting_period_comes_from_the_settings(): void
    {
        $this->travelTo('2026-11-20 10:00'); // Friday
        Settings::set(['follow_ups.business_days' => 2]);
        $order = WorkOrder::factory()->create();

        $this->deliver($order);

        $this->assertSame('2026-11-24', $order->followUps()->sole()->due_on->toDateString());
    }

    public function test_reopening_a_delivered_job_cancels_its_pending_follow_up(): void
    {
        $order = WorkOrder::factory()->create();
        $this->deliver($order);

        $order->update(['status' => WorkOrderStatus::InProgress]);

        $this->assertSame(FollowUpStatus::Cancelled, $order->followUps()->sole()->status);
    }

    public function test_clients_with_an_active_subscription_get_no_follow_up(): void
    {
        $client = Client::factory()->create();
        Subscription::factory()->for($client)->create();
        $order = WorkOrder::factory()->for($client)->create();

        $this->deliver($order);

        $this->assertDatabaseEmpty('follow_ups');
    }

    public function test_an_ended_subscription_does_not_exclude_the_client(): void
    {
        $client = Client::factory()->create();
        Subscription::factory()->for($client)->create(['ends_on' => today()->subMonth()]);
        $order = WorkOrder::factory()->for($client)->create();

        $this->deliver($order);

        $this->assertSame(1, $order->followUps()->count());
    }

    public function test_clients_that_opted_out_get_no_follow_up(): void
    {
        $order = WorkOrder::factory()->for(Client::factory()->create(['skip_follow_ups' => true]))->create();

        $this->deliver($order);

        $this->assertDatabaseEmpty('follow_ups');
    }

    public function test_nothing_is_scheduled_while_follow_ups_are_turned_off(): void
    {
        Settings::set(['follow_ups.enabled' => false]);
        $order = WorkOrder::factory()->create();

        $this->deliver($order);

        $this->assertDatabaseEmpty('follow_ups');
    }

    public function test_an_accepted_quote_in_installments_gets_a_single_sale_follow_up(): void
    {
        $quote = $this->acceptedQuote(Client::factory()->create());

        $quote->charge(3);

        $followUp = FollowUp::sole();
        $this->assertSame(FollowUpKind::Sale, $followUp->kind);
        $this->assertSame($quote->id, $followUp->quote_id);
    }

    public function test_a_quote_turned_into_a_job_is_followed_up_when_the_job_is_delivered(): void
    {
        $quote = $this->acceptedQuote(Client::factory()->create());
        $quote->charge();

        $order = $quote->convertToWorkOrder();
        $this->deliver($order);

        $this->assertSame(FollowUpStatus::Cancelled, $quote->followUps()->sole()->status);
        $this->assertSame(FollowUpStatus::Pending, $order->followUps()->sole()->status);
    }

    public function test_charging_a_quote_that_already_has_a_job_schedules_no_sale_follow_up(): void
    {
        $quote = $this->acceptedQuote(Client::factory()->create());
        $quote->convertToWorkOrder();

        $quote->charge();

        $this->assertDatabaseEmpty('follow_ups');
    }

    public function test_a_resale_charge_is_followed_up_as_a_sale(): void
    {
        $charge = Charge::factory()->resale()->create(['concept' => 'Impresora HP']);

        $this->assertSame('Venta: Impresora HP', $charge->followUps()->sole()->subject());
    }

    /**
     * @return array<string, array{Closure(): array<string, mixed>}>
     */
    public static function chargesThatAreNotSales(): array
    {
        return [
            'service' => [fn () => []],
            'subscription' => [fn () => ['is_resale' => true, 'subscription_id' => Subscription::factory()->create()->id]],
            'work order' => [fn () => ['is_resale' => true, 'work_order_id' => WorkOrder::factory()->create()->id]],
        ];
    }

    /**
     * @param  Closure(): array<string, mixed>  $attributes
     */
    #[DataProvider('chargesThatAreNotSales')]
    public function test_charges_that_are_not_sales_get_no_follow_up(Closure $attributes): void
    {
        Charge::factory()->create($attributes());

        $this->assertSame(0, FollowUp::where('kind', FollowUpKind::Sale)->count());
    }

    public function test_cancelling_a_sale_cancels_its_follow_up(): void
    {
        $charge = Charge::factory()->resale()->create();

        $charge->cancel();

        $this->assertSame(FollowUpStatus::Cancelled, $charge->followUps()->sole()->status);
    }

    public function test_a_quote_follow_up_stands_while_one_of_its_charges_does(): void
    {
        $quote = $this->acceptedQuote(Client::factory()->create());
        $installments = $quote->charge(3);

        $installments->first()->cancel();
        $this->assertSame(FollowUpStatus::Pending, $quote->followUps()->sole()->status);

        $installments->skip(1)->each->cancel();
        $this->assertSame(FollowUpStatus::Cancelled, $quote->followUps()->sole()->status);
    }

    public function test_a_client_gets_one_follow_up_per_waiting_period(): void
    {
        $this->travelTo('2026-11-20 10:00');
        $client = Client::factory()->create();
        $this->deliver(WorkOrder::factory()->for($client)->create());

        // A sale the same week while the first one is pending.
        Charge::factory()->for($client)->resale()->create();
        $this->assertSame(1, $client->followUps()->count());

        // Sent, then another job a few days later: still within the period.
        $client->followUps()->sole()->markAsSent();
        $this->travelTo('2026-11-25 10:00');
        $this->deliver(WorkOrder::factory()->for($client)->create());
        $this->assertSame(1, $client->followUps()->count());

        // A full period after the last one, a new job gets its own.
        $this->travelTo('2026-12-02 10:00');
        $this->deliver(WorkOrder::factory()->for($client)->create());
        $this->assertSame(2, $client->followUps()->count());
    }

    public function test_sending_marks_it_as_sent_and_opens_whatsapp_with_the_message(): void
    {
        $client = Client::factory()->create(['phone' => '011 2345-6789']);
        ClientContact::factory()->for($client)->primary()->create(['name' => 'Juan Pérez']);
        Settings::set(['follow_ups.work_order_message' => 'Hola {nombre}!! Cómo anda el equipo?']);
        $followUp = FollowUp::factory()->for($client)->create();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('filament.admin.follow-ups.whatsapp', $followUp));

        $response->assertRedirect('https://wa.me/5491123456789?text='.rawurlencode('Hola Juan!! Cómo anda el equipo?'));
        $followUp->refresh();
        $this->assertSame(FollowUpStatus::Sent, $followUp->status);
        $this->assertNotNull($followUp->sent_at);
        $this->assertSame('Hola Juan!! Cómo anda el equipo?', $followUp->message);
    }

    public function test_sending_requires_login(): void
    {
        $followUp = FollowUp::factory()->create();

        $this->get(route('filament.admin.follow-ups.whatsapp', $followUp))->assertRedirect();

        $this->assertSame(FollowUpStatus::Pending, $followUp->refresh()->status);
    }

    public function test_a_cancelled_follow_up_cannot_be_sent(): void
    {
        $followUp = FollowUp::factory()->status(FollowUpStatus::Cancelled)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('filament.admin.follow-ups.whatsapp', $followUp))
            ->assertNotFound();
    }

    public function test_the_dashboard_lists_the_follow_ups_due_today(): void
    {
        $this->actingAs(User::factory()->create());
        $due = FollowUp::factory()->for(Client::factory()->create(['business_name' => 'Debido SA']))->create();
        $later = FollowUp::factory()->for(Client::factory()->create(['business_name' => 'Futuro SA']))->create(['due_on' => today()->addDay()]);
        $sent = FollowUp::factory()->for(Client::factory()->create(['business_name' => 'Enviado SA']))->status(FollowUpStatus::Sent)->create();
        $subscribed = FollowUp::factory()->for(Client::factory()->create(['business_name' => 'Abonado SA']))->create();
        Subscription::factory()->for($subscribed->client)->create();

        Livewire::test(DueFollowUps::class)
            ->assertCanSeeTableRecords([$due])
            ->assertCanNotSeeTableRecords([$later, $sent, $subscribed]);
    }

    public function test_postponing_moves_it_to_another_day(): void
    {
        $this->actingAs(User::factory()->create());
        $followUp = FollowUp::factory()->create();
        $date = today()->addWeek()->toDateString();

        Livewire::test(DueFollowUps::class)
            ->callTableAction('postponeFollowUp', $followUp, data: ['due_on' => $date])
            ->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$followUp]);

        $this->assertSame($date, $followUp->refresh()->due_on->toDateString());
    }

    public function test_skipping_removes_it_from_the_list(): void
    {
        $this->actingAs(User::factory()->create());
        $followUp = FollowUp::factory()->create();

        Livewire::test(DueFollowUps::class)
            ->callTableAction('skipFollowUp', $followUp)
            ->assertCanNotSeeTableRecords([$followUp]);

        $this->assertSame(FollowUpStatus::Skipped, $followUp->refresh()->status);
    }

    public function test_the_client_page_shows_its_follow_up_history(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create();
        $sent = FollowUp::factory()->for($client)->status(FollowUpStatus::Sent)->create();
        $cancelled = FollowUp::factory()->for($client)->status(FollowUpStatus::Cancelled)->create();
        $other = FollowUp::factory()->create();

        Livewire::test(FollowUpsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->assertCanSeeTableRecords([$sent, $cancelled])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableActionHasLabel('sendFollowUp', 'Abrir de nuevo en WhatsApp', $sent)
            ->assertTableActionHidden('sendFollowUp', $cancelled);
    }

    public function test_saves_the_follow_up_settings(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SettingsPage::class)
            ->fillForm([
                'follow_ups_enabled' => false,
                'follow_ups_business_days' => '5',
                'follow_ups_notify_at' => '08:45',
                'follow_ups_sale_message' => 'Hola {nombre}, cómo anda lo que compraste?',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(Settings::get('follow_ups.enabled'));
        $this->assertSame(5, Settings::get('follow_ups.business_days'));
        $this->assertSame('Hola {nombre}, cómo anda lo que compraste?', Settings::get('follow_ups.sale_message'));
        $this->assertSame('08:45', Settings::get('follow_ups.notify_at'));
    }

    public function test_clearing_the_notice_hour_turns_it_off_instead_of_restoring_the_default(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SettingsPage::class)
            ->fillForm(['follow_ups_notify_at' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('', Settings::get('follow_ups.notify_at'));
    }
}
