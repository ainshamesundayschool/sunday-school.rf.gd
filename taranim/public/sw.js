const CACHE_NAME = 'sunday_school_taranim_v20260729_v16';

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

// ACTIVATE: CLEANUP OLD CACHES
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

// FETCH STRATEGY: CACHE-FIRST FOR OFFLINE COMPATIBILITY, FALLBACK TO NETWORK
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

  const isNavigation = event.request.mode === 'navigate' || url.endsWith('/taranim/') || url.includes('index.html') || url.includes('obs.html') || url.includes('install.html');
  const isStaticAsset = url.includes('songs_catalog.json') || url.includes('arabic_dictionary.json') || url.includes('playlists.json') || url.includes('app.js') || url.includes('style.css') || url.includes('logo.png') || url.includes('manifest.webmanifest') || url.includes('fonts.googleapis') || url.includes('fontawesome');

  // Cache-First with Network fallback for Navigation and Static Assets
  if (isNavigation || isStaticAsset) {
    event.respondWith(
      caches.match(event.request, { ignoreSearch: true }).then((cached) => {
        if (cached) {
          // Asynchronously update cache from network if online
          fetch(event.request).then((networkRes) => {
            if (networkRes && networkRes.status === 200) {
              caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkRes));
            }
          }).catch(() => {});
          return cached;
        }

        return fetch(event.request)
          .then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200) {
              const clone = networkResponse.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
            }
            return networkResponse;
          })
          .catch(() => {
            if (isNavigation) {
              return caches.match('./index.html') || caches.match('./install.html');
            }
          });
      })
    );
    return;
  }

  // Network-First for other requests
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseToCache));
        }
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request);
      })
  );
});
