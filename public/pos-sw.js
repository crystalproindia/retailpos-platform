const CACHE_NAME = 'retailpos-pos-shell-v2';

self.addEventListener('install', (event) => event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(['/pos-manifest.webmanifest', '/pos-icon.svg', '/pos-offline.html'])).then(() => self.skipWaiting()),
));
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// POS transactions remain online-only. This worker establishes the PWA shell
// and intentionally avoids caching authenticated sales or customer data.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;
    event.respondWith(fetch(event.request).catch(() => event.request.mode === 'navigate' ? caches.match('/pos-offline.html') : caches.match(event.request)));
});
