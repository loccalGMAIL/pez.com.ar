<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Support\Push\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Called from the panel when notifications are turned on or off on a device,
 * with the subscription the browser's push service handed over.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request, PushNotifier $notifier): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $subscription = PushSubscription::query()->forEndpoint($validated['endpoint'])->firstOrNew();

        $subscription->fill([
            'user_id' => $request->user()->id,
            'endpoint' => $validated['endpoint'],
            'public_key' => $validated['keys']['p256dh'],
            'auth_token' => $validated['keys']['auth'],
            'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ])->save();

        // Confirms on the device itself that everything works.
        $delivered = $notifier->send([$subscription], 'Notificaciones activadas', 'Te vamos a avisar acá cuando haya seguimientos para enviar.', '/', 'push-enabled');

        return response()->json(['delivered' => $delivered > 0], 201);
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        $request->user()->pushSubscriptions()->forEndpoint($validated['endpoint'])->delete();

        return response()->noContent();
    }
}
