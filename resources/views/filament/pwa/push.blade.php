@php
    use App\Support\Push\PushNotifier;
@endphp

@if (PushNotifier::isConfigured())
    <script>
        (() => {
            const PUBLIC_KEY = @js(PushNotifier::publicKey());
            const SUBSCRIPTIONS_URL = @js(route('filament.admin.push-subscriptions.store', absolute: false));

            const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            const isSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

            const notify = (title, body, status = 'success') => {
                const notification = new FilamentNotification().title(title).body(body);
                notification[status]();
                notification.send();
            };

            const setLabel = (text) => {
                document.querySelectorAll('[data-push-toggle]').forEach((item) => {
                    item.style.display = '';

                    const label = item.querySelector('.fi-dropdown-list-item-label');

                    if (label) {
                        label.textContent = text;
                    }
                });
            };

            const currentSubscription = async () => {
                const registration = await navigator.serviceWorker.ready;

                return registration.pushManager.getSubscription();
            };

            const refresh = async () => {
                if (isSupported()) {
                    window.pushSubscribed = !! (await currentSubscription());
                    setLabel(window.pushSubscribed ? 'Desactivar notificaciones' : 'Activar notificaciones');
                } else if (isIos() && ! isStandalone()) {
                    // Safari only offers notifications to the app added to the home screen.
                    setLabel('Activar notificaciones');
                }
            };

            // Runs on every wire:navigate visit, since the body is rendered again.
            refresh().catch(() => {});

            if (window.pushBooted) {
                return;
            }

            window.pushBooted = true;

            const request = (method, body) => fetch(SUBSCRIPTIONS_URL, {
                method,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(body),
            }).then((response) => {
                if (! response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response;
            });

            const keyBytes = (base64Url) => {
                const base64 = (base64Url + '='.repeat((4 - base64Url.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');

                return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
            };

            const enable = async () => {
                // Asked first, while the tap still counts as a user gesture (Safari requires it).
                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    notify('Notificaciones bloqueadas', 'Habilitalas para esta app en los ajustes del navegador o del celular.', 'warning');

                    return;
                }

                const registration = await navigator.serviceWorker.ready;

                // A subscription made with other keys can't be reused.
                await (await registration.pushManager.getSubscription())?.unsubscribe();

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: keyBytes(PUBLIC_KEY),
                });

                const encodings = PushManager.supportedContentEncodings ?? ['aes128gcm'];

                const response = await request('POST', {
                    ...subscription.toJSON(),
                    contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
                });

                if ((await response.json()).delivered) {
                    notify('Notificaciones activadas', 'En unos segundos te llega una de prueba.');
                } else {
                    // The push service refused it and the server dropped the device.
                    await subscription.unsubscribe();
                    notify('No llegó la notificación de prueba', 'Probá activarlas de nuevo en un momento.', 'warning');
                }
            };

            const disable = async () => {
                const subscription = await currentSubscription();

                if (subscription) {
                    await request('DELETE', { endpoint: subscription.endpoint });
                    await subscription.unsubscribe();
                }

                notify('Notificaciones desactivadas', 'Este dispositivo ya no recibe avisos.');
            };

            document.addEventListener('click', async (event) => {
                if (! event.target.closest('[data-push-toggle]')) {
                    return;
                }

                if (! isSupported()) {
                    notify('Primero instalá la app', 'En el iPhone las notificaciones funcionan con la app en la pantalla de inicio: tocá Compartir y después "Agregar a inicio".', 'info');

                    return;
                }

                try {
                    window.pushSubscribed ? await disable() : await enable();
                } catch (error) {
                    notify('No se pudieron cambiar las notificaciones', 'Probá de nuevo en un momento.', 'danger');
                }

                refresh().catch(() => {});
            });
        })();
    </script>
@endif
