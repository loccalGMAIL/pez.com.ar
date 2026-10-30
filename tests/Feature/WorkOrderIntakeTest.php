<?php

namespace Tests\Feature;

use App\Enums\WorkOrderStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\WorkOrders\Actions\WorkOrderActions;
use App\Filament\Resources\WorkOrders\Pages\CreateWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrder;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Client;
use App\Models\Device;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\WhatsApp;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class WorkOrderIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_has_a_button_that_starts_a_work_order(): void
    {
        Livewire::test(Dashboard::class)
            ->assertActionVisible('deviceIntake')
            ->assertActionHasUrl('deviceIntake', WorkOrderResource::getUrl('create'));
    }

    public function test_devices_live_in_the_workshop_group(): void
    {
        $this->assertSame('Taller', DeviceResource::getNavigationGroup());
    }

    public function test_creation_form_has_no_status_nor_resolution_data(): void
    {
        Livewire::test(CreateWorkOrder::class)
            ->assertFormFieldIsHidden('status')
            ->assertFormFieldIsHidden('opened_on')
            ->assertFormFieldIsHidden('hours')
            ->assertFormFieldIsHidden('resolution');
    }

    public function test_order_is_opened_today_and_pending(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5));

        Livewire::test(CreateWorkOrder::class)
            ->fillForm(['client_id' => Client::factory()->create()->id, 'title' => 'No enciende'])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = WorkOrder::sole();
        $this->assertSame('2026-11-05', $order->opened_on->toDateString());
        $this->assertSame(WorkOrderStatus::Pending, $order->status);
    }

    public function test_registers_a_new_client_while_creating_the_order(): void
    {
        Livewire::test(CreateWorkOrder::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('client_id'), data: [
                'business_name' => 'Juan Pérez',
                'phone' => '011 2345-6789',
            ])
            ->assertHasNoActionErrors()
            ->fillForm(['title' => 'No enciende'])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::where('business_name', 'Juan Pérez')->sole();
        $this->assertSame($client->id, WorkOrder::sole()->client_id);
    }

    public function test_intake_slip_is_served_through_a_signed_link_only(): void
    {
        $order = WorkOrder::factory()->create();
        $order->devices()->attach(Device::factory()->for($order->client)->create());

        $this->get(route('work-orders.pdf', $order))->assertForbidden();

        $response = $this->get($order->publicPdfUrl())->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_shares_the_summary_on_whatsapp(): void
    {
        $client = Client::factory()->create(['phone' => '011 2345-6789']);
        $order = WorkOrder::factory()->for($client)->create(['title' => 'No enciende']);
        $order->devices()->attach(Device::factory()->for($client)->create(['name' => 'Notebook de Juan']));

        $summary = WorkOrderActions::summary($order->load('devices'));

        $this->assertStringContainsString($order->code, $summary);
        $this->assertStringContainsString('Notebook de Juan', $summary);
        $this->assertStringContainsString('No enciende', $summary);
        $this->assertStringContainsString(URL::signedRoute('work-orders.pdf', $order), $summary);

        Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('shareOnWhatsApp')
            ->assertActionHasUrl('shareOnWhatsApp', WhatsApp::url($client->whatsappPhone(), $summary));
    }
}
