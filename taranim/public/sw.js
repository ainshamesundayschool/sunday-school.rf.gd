// TARANIM PWA & OBS PRESENTER SERVICE WORKER (100% OFFLINE CAPABLE)
const CACHE_NAME = 'taranim-pwa-v5';
const PRECACHE_ASSETS = [
  './',
  './index.html',
  './present.html',
  './style.css',
  './app.js',
  './favicon.png',
  './logoicon.png',
  './song_scales_map.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_ASSETS).catch(() => {});
    })
  );
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
  const url = event.request.url;

  // Bypass live API calls when offline (handled via BroadcastChannel / localStorage)
  if (url.includes('api.php?action=live')) {
    event.respondWith(
      fetch(event.request).catch(() => {
        return new Response(JSON.stringify({ status: 'offline' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json; charset=utf-8' }
        });
      })
    );
    return;
  }

  // Cache-First with ignoreSearch for query strings (e.g. style.css?v=...)
  event.respondWith(
    caches.match(event.request, { ignoreSearch: true }).then((cachedResponse) => {
      if (cachedResponse) {
        // Fetch fresh copy in background if online
        fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkResponse));
          }
        }).catch(() => {});
        return cachedResponse;
      }

      return fetch(event.request).then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseToCache));
        }
        return networkResponse;
      }).catch(() => {
        // Fallback for CSS offline if not in cache
        if (event.request.destination === 'style' || url.endsWith('.css') || url.includes('.css?')) {
          return caches.match('./style.css', { ignoreSearch: true }).then(cssRes => {
            return cssRes || new Response('/* Offline CSS Fallback */', {
              status: 200,
              headers: { 'Content-Type': 'text/css' }
            });
          });
        }
        return new Response('', { status: 200, statusText: 'OK' });
      });
    })
  );
});
