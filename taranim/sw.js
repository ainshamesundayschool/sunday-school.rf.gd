// TARANIM PWA & OBS PRESENTER SERVICE WORKER (100% OFFLINE CAPABLE)
const CACHE_NAME = 'taranim-pwa-v9';
const PRECACHE_ASSETS = [
  './',
  './index.html',
  './present.html',
  './style.css',
  './app.js',
  './favicon.ico',
  './logoicon.png',
  './logo.png',
  './logo_t.png',
  './logo_tw.png',
  './song_scales_map.js',
  './song_scales_map.json',
  './arabic_dictionary.json',
  './bible_books_data.json',
  './bible_chapters_data.json',
  './songs_catalog.json',
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

  // Handle navigation & document requests offline cleanly (Safari Fix)
  const isNavigation = event.request.mode === 'navigate' || 
                       event.request.destination === 'document' ||
                       (event.request.headers.get('accept') || '').includes('text/html');

  if (isNavigation) {
    event.respondWith(
      (async () => {
        try {
          const cached = await caches.match(event.request, { ignoreSearch: true }) ||
                         await caches.match('./index.html', { ignoreSearch: true }) ||
                         await caches.match('./present.html', { ignoreSearch: true });
          if (cached) return cached;
        } catch (e) {}

        try {
          const netRes = await fetch(event.request);
          if (netRes && netRes.ok) return netRes;
        } catch (e) {}

        const fallbackHtml = await caches.match('./index.html', { ignoreSearch: true }) || 
                             await caches.match('./present.html', { ignoreSearch: true });
        if (fallbackHtml) return fallbackHtml;

        return new Response(
          '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>تطبيق الترنم</title></head><body><script>location.reload();</script></body></html>',
          { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
      })()
    );
    return;
  }

  // Cache-First with ignoreSearch for static assets (e.g. style.css?v=...)
  event.respondWith(
    (async () => {
      try {
        const cachedResponse = await caches.match(event.request, { ignoreSearch: true });
        if (cachedResponse) {
          if (navigator.onLine) {
            fetch(event.request).then((networkResponse) => {
              if (networkResponse && networkResponse.status === 200) {
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkResponse));
              }
            }).catch(() => {});
          }
          return cachedResponse;
        }

        const networkResponse = await fetch(event.request);
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseToCache));
        }
        return networkResponse;
      } catch (err) {
        // Asset offline fallbacks
        if (url.endsWith('.css') || url.includes('.css?')) {
          const cssRes = await caches.match('./style.css', { ignoreSearch: true });
          return cssRes || new Response('/* Offline CSS */', { status: 200, headers: { 'Content-Type': 'text/css' } });
        }
        if (url.endsWith('.js') || url.includes('.js?')) {
          const jsRes = await caches.match('./app.js', { ignoreSearch: true });
          return jsRes || new Response('/* Offline JS */', { status: 200, headers: { 'Content-Type': 'application/javascript' } });
        }
        if (url.endsWith('.json') || url.includes('.json?')) {
          return new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        return new Response('', { status: 200, statusText: 'OK' });
      }
    })()
  );
});
