// TARANIM PWA & OBS PRESENTER SERVICE WORKER (NETWORK-FIRST FOR LIVE REFRESH)
const CACHE_NAME = 'taranim-pwa-v25';
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
        keys.map((key) => caches.delete(key))
      );
    }).then(() => {
      return self.clients.claim().catch(() => {});
    })
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

  // Handle HTML, JS, CSS requests Network-First to guarantee instant updates
  const isCodeAsset = event.request.mode === 'navigate' || 
                      event.request.destination === 'document' ||
                      url.includes('present.html') ||
                      url.includes('index.html') ||
                      url.endsWith('.js') ||
                      url.endsWith('.css') ||
                      (event.request.headers.get('accept') || '').includes('text/html');

  if (isCodeAsset) {
    event.respondWith(
      fetch(event.request).then(async (networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const cache = await caches.open(CACHE_NAME);
          cache.put(event.request, networkResponse.clone());
        }
        return networkResponse;
      }).catch(async () => {
        const cache = await caches.open(CACHE_NAME);
        return cache.match(event.request, { ignoreSearch: true });
      })
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
