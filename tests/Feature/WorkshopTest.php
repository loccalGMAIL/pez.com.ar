<?php

namespace Tests\Feature;

use App\Enums\ExternalRepairStatus;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Device;
use App\Models\ExternalRepair;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_orders_are_numbered_from_t_00001(): void
    {
        $this->assertSame('T-00001', WorkOrder::factory()->create()->code);
        $this->assertSame('T-00002', WorkOrder::factory()->create()->code);
    }

    public function test_finishing_and_delivering_record_their_dates(): void
    {
        $order = WorkOrder::factory()->create();

        $order->update(['status' => WorkOrderStatus::Finished]);
        $this->assertNotNull($order->finished_at);
        $this->assertNull($order->delivered_at);

        $order->update(['status' => WorkOrderStatus::Delivered]);
        $this->assertNotNull($order->delivered_at);

        $order->update(['status' => WorkOrderStatus::InProgress]);
        $this->assertNull($order->finished_at);
        $this->assertNull($order->delivered_at);
    }

    public function test_sending_a_device_out_puts_the_order_on_hold_until_it_comes_back(): void
    {
        $order = WorkOrder::factory()->status(WorkOrderStatus::InProgress)->create();

        $first = ExternalRepair::factory()->for($order)->create();
        $second = ExternalRepair::factory()->for($order)->create();
        $this->assertSame(WorkOrderStatus::AwaitingExternal, $order->refresh()->status);

        $first->update(['status' => ExternalRepairStatus::PickedUp]);
        $this->assertSame(today()->toDateString(), $first->returned_on->toDateString());
        $this->assertSame(WorkOrderStatus::AwaitingExternal, $order->refresh()->status);

        $second->update(['status' => ExternalRepairStatus::NotRepaired]);
        $this->assertSame(WorkOrderStatus::InProgress, $order->refresh()->status);
    }

    public function test_external_repairs_do_not_reopen_finished_orders(): void
    {
        $order = WorkOrder::factory()->status(WorkOrderStatus::Finished)->create();

        ExternalRepair::factory()->for($order)->create();

        $this->assertSame(WorkOrderStatus::Finished, $order->refresh()->status);
    }

    public function test_device_history_spans_work_orders_and_external_repairs(): void
    {
        $device = Device::factory()->create();
        $orders = WorkOrder::factory()->count(2)->for($device->client)->create();
        $orders->each(fn (WorkOrder $order) => $order->devices()->attach($device));
        ExternalRepair::factory()->for($orders[0])->create(['device_id' => $device->id]);

        $this->assertCount(2, $device->workOrders);
        $this->assertCount(1, $device->externalRepairs);
    }

    public function test_device_label_combines_its_identification(): void
    {
        $device = Device::factory()->make(['name' => 'PC Recepción', 'brand' => 'Dell', 'model' => 'OptiPlex 3080', 'serial_number' => 'ABC123']);

        $this->assertSame('PC Recepción — Dell OptiPlex 3080 (S/N ABC123)', $device->label);
    }

    public function test_an_accepted_quote_becomes_a_single_work_order(): void
    {
        $quote = Quote::factory()->status(QuoteStatus::Accepted)->create();
        $quote->items()->create(['description' => 'Instalar cámaras', 'quantity' => 4, 'unit' => 'unidad', 'unit_price' => 1000]);

        $order = $quote->refresh()->convertToWorkOrder();

        $this->assertSame($quote->client_id, $order->client_id);
        $this->assertSame($quote->id, $order->quote_id);
        $this->assertSame('Presupuesto P-00100', $order->title);
        $this->assertSame('- 4 unidad: Instalar cámaras', $order->description);
        $this->assertTrue($order->is($quote->convertToWorkOrder()));
        $this->assertSame(1, WorkOrder::count());
    }
}
