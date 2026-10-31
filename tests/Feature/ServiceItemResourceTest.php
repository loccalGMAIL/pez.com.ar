<?php

namespace Tests\Feature;

use App\Enums\CatalogItemType;
use App\Enums\ProductCondition;
use App\Enums\ServiceUnit;
use App\Filament\Resources\ServiceItems\Pages\CreateServiceItem;
use App\Filament\Resources\ServiceItems\Pages\EditServiceItem;
use App\Filament\Resources\ServiceItems\Pages\ListServiceItems;
use App\Models\ServiceItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceItemResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lists_service_items(): void
    {
        $items = ServiceItem::factory()->count(3)->create();

        Livewire::test(ListServiceItems::class)
            ->assertCanSeeTableRecords($items);
    }

    public function test_services_and_products_are_listed_in_separate_tabs(): void
    {
        $service = ServiceItem::factory()->create();
        $new = ServiceItem::factory()->product()->create();
        $resale = ServiceItem::factory()->product(ProductCondition::Resale)->create();

        Livewire::test(ListServiceItems::class)
            ->assertSet('activeTab', 'servicio')
            ->assertCanSeeTableRecords([$service])
            ->assertCanNotSeeTableRecords([$new, $resale])
            ->set('activeTab', 'producto')
            ->assertCanSeeTableRecords([$new, $resale])
            ->assertCanNotSeeTableRecords([$service])
            ->filterTable('condition', ProductCondition::Resale->value)
            ->assertCanSeeTableRecords([$resale])
            ->assertCanNotSeeTableRecords([$new]);
    }

    public function test_a_product_needs_its_condition(): void
    {
        $data = ['type' => CatalogItemType::Product->value, 'name' => 'Notebook', 'unit' => ServiceUnit::Unit->value, 'price' => 900000];

        Livewire::test(CreateServiceItem::class)
            ->fillForm($data + ['condition' => null])
            ->call('create')
            ->assertHasFormErrors(['condition' => 'required']);

        Livewire::test(CreateServiceItem::class)
            ->fillForm($data + ['condition' => ProductCondition::Resale->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('service_items', ['name' => 'Notebook', 'type' => 'producto', 'condition' => 'reventa']);
    }

    public function test_a_product_keeps_its_cost_supplier_and_notes(): void
    {
        $supplier = Supplier::factory()->create();

        Livewire::test(CreateServiceItem::class)
            ->fillForm([
                'type' => CatalogItemType::Product->value,
                'condition' => ProductCondition::New->value,
                'name' => 'SSD 480 GB',
                'unit' => ServiceUnit::Unit->value,
                'price' => 65000,
                'cost' => 50000,
                'supplier_id' => $supplier->id,
                'notes' => 'https://proveedor.example/ssd-480',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = ServiceItem::sole();
        $this->assertSame(50000.0, $item->cost);
        $this->assertSame($supplier->id, $item->supplier_id);
        $this->assertSame('https://proveedor.example/ssd-480', $item->notes);
        $this->assertDatabaseHas('service_items', ['name' => 'SSD 480 GB', 'cost' => 5000000]);
    }

    public function test_a_service_has_no_condition(): void
    {
        Livewire::test(CreateServiceItem::class)
            ->fillForm(['type' => CatalogItemType::Service->value, 'name' => 'Visita', 'unit' => ServiceUnit::Hour->value, 'price' => 1000])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(ServiceItem::sole()->condition);
        $this->assertSame(CatalogItemType::Service, ServiceItem::sole()->type);
    }

    public function test_creates_a_service_item_storing_price_in_cents(): void
    {
        Livewire::test(CreateServiceItem::class)
            ->fillForm([
                'name' => 'Hora de soporte técnico',
                'unit' => ServiceUnit::Hour->value,
                'price' => '25000.50',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('service_items', [
            'name' => 'Hora de soporte técnico',
            'unit' => 'hora',
            'price' => 2500050,
        ]);

        $this->assertSame(25000.5, ServiceItem::first()->price);
    }

    public function test_rejects_negative_prices(): void
    {
        Livewire::test(CreateServiceItem::class)
            ->fillForm([
                'name' => 'Servicio',
                'unit' => ServiceUnit::Unit->value,
                'price' => -1,
            ])
            ->call('create')
            ->assertHasFormErrors(['price']);
    }

    public function test_edits_a_service_item(): void
    {
        $item = ServiceItem::factory()->create(['price' => 1000]);

        Livewire::test(EditServiceItem::class, ['record' => $item->getRouteKey()])
            ->assertSchemaStateSet(['price' => 1000])
            ->fillForm(['price' => 1500])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1500.0, $item->refresh()->price);
    }
}
