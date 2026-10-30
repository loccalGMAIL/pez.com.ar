<?php

namespace Tests\Feature;

use App\Http\Controllers\PwaController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    private function panelUrl(string $path = '/'): string
    {
        return 'http://'.config('app.admin_domain').$path;
    }

    public function test_manifest_is_public_and_installable(): void
    {
        $manifest = $this->get($this->panelUrl('/manifest.webmanifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->json();

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame(PwaController::panelPath(), $manifest['id']);
        $this->assertSame($manifest['id'], $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
    }

    public function test_manifest_icons_exist_and_use_relative_paths(): void
    {
        $manifest = $this->get($this->panelUrl('/manifest.webmanifest'))->json();

        $this->assertContains('192x192', array_column($manifest['icons'], 'sizes'));
        $this->assertContains('512x512', array_column($manifest['icons'], 'sizes'));
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        foreach ([...$manifest['icons'], ...$manifest['screenshots'] ?? []] as $image) {
            $this->assertStringStartsNotWith('http', $image['src']);
            $this->assertFileExists(public_path(ltrim($image['src'], '/')));
        }

        $this->assertFileExists(public_path('apple-touch-icon.png'));
    }

    public function test_service_worker_never_caches_pages_or_livewire(): void
    {
        config(['pwa.version' => 'test-1.2.3']);

        $response = $this->get($this->panelUrl('/sw.js'))->assertOk();

        $this->assertStringContainsString('javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));

        $response->assertSee('test-1.2.3', escape: false)
            ->assertSee("request.mode === 'navigate'", escape: false)
            ->assertSee('CACHEABLE_PREFIXES', escape: false)
            ->assertSee('SKIP_WAITING', escape: false)
            ->assertDontSee("'/livewire/'", escape: false);
    }

    public function test_offline_page_sets_no_cookies(): void
    {
        $response = $this->get($this->panelUrl('/offline'))
            ->assertOk()
            ->assertSee('Sin conexión');

        $this->assertEmpty($response->headers->getCookies());
    }

    public function test_login_links_the_app_without_the_banner(): void
    {
        $response = $this->get($this->panelUrl('/login'))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', escape: false)
            ->assertSee('viewport-fit=cover', escape: false)
            ->assertSee('serviceWorker', escape: false)
            ->assertDontSee('id="pwa-banner"', escape: false);

        foreach (PwaController::splashScreens() as [$width, $height]) {
            $response->assertSee("/icons/splash/{$width}x{$height}.png", escape: false);
        }
    }

    public function test_panel_shows_the_banner_and_the_install_menu_item(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->panelUrl())
            ->assertOk()
            ->assertSee('id="pwa-banner"', escape: false)
            ->assertSee('data-pwa-install', escape: false);
    }

    public function test_splash_screen_is_only_drawn_for_known_sizes(): void
    {
        $this->get($this->panelUrl('/icons/splash/750x1334.png'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->get($this->panelUrl('/icons/splash/9999x9999.png'))->assertNotFound();
    }
}
