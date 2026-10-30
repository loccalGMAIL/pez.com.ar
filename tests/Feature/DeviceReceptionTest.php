<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Filament\Resources\Devices\Pages\CreateDevice;
use App\Filament\Resources\WorkOrders\Pages\CreateWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrder;
use App\Models\Client;
use App\Models\Device;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DeviceReceptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAs(User::factory()->create());
    }

    public function test_creates_a_device_with_photos(): void
    {
        $client = Client::factory()->create();

        Livewire::test(CreateDevice::class)
            ->fillForm([
                'client_id' => $client->id,
                'type' => DeviceType::Notebook->value,
                'serial_number' => 'PF2ABC12',
                'photos' => [UploadedFile::fake()->image('frente.jpg'), UploadedFile::fake()->image('etiqueta.jpg')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $device = Device::sole();
        $this->assertCount(2, $device->photos);
        foreach ($device->photos as $path) {
            $this->assertStringStartsWith('devices/', $path);
            Storage::disk('local')->assertExists($path);
        }

        $this->get(\App\Filament\Resources\Devices\DeviceResource::getUrl('view', ['record' => $device]))->assertOk();
    }

    public function test_photos_are_optional(): void
    {
        Livewire::test(CreateDevice::class)
            ->fillForm([
                'client_id' => Client::factory()->create()->id,
                'type' => DeviceType::Printer->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEmpty(Device::sole()->photos);
    }

    public function test_rejects_files_that_are_not_images(): void
    {
        Livewire::test(CreateDevice::class)
            ->fillForm([
                'client_id' => Client::factory()->create()->id,
                'type' => DeviceType::Printer->value,
                'photos' => [UploadedFile::fake()->create('virus.exe', 10)],
            ])
            ->call('create')
            ->assertHasErrors();

        $this->assertSame(0, Device::count());
    }

    public function test_serial_number_has_a_camera_scanner_loaded_on_demand(): void
    {
        $src = FilamentAsset::getScriptSrc('serial-scanner');
        $this->assertStringContainsString('serial-scanner.js', $src);

        Livewire::test(CreateDevice::class)
            ->assertSee('pezScanSerial')
            ->assertSee('serial-scanner.js?v=');

        // Not loaded on every panel page: only fetched when the button is used.
        $this->get(\App\Filament\Resources\Devices\DeviceResource::getUrl('index'))
            ->assertDontSee('<script src="'.$src, escape: false);
    }

    public function test_registers_a_new_device_while_receiving_it_in_a_work_order(): void
    {
        $client = Client::factory()->create();

        Livewire::test(CreateWorkOrder::class)
            ->fillForm(['client_id' => $client->id, 'title' => 'No enciende'])
            ->callAction(TestAction::make('createOption')->schemaComponent('devices'), data: [
                'type' => DeviceType::Notebook->value,
                'name' => 'Notebook de Juan',
                'serial_number' => 'SN-998877',
            ])
            ->assertHasNoActionErrors()
            ->fillForm(['reception_photos' => [UploadedFile::fake()->image('golpe.jpg')]])
            ->call('create')
            ->assertHasNoFormErrors();

        $device = Device::sole();
        $this->assertSame($client->id, $device->client_id);

        $order = WorkOrder::sole();
        $this->assertTrue($order->devices->first()->is($device));
        $this->assertCount(1, $order->reception_photos);

        Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()])->assertOk();
    }
}
