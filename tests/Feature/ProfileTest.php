<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['name' => 'Ana', 'email' => 'ana@pez.com.ar']));
    }

    public function test_user_can_edit_their_name_and_email(): void
    {
        Livewire::test(EditProfile::class)
            ->fillForm(['name' => 'Ana María', 'email' => 'ana.maria@pez.com.ar', 'currentPassword' => 'password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['name' => 'Ana María', 'email' => 'ana.maria@pez.com.ar']);
    }

    public function test_profile_page_is_reachable_from_the_panel(): void
    {
        $this->assertTrue(Filament::getCurrentOrDefaultPanel()->hasProfile());

        $this->get('http://'.config('app.admin_domain').'/profile')->assertOk();
    }

    public function test_dashboard_has_no_account_widget(): void
    {
        $this->assertNotContains(AccountWidget::class, Filament::getCurrentOrDefaultPanel()->getWidgets());
    }
}
