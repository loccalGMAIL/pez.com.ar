<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Push\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const PUSH_SERVICE = 'https://fcm.googleapis.com/fcm/send/*';

    private function configureKeys(): void
    {
        $keys = VAPID::createVapidKeys();

        config([
            'services.webpush.public_key' => $keys['publicKey'],
            'services.webpush.private_key' => $keys['privateKey'],
            'services.webpush.subject' => 'mailto:info@pez.com.ar',
        ]);
    }

    /**
     * What the browser hands over after PushManager.subscribe().
     *
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding: string}
     */
    private function browserSubscription(): array
    {
        $device = PushSubscription::factory()->make();

        return [
            'endpoint' => $device->endpoint,
            'keys' => ['p256dh' => $device->public_key, 'auth' => $device->auth_token],
            'contentEncoding' => 'aes128gcm',
        ];
    }

    public function test_turning_notifications_on_saves_the_device_and_sends_a_test_notification(): void
    {
        $this->configureKeys();
        Http::preventStrayRequests();
        Http::fake([self::PUSH_SERVICE => Http::response('', 201)]);
        $user = User::factory()->create();
        $subscription = $this->browserSubscription();

        $this->actingAs($user)
            ->postJson(route('filament.admin.push-subscriptions.store'), $subscription)
            ->assertCreated()
            ->assertJson(['delivered' => true]);

        $device = $user->pushSubscriptions()->sole();
        $this->assertSame($subscription['endpoint'], $device->endpoint);
        $this->assertSame($subscription['keys']['auth'], $device->auth_token);

        Http::assertSent(fn (Request $request) => $request->url() === $subscription['endpoint']
            && str_starts_with($request->header('Authorization')[0], 'vapid t=')
            && $request->header('Content-Encoding')[0] === 'aes128gcm'
            && $request->header('TTL')[0] === '43200'
            && $request->body() !== '');
    }

    public function test_turning_notifications_on_again_on_the_same_device_keeps_a_single_record(): void
    {
        $this->configureKeys();
        Http::fake([self::PUSH_SERVICE => Http::response('', 201)]);
        $user = User::factory()->create();
        $subscription = $this->browserSubscription();

        $this->actingAs($user)->postJson(route('filament.admin.push-subscriptions.store'), $subscription)->assertCreated();
        $subscription['keys']['auth'] = PushSubscription::factory()->make()->auth_token;
        $this->actingAs($user)->postJson(route('filament.admin.push-subscriptions.store'), $subscription)->assertCreated();

        $this->assertSame($subscription['keys']['auth'], PushSubscription::sole()->auth_token);
    }

    public function test_rejects_a_subscription_without_its_keys(): void
    {
        $subscription = $this->browserSubscription();
        unset($subscription['keys']);

        $this->actingAs(User::factory()->create())
            ->postJson(route('filament.admin.push-subscriptions.store'), $subscription)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);

        $this->assertDatabaseEmpty('push_subscriptions');
    }

    public function test_turning_notifications_on_requires_login(): void
    {
        $this->postJson(route('filament.admin.push-subscriptions.store'), $this->browserSubscription())
            ->assertUnauthorized();

        $this->assertDatabaseEmpty('push_subscriptions');
    }

    public function test_turning_notifications_off_removes_only_that_device(): void
    {
        $user = User::factory()->create();
        $phone = PushSubscription::factory()->for($user)->create();
        $notebook = PushSubscription::factory()->for($user)->create();

        $this->actingAs($user)
            ->deleteJson(route('filament.admin.push-subscriptions.destroy'), ['endpoint' => $phone->endpoint])
            ->assertNoContent();

        $this->assertModelMissing($phone);
        $this->assertModelExists($notebook);
    }

    public function test_devices_the_push_service_no_longer_knows_are_removed(): void
    {
        $this->configureKeys();
        Http::fake([self::PUSH_SERVICE => Http::response('', 410)]);
        $device = PushSubscription::factory()->create();

        $delivered = app(PushNotifier::class)->sendToEveryone('Prueba', 'Hola');

        $this->assertSame(0, $delivered);
        $this->assertModelMissing($device);
    }

    public function test_nothing_is_sent_until_the_keys_are_configured(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
        Http::fake();
        PushSubscription::factory()->create();

        $this->assertSame(0, app(PushNotifier::class)->sendToEveryone('Prueba', 'Hola'));

        Http::assertNothingSent();
    }
}
