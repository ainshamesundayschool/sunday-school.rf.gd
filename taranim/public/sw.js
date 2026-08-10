// TARANIM PWA SERVICE WORKER (NETWORK-FIRST WITH OFFLINE CACHE FALLBACK)
const CACHE_NAME = 'taranim-pwa-v3';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response && response.status === 200 && response.type === 'basic') {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseToCache));
        }
        return response;
      })
      .catch(() => {
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) return cachedResponse;
          if (event.request.url.includes('api.php')) {
            return new Response(JSON.stringify({ status: 'offline', total_songs: 11611 }), {
              status: 200,
              headers: { 'Content-Type': 'application/json; charset=utf-8' }
            });
          }
          return new Response('', { status: 200, statusText: 'OK' });
        });
      })
  );
});
