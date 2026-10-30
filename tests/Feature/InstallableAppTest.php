<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallableAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_links_the_manifest_and_registers_the_service_worker(): void
    {
        $this->get('http://'.config('app.admin_domain').'/login')
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', escape: false)
            ->assertSee("navigator.serviceWorker.register('/sw.js')", escape: false);
    }

    public function test_panel_shows_the_install_button(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('http://'.config('app.admin_domain').'/')
            ->assertOk()
            ->assertSee('Instalar app');
    }

    public function test_manifest_is_installable_and_its_icons_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], array_unique(array_column($manifest['icons'], 'sizes')));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }

        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
    }
}
