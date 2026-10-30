/*
 * Service worker of {{ \App\Http\Controllers\PwaController::appName() }}.
 *
 * Never caches HTML nor /livewire/: the HTML carries the CSRF token (a stale
 * copy answers 419), on a shared device it would show the previous user's
 * screen, and wire:navigate fetches pages that would get out of sync.
 * Only the offline page and static assets (build, icons) are cached.
 */
const CACHE_PREFIX = @js($cachePrefix);
const CACHE_NAME = CACHE_PREFIX + @js($version);
const OFFLINE_URL = @js($offlineUrl);
const CACHEABLE_PREFIXES = ['/build/', '/css/', '/js/', '/fonts/', '/icons/'];
const CACHEABLE_PATHS = ['/favicon.ico', '/favicon.svg', '/apple-touch-icon.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL)).catch(() => {}),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        if (self.registration.navigationPreload) {
            await self.registration.navigationPreload.enable();
        }

        const keys = await caches.keys();

        await Promise.all(
            keys
                .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
                .map((key) => caches.delete(key)),
        );

        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                return (await event.preloadResponse) || (await fetch(request));
            } catch (error) {
                return (await caches.match(OFFLINE_URL)) || Response.error();
            }
        })());

        return;
    }

    const isCacheable = CACHEABLE_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))
        || CACHEABLE_PATHS.includes(url.pathname);

    if (! isCacheable) {
        return;
    }

    event.respondWith((async () => {
        const cached = await caches.match(request);

        if (cached) {
            return cached;
        }

        const response = await fetch(request);

        if (response.ok && response.type === 'basic') {
            const cache = await caches.open(CACHE_NAME);
            await cache.put(request, response.clone());
        }

        return response;
    })());
});
