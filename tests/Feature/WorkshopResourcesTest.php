<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\ExternalRepairs\ExternalRepairResource;
use App\Filament\Resources\ExternalRepairs\Pages\ManageExternalRepairs;
use App\Filament\Resources\Quotes\Pages\ViewQuote;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\WorkOrders\Pages\CreateWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\ListWorkOrders;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrder;
use App\Filament\Resources\WorkOrders\RelationManagers\ExternalRepairsRelationManager;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Client;
use App\Models\Device;
use App\Models\ExternalRepair;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkshopResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_adds_devices_from_the_client_page(): void
    {
        $client = Client::factory()->create();

        Livewire::test(DevicesRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->callTableAction('create', data: [
                'type' => DeviceType::Notebook->value,
                'name' => 'Notebook Gerencia',
                'brand' => 'Lenovo',
                'serial_number' => 'PF123',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Notebook Gerencia', $client->devices()->sole()->name);
    }

    public function test_creates_a_work_order_with_the_client_devices(): void
    {
        $client = Client::factory()->create();
        $device = Device::factory()->for($client)->create();

        Livewire::test(CreateWorkOrder::class)
            ->fillForm([
                'client_id' => $client->id,
                'title' => 'No enciende',
                'devices' => [$device->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = WorkOrder::sole();
        $this->assertSame('T-00001', $order->code);
        $this->assertSame(WorkOrderStatus::Pending, $order->status);
        $this->assertTrue($order->devices->first()->is($device));
    }

    public function test_lists_open_work_orders_by_default(): void
    {
        $open = WorkOrder::factory()->status(WorkOrderStatus::InProgress)->create();
        $delivered = WorkOrder::factory()->status(WorkOrderStatus::Delivered)->create();

        Livewire::test(ListWorkOrders::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$delivered]);
    }

    public function test_changes_the_status_from_the_view_page(): void
    {
        $order = WorkOrder::factory()->create();

        Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('changeStatus', data: ['status' => WorkOrderStatus::Finished->value])
            ->assertHasNoActionErrors();

        $this->assertSame(WorkOrderStatus::Finished, $order->refresh()->status);
    }

    public function test_sends_a_device_to_an_external_service_from_the_work_order(): void
    {
        $order = WorkOrder::factory()->status(WorkOrderStatus::InProgress)->create();
        $device = Device::factory()->for($order->client)->create();
        $order->devices()->attach($device);
        $supplier = Supplier::factory()->create();

        Livewire::test(ExternalRepairsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditWorkOrder::class])
            ->callTableAction('create', data: [
                'supplier_id' => $supplier->id,
                'device_id' => $device->id,
                'problem' => 'Fuente quemada',
                'cost' => 15000,
            ])
            ->assertHasNoTableActionErrors();

        $repair = ExternalRepair::sole();
        $this->assertTrue($repair->device->is($device));
        $this->assertSame(15000.0, $repair->cost);
        $this->assertSame(WorkOrderStatus::AwaitingExternal, $order->refresh()->status);
    }

    public function test_external_service_list_shows_what_is_still_out(): void
    {
        $out = ExternalRepair::factory()->create();
        $back = ExternalRepair::factory()->create(['status' => 'retirado']);

        Livewire::test(ManageExternalRepairs::class)
            ->assertCanSeeTableRecords([$out])
            ->assertCanNotSeeTableRecords([$back]);
    }

    public function test_manages_suppliers(): void
    {
        Livewire::test(ManageSuppliers::class)
            ->callAction('create', data: ['name' => 'Service Impresoras Sur'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('suppliers', ['name' => 'Service Impresoras Sur']);
    }

    public function test_converts_an_accepted_quote_from_its_page(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Accepted)->withItems()->create();

        Livewire::test(ViewQuote::class, ['record' => $quote->getRouteKey()])
            ->callAction('convertToWorkOrder')
            ->assertRedirect(WorkOrderResource::getUrl('view', ['record' => WorkOrder::sole()]));
    }

    public function test_pages_render(): void
    {
        $order = WorkOrder::factory()->create();
        $device = Device::factory()->for($order->client)->create();
        $order->devices()->attach($device);
        ExternalRepair::factory()->for($order)->create(['device_id' => $device->id]);

        foreach ([
            WorkOrderResource::getUrl('index'),
            WorkOrderResource::getUrl('create'),
            WorkOrderResource::getUrl('view', ['record' => $order]),
            WorkOrderResource::getUrl('edit', ['record' => $order]),
            DeviceResource::getUrl('index'),
            DeviceResource::getUrl('view', ['record' => $device]),
            ExternalRepairResource::getUrl('index'),
            SupplierResource::getUrl('index'),
            \App\Filament\Resources\Clients\ClientResource::getUrl('view', ['record' => $order->client]),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
