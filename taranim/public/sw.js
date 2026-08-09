// IMMEDIATELY UNREGISTER SERVICE WORKER AND CLEAR ALL CACHES PERMANENTLY
self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => caches.delete(cacheName))
      );
    }).then(() => self.clients.claim()).then(() => {
      return self.registration.unregister();
    })
  );
});

self.addEventListener('fetch', (event) => {
  // Always fetch directly from network with no caching
  event.respondWith(fetch(event.request));
});
