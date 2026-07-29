const CACHE_NAME = 'sunday_school_taranim_v20260729_v5';

const ASSETS_TO_CACHE = [
  './',
  './index.html',
  './obs.html',
  './style.css',
  './app.js',
  './logo.png'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          // Delete all old cached versions immediately
          if (key !== CACHE_NAME) return caches.delete(key);
        })
      );
    })
  );
  self.clients.claim();
});

// NETWORK-FIRST STRATEGY: ALWAYS FETCH FRESH FILE FROM SERVER FIRST
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip POST requests (e.g. /api/live)
  if (event.request.method !== 'GET') {
    event.respondWith(fetch(event.request));
    return;
  }

  // Network-First with Cache Fallback for All HTML, JS, CSS, and API requests
  event.respondWith(
    fetch(event.request, { cache: 'no-cache' })
      .then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const responseClone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
        }
        return networkResponse;
      })
      .catch(() => {
        // Fallback to cache ONLY when completely offline
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) return cachedResponse;
          if (event.request.mode === 'navigate') {
            return caches.match('./index.html') || caches.match('./');
          }
        });
      })
  );
});
