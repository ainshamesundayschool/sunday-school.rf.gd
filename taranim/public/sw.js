const CACHE_NAME = 'sunday_school_taranim_v20260730_v18';

const PRECACHE_URLS = [
  './',
  './index.html',
  './obs.html',
  './install.html',
  './app.js',
  './style.css',
  './logo.png',
  './manifest.webmanifest',
  './songs_catalog.json',
  './arabic_dictionary.json',
  './playlists.json'
];

// PRECACHE SHELL ON INSTALL
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      for (const url of PRECACHE_URLS) {
        try {
          const res = await fetch(url, { cache: 'no-cache' });
          if (res && res.status === 200) {
            await cache.put(url, res);
          }
        } catch (err) {}
      }
    })
  );
});

// ACTIVATE: CLEANUP OLD CACHES AND CLAIM CLIENTS IMMEDIATELY
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

// FETCH STRATEGY: NETWORK-FIRST FOR CODE & NAVIGATION, CACHE-FIRST FOR DATA/FONTS
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const url = event.request.url;
  const isApi = url.includes('api.php') || url.includes('/api/') || url.includes('action=live');

  // Bypass API requests to let browser handle them directly
  if (isApi) {
    return;
  }

  const isCodeOrNav = event.request.mode === 'navigate' ||
                      url.endsWith('/taranim/') ||
                      url.includes('index.html') ||
                      url.includes('obs.html') ||
                      url.includes('install.html') ||
                      url.includes('app.js') ||
                      url.includes('style.css') ||
                      url.includes('sw.js');

  // Network-First strategy for application code (HTML, JS, CSS) to guarantee fresh code on every normal refresh when online
  if (isCodeOrNav) {
    event.respondWith(
      fetch(event.request, { cache: 'no-cache' })
        .then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const clone = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
          }
          return networkRes;
        })
        .catch(() => {
          return caches.match(event.request, { ignoreSearch: true }).then((cached) => {
            return cached || caches.match('./index.html');
          });
        })
    );
    return;
  }

  // Cache-First strategy for large static data files (songs catalog, dictionary, images, fonts)
  event.respondWith(
    caches.match(event.request, { ignoreSearch: true }).then((cached) => {
      if (cached) return cached;
      return fetch(event.request)
        .then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const clone = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
          }
          return networkRes;
        })
        .catch(() => {
          return caches.match('./index.html');
        });
    })
  );
});
