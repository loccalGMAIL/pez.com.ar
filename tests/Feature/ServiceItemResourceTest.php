<?php

namespace Tests\Feature;

use App\Enums\ServiceUnit;
use App\Filament\Resources\ServiceItems\Pages\CreateServiceItem;
use App\Filament\Resources\ServiceItems\Pages\EditServiceItem;
use App\Filament\Resources\ServiceItems\Pages\ListServiceItems;
use App\Models\ServiceItem;
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
