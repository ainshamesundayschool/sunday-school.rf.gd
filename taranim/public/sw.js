// TARANIM PWA & OBS PRESENTER SERVICE WORKER (OFFLINE FIRST WITH PERMANENT DATA CACHE)
const CACHE_NAME = 'taranim-pwa-v38';
const DATA_CACHE_NAME = 'taranim-data-v1';

const PRECACHE_ASSETS = [
  './',
  './index.html',
  './present.html',
  './remote.html',
  './install.html',
  './style.css',
  './app.js',
  './manifest.json',
  './manifest.webmanifest',
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
  './templates.json',
  './playlists.json',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
  'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js',
  'https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js',
  'https://cdn.jsdelivr.net/npm/@fontsource/playpen-sans-arabic@5.3.0/index.css'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    (async () => {
      const appCache = await caches.open(CACHE_NAME);
      const dataCache = await caches.open(DATA_CACHE_NAME);

      for (const asset of PRECACHE_ASSETS) {
        try {
          const isLargeData = asset.includes('songs_catalog.json') || asset.includes('bible_chapters_data.json');
          const targetCache = isLargeData ? dataCache : appCache;

          // Check if already cached
          const existing = await targetCache.match(asset, { ignoreSearch: true });
          if (!existing) {
            const response = await fetch(asset, { cache: 'no-cache' });
            if (response && response.ok) {
              await targetCache.put(asset, response);
            }
          }
        } catch (e) {
          console.warn('[SW] Precache skipped for:', asset);
        }
      }
    })()
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME && key !== DATA_CACHE_NAME && (key.startsWith('taranim-pwa-') || key.startsWith('sunday_school_taranim_'))) {
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

  // 1. Bypass live API calls when offline (handled via BroadcastChannel / localStorage)
  if (url.includes('api.php?action=live') || url.includes('/api/live')) {
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

  // 2. Navigation requests (HTML pages): Network First, fallback to cached HTML
  if (event.request.mode === 'navigate' || (event.request.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(
      (async () => {
        try {
          const networkPromise = fetch(event.request);
          const timeoutPromise = new Promise((_, reject) => setTimeout(() => reject(new Error('Network timeout')), 2000));
          const networkResponse = await Promise.race([networkPromise, timeoutPromise]);
          
          if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(event.request, networkResponse.clone());
            return networkResponse;
          }
        } catch (err) {
          // Network failed or timed out -> check caches
        }

        const cache = await caches.open(CACHE_NAME);
        const cached = await cache.match(event.request, { ignoreSearch: true });
        if (cached) return cached;

        if (url.includes('present.html')) {
          const matchPresent = await cache.match('./present.html', { ignoreSearch: true });
          if (matchPresent) return matchPresent;
        }

        if (url.includes('remote.html')) {
          const matchRemote = await cache.match('./remote.html', { ignoreSearch: true });
          if (matchRemote) return matchRemote;
        }

        if (url.includes('install.html')) {
          const matchInstall = await cache.match('./install.html', { ignoreSearch: true });
          if (matchInstall) return matchInstall;
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

  // 3. JSON databases (songs_catalog, bible data, templates, dictionary): Cache First with Background Revalidation
  if (url.endsWith('.json') || url.includes('.json?')) {
    event.respondWith(
      (async () => {
        const isDataFile = url.includes('songs_catalog') || url.includes('bible_chapters');
        const targetCacheName = isDataFile ? DATA_CACHE_NAME : CACHE_NAME;
        
        const primaryCache = await caches.open(targetCacheName);
        let cachedResponse = await primaryCache.match(event.request, { ignoreSearch: true });
        
        if (!cachedResponse && isDataFile) {
          // Fallback check app cache
          const appCache = await caches.open(CACHE_NAME);
          cachedResponse = await appCache.match(event.request, { ignoreSearch: true });
        }

        // Background update if online
        const fetchPromise = fetch(event.request).then(async (networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const cacheToSave = await caches.open(targetCacheName);
            cacheToSave.put(event.request, networkResponse.clone());
          }
          return networkResponse;
        }).catch(() => null);

        if (cachedResponse) {
          // Don't wait for background fetch, return cached immediately
          return cachedResponse;
        }

        // If not cached, wait for network fetch
        const netRes = await fetchPromise;
        if (netRes && netRes.status === 200) {
          return netRes;
        }

        // Return error response rather than empty object so caller can fallback to IndexedDB
        return new Response(null, { status: 503, statusText: 'Service Unavailable Offline' });
      })()
    );
    return;
  }

  // 4. JS & CSS Assets (app.js, style.css): Network First with Cache Fallback for instant update delivery
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

  // 5. Other Static Assets (Images, Fonts, Media, Icons): Cache First with Background Update
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

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  if (event.data && event.data.type === 'PURGE_CACHE') {
    caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))));
  }
});
