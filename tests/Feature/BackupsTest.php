<?php

namespace Tests\Feature;

use App\Filament\Pages\Backups;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BackupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backups_cover_the_database_and_uploaded_files(): void
    {
        $this->assertSame([storage_path('app/private')], config('backup.backup.source.files.include'));
        $this->assertSame(['backups'], config('backup.backup.destination.disks'));
        $this->assertNotEmpty(config('backup.backup.source.databases'));
    }

    public function test_failure_mails_always_have_a_valid_recipient(): void
    {
        // laravel-backup validates this address while the app boots (also during
        // composer install, before any .env exists), so it can never be empty.
        foreach (['', 'avisos@pez.com.ar'] as $configured) {
            putenv("BACKUP_NOTIFICATION_EMAIL={$configured}");
            $_ENV['BACKUP_NOTIFICATION_EMAIL'] = $_SERVER['BACKUP_NOTIFICATION_EMAIL'] = $configured;

            $to = (require config_path('backup.php'))['notifications']['mail']['to'];

            $this->assertNotFalse(filter_var($to, FILTER_VALIDATE_EMAIL));
            $configured === '' || $this->assertSame($configured, $to);
        }

        putenv('BACKUP_NOTIFICATION_EMAIL');
        unset($_ENV['BACKUP_NOTIFICATION_EMAIL'], $_SERVER['BACKUP_NOTIFICATION_EMAIL']);
    }

    public function test_backups_run_every_night_and_are_monitored(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->join("\n");

        $this->assertStringContainsString('backup:clean', $commands);
        $this->assertStringContainsString('backup:run', $commands);
        $this->assertStringContainsString('backup:monitor', $commands);
    }

    public function test_lists_and_downloads_backups(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('backups');
        Storage::disk('backups')->put('pez-sistema/2026-10-29-01-30-00.zip', str_repeat('x', 2048));
        Storage::disk('backups')->put('pez-sistema/2026-10-30-01-30-00.zip', 'zip');

        $this->assertEqualsCanonicalizing(
            ['pez-sistema/2026-10-30-01-30-00.zip', 'pez-sistema/2026-10-29-01-30-00.zip'],
            array_keys(Backups::backups()),
        );

        Livewire::test(Backups::class)
            ->assertSee('2026-10-29-01-30-00.zip')
            ->assertSee('2.0 KB')
            ->callTableAction('download', 'pez-sistema/2026-10-30-01-30-00.zip')
            ->assertFileDownloaded('2026-10-30-01-30-00.zip');
    }

    public function test_deletes_a_backup(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('backups');
        Storage::disk('backups')->put('pez-sistema/old.zip', 'zip');

        Livewire::test(Backups::class)
            ->callTableAction('delete', 'pez-sistema/old.zip');

        Storage::disk('backups')->assertMissing('pez-sistema/old.zip');
    }

    public function test_makes_a_backup_from_the_panel(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('backups');
        // The in-memory test database can't be dumped the same way everywhere
        // (it depends on the sqlite3 binary), so back up the files only.
        config(['backup.backup.source.databases' => []]);

        Livewire::test(Backups::class)
            ->callAction('backupNow')
            ->assertNotified('Backup hecho');

        $this->assertCount(1, Backups::backups());
    }

    public function test_reports_a_failed_backup(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('backups');
        config(['backup.backup.source.databases' => ['missing-connection']]);

        Livewire::test(Backups::class)
            ->callAction('backupNow')
            ->assertNotified('No se pudo hacer el backup');
    }
}
