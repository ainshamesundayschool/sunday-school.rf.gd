// TARANIM PWA & OBS PRESENTER SERVICE WORKER (OFFLINE FIRST WITH SMART SYNC)
const CACHE_NAME = 'taranim-pwa-v35';

const PRECACHE_ASSETS = [
  './',
  './index.html',
  './present.html',
  './style.css',
  './app.js',
  './manifest.json',
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
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      // Fetch each asset individually so one failure doesn't abort the entire cache
      for (const asset of PRECACHE_ASSETS) {
        try {
          const response = await fetch(asset, { cache: 'no-cache' });
          if (response && response.ok) {
            await cache.put(asset, response);
          }
        } catch (e) {
          console.warn('[SW] Precache skipped for:', asset);
        }
      }
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME && key.startsWith('taranim-pwa-')) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => {
      return self.clients.claim();
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
        return new Response(JSON.stringify({ status: 'offline', local: true }), {
          status: 200,
          headers: { 'Content-Type': 'application/json; charset=utf-8' }
        });
      })
    );
    return;
  }

  // Navigation requests (HTML pages): Network First, fallback to cached HTML
  if (event.request.mode === 'navigate' || (event.request.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(
      (async () => {
        try {
          // Attempt network fetch with a timeout
          const networkPromise = fetch(event.request);
          const timeoutPromise = new Promise((_, reject) => setTimeout(() => reject(new Error('Network timeout')), 2500));
          const networkResponse = await Promise.race([networkPromise, timeoutPromise]);
          
          if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(event.request, networkResponse.clone());
            return networkResponse;
          }
        } catch (err) {
          // Network failed or timed out — check cache
        }

        const cache = await caches.open(CACHE_NAME);
        const cached = await cache.match(event.request, { ignoreSearch: true });
        if (cached) return cached;

        if (url.includes('present.html')) {
          const matchPresent = await cache.match('./present.html', { ignoreSearch: true });
          if (matchPresent) return matchPresent;
        }

        const matchIndex = (await cache.match('./index.html', { ignoreSearch: true })) ||
                           (await cache.match('./', { ignoreSearch: true }));
        if (matchIndex) return matchIndex;

        return new Response('<!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"><title>وضع عدم الاتصال</title></head><body style="font-family:sans-serif;padding:30px;text-align:center;background:#0f172a;color:#ffffff;"><h2>أنت في وضع عدم الاتصال</h2><p>جاري تشغيل المنظومة من الذاكرة المحلية.</p></body></html>', {
          status: 200,
          headers: { 'Content-Type': 'text/html; charset=utf-8' }
        });
      })()
    );
    return;
  }

  // JSON databases (songs_catalog, bible data, arabic dictionary): Stale-While-Revalidate
  if (url.endsWith('.json') || url.includes('.json?')) {
    event.respondWith(
      caches.open(CACHE_NAME).then(async (cache) => {
        const cachedResponse = await cache.match(event.request, { ignoreSearch: true });
        const fetchPromise = fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            cache.put(event.request, networkResponse.clone());
          }
          return networkResponse;
        }).catch(() => null);

        return cachedResponse || (await fetchPromise) || new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } });
      })
    );
    return;
  }

  // JS & CSS Assets (app.js, style.css): Network First with Cache Fallback for instant update delivery
  if (url.includes('.js') || url.includes('.css')) {
    event.respondWith(
      (async () => {
        try {
          const networkResponse = await fetch(event.request, { cache: 'no-cache' });
          if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(event.request, networkResponse.clone());
            return networkResponse;
          }
        } catch (err) {
          // Offline or network error -> fallback to cache
        }
        const cache = await caches.open(CACHE_NAME);
        const cached = await cache.match(event.request);
        if (cached) return cached;
        const cachedIgnoreSearch = await cache.match(event.request, { ignoreSearch: true });
        if (cachedIgnoreSearch) return cachedIgnoreSearch;
        return new Response('', { status: 200, statusText: 'OK' });
      })()
    );
    return;
  }

  // Other Static Assets (Images, Fonts, Icons): Cache First with Background Update
  event.respondWith(
    caches.open(CACHE_NAME).then(async (cache) => {
      const cachedResponse = await cache.match(event.request, { ignoreSearch: true });
      if (cachedResponse) {
        if (navigator.onLine) {
          fetch(event.request).then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200) {
              cache.put(event.request, networkResponse);
            }
          }).catch(() => {});
        }
        return cachedResponse;
      }

      try {
        const networkResponse = await fetch(event.request);
        if (networkResponse && networkResponse.status === 200) {
          cache.put(event.request, networkResponse.clone());
        }
        return networkResponse;
      } catch (err) {
        return new Response('', { status: 200, statusText: 'OK' });
      }
    })
  );
});
