const CACHE_NAME = 'pancor-mas-static-v2';
const STATIC_ASSETS = ['/offline.html', '/manifest.webmanifest', '/pwa-icon.svg'];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    if (new URL(request.url).origin !== self.location.origin
        || !['style', 'script', 'font', 'image'].includes(request.destination)) return;

    event.respondWith(
        caches.match(request).then(cached => {
            const refreshed = fetch(request).then(response => {
                if (response.ok && response.type === 'basic') {
                    caches.open(CACHE_NAME).then(cache => cache.put(request, response.clone()));
                }

                return response;
            });

            return cached || refreshed;
        }),
    );
});
