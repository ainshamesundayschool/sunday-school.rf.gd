const CACHE_NAME = 'sunday_school_taranim_v20260729_v15';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

// NETWORK-FIRST STRATEGY (ALWAYS GET LATEST FROM SERVER, FALLBACK TO CACHE IF OFFLINE)
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const url = event.request.url;
  const isApi = url.includes('api.php') || url.includes('/api/') || url.includes('action=live');

  // Let browser handle dynamic API requests natively with zero Service Worker proxy delays
  if (isApi) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
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
