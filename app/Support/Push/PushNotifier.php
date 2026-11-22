<?php

namespace App\Support\Push;

use App\Models\PushSubscription;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

/**
 * Sends a notification to the devices where notifications were turned on. The
 * payload is encrypted for each device and signed with the VAPID keys, so the
 * push service (Google, Apple, Mozilla) only relays it.
 */
class PushNotifier
{
    /**
     * Notifications nobody saw within this time are dropped by the push service.
     */
    private const TTL_SECONDS = 12 * 60 * 60;

    public static function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    public static function publicKey(): ?string
    {
        return config('services.webpush.public_key');
    }

    /**
     * Devices the push service no longer knows (the user revoked the permission
     * or uninstalled the app) are removed.
     *
     * @param  iterable<PushSubscription>  $subscriptions
     * @return int Notifications the push service accepted.
     */
    public function send(iterable $subscriptions, string $title, string $body, string $url = '/', string $tag = 'pez'): int
    {
        if (! self::isConfigured()) {
            return 0;
        }

        $webPush = $this->webPush();
        $payload = json_encode(compact('title', 'body', 'url', 'tag'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification($subscription->toWebPush(), $payload);
        }

        $delivered = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered++;
            } elseif ($report->isSubscriptionExpired()) {
                PushSubscription::query()->forEndpoint($report->getEndpoint())->delete();
            } else {
                Log::warning('No se pudo enviar una notificación push.', [
                    'push_service' => parse_url($report->getEndpoint(), PHP_URL_HOST),
                    'reason' => $report->getReason(),
                ]);
            }
        }

        return $delivered;
    }

    public function sendToEveryone(string $title, string $body, string $url = '/', string $tag = 'pez'): int
    {
        return $this->send(PushSubscription::query()->lazyById(), $title, $body, $url, $tag);
    }

    private function webPush(): WebPush
    {
        $factory = new HttpFactory;

        return new WebPush(
            auth: ['VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ]],
            defaultOptions: ['TTL' => self::TTL_SECONDS, 'urgency' => 'normal'],
            client: new HttpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            // Without it, the notice about the missing GMP/BCMath extensions (optional,
            // only for speed) is raised as a PHP notice, which Laravel turns into an exception.
            logger: app(LoggerInterface::class),
        );
    }
}
