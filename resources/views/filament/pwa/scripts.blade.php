@unless (request()->routeIs('filament.'.config('pwa.panel').'.auth.login'))
    <style>
        #pwa-banner {
            position: fixed;
            left: 50%;
            bottom: calc(1rem + env(safe-area-inset-bottom));
            z-index: 50;
            width: calc(100% - 2rem);
            max-width: 32rem;
            transform: translateX(-50%);
            align-items: center;
            gap: .75rem;
            padding: .75rem .75rem .75rem 1rem;
            border-radius: .75rem;
            background: #ffffff;
            color: var(--gray-950, #030712);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / .1), 0 0 0 1px rgb(3 7 18 / .05);
            font-size: .875rem;
            line-height: 1.25rem;
        }

        .dark #pwa-banner {
            background: var(--gray-900, #111827);
            color: #ffffff;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / .3), 0 0 0 1px rgb(255 255 255 / .1);
        }

        #pwa-banner [data-pwa-text] {
            flex: 1;
        }

        #pwa-banner [data-pwa-action] {
            padding: .375rem .75rem;
            border-radius: .5rem;
            background: var(--primary-600, #059669);
            color: #ffffff;
            font-weight: 600;
            white-space: nowrap;
        }

        #pwa-banner [data-pwa-action]:hover {
            background: var(--primary-500, #10b981);
        }

        #pwa-banner [data-pwa-dismiss] {
            padding: .25rem;
            border-radius: .5rem;
            color: var(--gray-400, #9ca3af);
            line-height: 0;
        }

        #pwa-banner [data-pwa-dismiss]:hover {
            color: var(--gray-500, #6b7280);
        }
    </style>

    <div id="pwa-banner" role="status" style="display: none;">
        <span data-pwa-text></span>
        <button type="button" data-pwa-action></button>
        <button type="button" data-pwa-dismiss aria-label="Cerrar">
            <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
        </button>
    </div>
@endunless

<script>
    (() => {
        const DISMISS_KEY = 'pwa-install-dismissed';
        const IOS_TEXT = 'Tocá Compartir y después "Agregar a inicio".';

        const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        const isDismissed = () => {
            try {
                return localStorage.getItem(DISMISS_KEY) === '1';
            } catch (error) {
                return false;
            }
        };

        const setDismissed = (dismissed) => {
            try {
                dismissed ? localStorage.setItem(DISMISS_KEY, '1') : localStorage.removeItem(DISMISS_KEY);
            } catch (error) {
                //
            }
        };

        const canInstall = () => ! isStandalone() && (!! window.deferredInstallPrompt || isIos());

        const showBanner = (mode, text, actionLabel) => {
            const banner = document.getElementById('pwa-banner');

            if (! banner) {
                return;
            }

            const action = banner.querySelector('[data-pwa-action]');

            banner.dataset.mode = mode;
            banner.querySelector('[data-pwa-text]').textContent = text;
            action.textContent = actionLabel ?? '';
            action.style.display = actionLabel ? '' : 'none';
            banner.style.display = 'flex';
        };

        const hideBanner = () => {
            const banner = document.getElementById('pwa-banner');

            if (banner) {
                banner.style.display = 'none';
            }
        };

        const showInstallBanner = () => {
            if (window.deferredInstallPrompt) {
                showBanner('install', 'Instalá la app para abrirla sin el navegador.', 'Instalar');
            } else if (isIos()) {
                showBanner('install', IOS_TEXT, null);
            }
        };

        const refreshPwaUi = () => {
            document.querySelectorAll('[data-pwa-install]').forEach((item) => {
                item.style.display = canInstall() ? '' : 'none';
            });

            if (window.pwaRegistration?.waiting) {
                showBanner('update', 'Hay una versión nueva del sistema.', 'Actualizar');
            } else if (canInstall() && ! isDismissed()) {
                showInstallBanner();
            } else {
                hideBanner();
            }
        };

        const install = () => {
            const installPrompt = window.deferredInstallPrompt;

            if (! installPrompt) {
                if (isIos() && ! isStandalone()) {
                    showBanner('install', IOS_TEXT, null);
                }

                return;
            }

            installPrompt.prompt();
            installPrompt.userChoice.finally(() => {
                window.deferredInstallPrompt = null;
                refreshPwaUi();
            });
        };

        window.refreshPwaUi = refreshPwaUi;

        // Runs on every wire:navigate visit, since the body is rendered again.
        refreshPwaUi();

        if (window.pwaBooted) {
            return;
        }

        window.pwaBooted = true;

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-pwa-install]')) {
                setDismissed(false);
                install();

                return;
            }

            if (event.target.closest('#pwa-banner [data-pwa-action]')) {
                const mode = document.getElementById('pwa-banner').dataset.mode;

                if (mode === 'update') {
                    window.pwaRegistration?.waiting?.postMessage({ type: 'SKIP_WAITING' });
                } else {
                    install();
                }

                return;
            }

            if (event.target.closest('#pwa-banner [data-pwa-dismiss]')) {
                if (document.getElementById('pwa-banner').dataset.mode === 'install') {
                    setDismissed(true);
                }

                hideBanner();
            }
        });

        window.addEventListener('pwa:installable', () => refreshPwaUi());

        window.addEventListener('appinstalled', () => {
            window.deferredInstallPrompt = null;
            refreshPwaUi();
        });

        if (! ('serviceWorker' in navigator)) {
            return;
        }

        const offerUpdate = () => refreshPwaUi();
        const hadController = !! navigator.serviceWorker.controller;

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            // On the first install clients.claim() fires this too.
            if (! hadController || window.pwaReloading) {
                return;
            }

            window.pwaReloading = true;
            location.reload();
        });

        const register = () => {
            navigator.serviceWorker.register(@js(route('pwa.service-worker', absolute: false)), { scope: '/' }).then((registration) => {
                window.pwaRegistration = registration;

                if (registration.waiting) {
                    offerUpdate();
                }

                registration.addEventListener('updatefound', () => {
                    const installing = registration.installing;

                    installing?.addEventListener('statechange', () => {
                        if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                            offerUpdate();
                        }
                    });
                });

                // An installed app can stay open for days without navigating.
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        registration.update();
                    }
                });

                setInterval(() => registration.update(), 30 * 60 * 1000);
            }).catch(() => {});
        };

        document.readyState === 'complete' ? register() : window.addEventListener('load', register);
    })();
</script>
