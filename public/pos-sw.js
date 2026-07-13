const CACHE_NAME = 'retailpos-pos-shell-v3';

self.addEventListener('install', (event) => event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(['/pos-manifest.webmanifest', '/pos-icon.svg', '/pos-offline.html'])).then(() => self.skipWaiting()),
));
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Authenticated POS data is refreshed into IndexedDB by the POS app. This
// worker intentionally caches only the public shell and offline fallback.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;
    event.respondWith(fetch(event.request).catch(() => event.request.mode === 'navigate' ? caches.match('/pos-offline.html') : caches.match(event.request)));
});
