<?php

namespace Tests\Feature;

use App\Enums\TaxCondition;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\ContactsRelationManager;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lists_and_searches_clients(): void
    {
        $clients = Client::factory()->count(3)->create();
        $target = Client::factory()->create(['business_name' => 'Ferretería Los Pinos SRL']);

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords($clients->push($target))
            ->searchTable('Los Pinos')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords($clients->take(3));
    }

    public function test_searches_clients_by_cuit_with_dashes(): void
    {
        $target = Client::factory()->create(['cuit' => '30-71234567-1']);
        $other = Client::factory()->create();

        Livewire::test(ListClients::class)
            ->searchTable('30-71234567')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_creates_a_client_storing_cuit_as_digits(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'business_name' => 'Estudio Contable Pérez',
                'cuit' => '20-12345678-6',
                'tax_condition' => TaxCondition::Monotributista->value,
                'email' => 'info@estudioperez.com.ar',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('clients', [
            'business_name' => 'Estudio Contable Pérez',
            'cuit' => '20123456786',
            'tax_condition' => 'monotributista',
        ]);

        $this->assertSame('20-12345678-6', Client::first()->cuit);
    }

    public function test_rejects_an_invalid_cuit(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'business_name' => 'Cliente',
                'cuit' => '20-12345678-5',
            ])
            ->call('create')
            ->assertHasFormErrors(['cuit']);
    }

    public function test_rejects_a_duplicated_cuit(): void
    {
        Client::factory()->create(['cuit' => '20123456786']);

        Livewire::test(CreateClient::class)
            ->fillForm([
                'business_name' => 'Otro cliente',
                'cuit' => '20-12345678-6',
            ])
            ->call('create')
            ->assertHasFormErrors(['cuit']);
    }

    public function test_edits_a_client_keeping_its_own_cuit(): void
    {
        $client = Client::factory()->create(['cuit' => '20123456786']);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['business_name' => 'Nombre nuevo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nombre nuevo', $client->refresh()->business_name);
    }

    public function test_requires_business_name(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm(['business_name' => ''])
            ->call('create')
            ->assertHasFormErrors(['business_name' => 'required']);
    }

    public function test_shows_a_client(): void
    {
        $client = Client::factory()->create();

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->assertOk()
            ->assertSee($client->business_name);
    }

    public function test_deleting_a_client_is_soft(): void
    {
        $client = Client::factory()->create();

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->callAction('delete');

        $this->assertSoftDeleted($client);
    }

    public function test_adds_contacts_from_the_client_page(): void
    {
        $client = Client::factory()->create();

        Livewire::test(ContactsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => EditClient::class,
        ])
            ->callTableAction('create', data: [
                'name' => 'María Gómez',
                'email' => 'maria@example.com',
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('María Gómez', $client->primaryContact->name);
    }

    public function test_a_client_keeps_a_single_primary_contact(): void
    {
        $client = Client::factory()->create();
        $first = ClientContact::factory()->for($client)->primary()->create();
        $second = ClientContact::factory()->for($client)->primary()->create();

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);
    }
}
