const CACHE_NAME = 'kasirrapi-static-v1';
const STATIC_ASSETS = [
    './manifest.webmanifest',
    './assets/app/app-ui.css',
    './assets/app/app-ui.js',
    './assets/app/pwa-install.js',
    './assets/app/favicon.png',
    './assets/app/logo-icon.png',
    './assets/app/logo-full.png',
    './assets/app/icon-192.png',
    './assets/app/icon-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .catch(() => null)
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys
                .filter(key => key !== CACHE_NAME)
                .map(key => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.includes('/public/uploads/') || url.pathname.includes('/uploads/')) return;

    const staticRequest = ['style', 'script', 'image', 'manifest'].includes(request.destination)
        || url.pathname.endsWith('/manifest.webmanifest');

    if (!staticRequest) return;

    event.respondWith(
        caches.match(request).then(cached => {
            if (cached) return cached;

            return fetch(request).then(response => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
                return response;
            });
        })
    );
});
