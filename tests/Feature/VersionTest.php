<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_version_matches_the_latest_released_changelog_entry(): void
    {
        preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', file_get_contents(base_path('CHANGELOG.md')), $matches);

        $this->assertSame($matches[1], config('app.version'));
    }

    public function test_panel_shows_the_version(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('http://'.config('app.admin_domain').'/')
            ->assertOk()
            ->assertSee('v'.config('app.version'));
    }
}
