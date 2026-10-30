<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function siteUrl(string $path = '/'): string
    {
        return 'http://'.config('app.domain').$path;
    }

    private function panelUrl(string $path = '/'): string
    {
        return 'http://'.config('app.admin_domain').$path;
    }

    public function test_landing_is_served_on_the_main_domain(): void
    {
        $this->get($this->siteUrl())->assertOk();
    }

    public function test_landing_is_not_served_on_the_panel_domain(): void
    {
        $this->get($this->panelUrl())->assertRedirect($this->panelUrl('/login'));
    }

    public function test_panel_login_is_served_on_the_panel_domain(): void
    {
        $this->get($this->panelUrl('/login'))->assertOk();
    }

    public function test_panel_is_not_served_on_the_main_domain(): void
    {
        $this->get($this->siteUrl('/login'))->assertNotFound();
    }

    public function test_authenticated_user_can_open_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->panelUrl())
            ->assertOk();
    }
}
