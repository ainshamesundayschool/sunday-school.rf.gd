// TARANIM PWA & OBS PRESENTER SERVICE WORKER (100% OFFLINE CAPABLE ON ALL DEVICES & IOS SAFARI)
const CACHE_NAME = 'taranim-pwa-v10';
const PRECACHE_ASSETS = [
  '/',
  './',
  'index.html',
  './index.html',
  'present.html',
  './present.html',
  'style.css',
  './style.css',
  'app.js',
  './app.js',
  'favicon.ico',
  './favicon.ico',
  'logoicon.png',
  './logoicon.png',
  'logo.png',
  './logo.png',
  'logo_t.png',
  './logo_t.png',
  'logo_tw.png',
  './logo_tw.png',
  'song_scales_map.js',
  './song_scales_map.js',
  'song_scales_map.json',
  './song_scales_map.json',
  'arabic_dictionary.json',
  './arabic_dictionary.json',
  'bible_books_data.json',
  './bible_books_data.json',
  'bible_chapters_data.json',
  './bible_chapters_data.json',
  'songs_catalog.json',
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

  // Handle all HTML navigation & document requests 100% Cache-First (Instant 0ms offline capability for iOS PWA)
  const isNavigation = event.request.mode === 'navigate' || 
                       event.request.destination === 'document' ||
                       url.includes('present.html') ||
                       url.includes('index.html') ||
                       (event.request.headers.get('accept') || '').includes('text/html');

  if (isNavigation) {
    event.respondWith(
      (async () => {
        try {
          const cache = await caches.open(CACHE_NAME).catch(() => null);
          if (cache) {
            if (url.includes('present.html')) {
              const cachedPresent = await cache.match('present.html', { ignoreSearch: true }) ||
                                   await cache.match('./present.html', { ignoreSearch: true });
              if (cachedPresent) return cachedPresent;
            }

            const cachedIndex = await cache.match('index.html', { ignoreSearch: true }) ||
                               await cache.match('./index.html', { ignoreSearch: true }) ||
                               await cache.match('/', { ignoreSearch: true }) ||
                               await cache.match('./', { ignoreSearch: true });
            if (cachedIndex) return cachedIndex;

            const directMatch = await cache.match(event.request, { ignoreSearch: true });
            if (directMatch) return directMatch;
          }
        } catch (e) {}

        try {
          const directMatch = await caches.match(event.request, { ignoreSearch: true }) ||
                              await caches.match('./index.html', { ignoreSearch: true }) ||
                              await caches.match('index.html', { ignoreSearch: true }) ||
                              await caches.match('./present.html', { ignoreSearch: true });
          if (directMatch) return directMatch;
        } catch (e) {}

        if (navigator.onLine) {
          try {
            const netRes = await fetch(event.request);
            if (netRes && netRes.ok) return netRes;
          } catch (e) {}
        }

        const fallbackIndex = await caches.match('./index.html', { ignoreSearch: true }) || 
                              await caches.match('index.html', { ignoreSearch: true }) ||
                              await caches.match('./present.html', { ignoreSearch: true });
        if (fallbackIndex) return fallbackIndex;

        return new Response(
          '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>تطبيق الترنم</title></head><body><script>location.reload();</script></body></html>',
          { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
      })()
    );
    return;
  }

  // Cache-First with ignoreSearch for all static assets
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

        if (navigator.onLine) {
          const networkResponse = await fetch(event.request);
          if (networkResponse && networkResponse.status === 200) {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseToCache));
          }
          return networkResponse;
        }
      } catch (err) {}

      // Fallbacks when offline
      if (url.endsWith('.css') || url.includes('.css?')) {
        const cssRes = await caches.match('./style.css', { ignoreSearch: true }) || await caches.match('style.css', { ignoreSearch: true });
        return cssRes || new Response('/* Offline CSS */', { status: 200, headers: { 'Content-Type': 'text/css' } });
      }
      if (url.endsWith('.js') || url.includes('.js?')) {
        const jsRes = await caches.match('./app.js', { ignoreSearch: true }) || await caches.match('app.js', { ignoreSearch: true });
        return jsRes || new Response('/* Offline JS */', { status: 200, headers: { 'Content-Type': 'application/javascript' } });
      }
      if (url.endsWith('.json') || url.includes('.json?')) {
        const jsonRes = await caches.match(event.request, { ignoreSearch: true });
        return jsonRes || new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } });
      }
      return new Response('', { status: 200, statusText: 'OK' });
    })()
  );
});
