<?php

namespace Tests\Feature;

use App\Console\Commands\NotifyFollowUps;
use App\Enums\FollowUpStatus;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\PushSubscription;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

class NotifyFollowUpsTest extends TestCase
{
    use RefreshDatabase;

    private const PUSH_SERVICE = 'https://fcm.googleapis.com/fcm/send/*';

    private function configurePush(): void
    {
        $keys = VAPID::createVapidKeys();

        config([
            'services.webpush.public_key' => $keys['publicKey'],
            'services.webpush.private_key' => $keys['privateKey'],
            'services.webpush.subject' => 'mailto:info@pez.com.ar',
        ]);

        Http::preventStrayRequests();
        Http::fake([self::PUSH_SERVICE => Http::response('', 201)]);
        PushSubscription::factory()->create();
    }

    public function test_notifies_once_a_day_from_the_configured_hour(): void
    {
        $this->configurePush();
        Settings::set(['follow_ups.notify_at' => '09:30']);
        $this->travelTo('2026-11-23 09:29');
        FollowUp::factory()->create();

        $this->artisan('follow-ups:notify')->assertSuccessful();
        Http::assertSentCount(0);

        $this->travelTo('2026-11-23 09:31');
        $this->artisan('follow-ups:notify')->assertSuccessful();
        Http::assertSentCount(1);

        $this->travelTo('2026-11-23 11:00');
        $this->artisan('follow-ups:notify')->assertSuccessful();
        Http::assertSentCount(1);

        $this->travelTo('2026-11-24 09:30');
        $this->artisan('follow-ups:notify')->assertSuccessful();
        Http::assertSentCount(2);
    }

    public function test_does_not_notify_without_follow_ups_due(): void
    {
        $this->configurePush();
        $this->travelTo('2026-11-23 10:00');
        FollowUp::factory()->create(['due_on' => today()->addDay()]);
        FollowUp::factory()->status(FollowUpStatus::Sent)->create();

        $this->artisan('follow-ups:notify')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_an_empty_hour_turns_the_notice_off(): void
    {
        $this->configurePush();
        Settings::set(['follow_ups.notify_at' => '']);
        $this->travelTo('2026-11-23 23:00');
        FollowUp::factory()->create();

        $this->artisan('follow-ups:notify')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_the_message_counts_the_follow_ups_and_names_two_clients(): void
    {
        $due = collect(['Estudio Pérez', 'Ana López', 'Ferretería Sur'])
            ->map(fn (string $name) => FollowUp::factory()->for(Client::factory()->create(['business_name' => $name, 'trade_name' => null]))->create());

        $this->assertSame('Tenés 3 seguimientos para enviar: Estudio Pérez, Ana López y 1 más.', NotifyFollowUps::message($due));
        $this->assertSame('Tenés 1 seguimiento para enviar: Estudio Pérez.', NotifyFollowUps::message($due->take(1)));
        $this->assertSame('Tenés 2 seguimientos para enviar: Estudio Pérez y Ana López.', NotifyFollowUps::message($due->take(2)));
    }
}
