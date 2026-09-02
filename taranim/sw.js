// TARANIM PWA & OBS PRESENTER SERVICE WORKER (LOW DATA CONSUMING & OFFLINE FIRST)
const CACHE_NAME = 'taranim-pwa-v39';
const DATA_CACHE_NAME = 'taranim-data-v1';

// Core App Shell Only (Lightweight assets < 1MB)
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
      for (const asset of PRECACHE_ASSETS) {
        try {
          const existing = await appCache.match(asset, { ignoreSearch: true });
          if (!existing) {
            const response = await fetch(asset, { cache: 'no-cache' });
            if (response && response.ok) {
              await appCache.put(asset, response);
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
          const networkResponse = await fetch(event.request);
          if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(event.request, networkResponse.clone());
            return networkResponse;
          }
        } catch (error) {
          // Offline -> fallback to cache
        }

        const cache = await caches.open(CACHE_NAME);
        const urlObj = new URL(event.request.url);
        const path = urlObj.pathname;

        if (path.includes('present')) {
          const matchPresent = (await cache.match('./present.html', { ignoreSearch: true })) ||
                               (await cache.match('present.html', { ignoreSearch: true }));
          if (matchPresent) return matchPresent;
        }

        if (path.includes('remote')) {
          const matchRemote = (await cache.match('./remote.html', { ignoreSearch: true })) ||
                              (await cache.match('remote.html', { ignoreSearch: true }));
          if (matchRemote) return matchRemote;
        }

        if (path.includes('install')) {
          const matchInstall = (await cache.match('./install.html', { ignoreSearch: true })) ||
                               (await cache.match('install.html', { ignoreSearch: true }));
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

  // 3. JSON databases: Cache First (NO background re-download to save bandwidth)
  if (url.endsWith('.json') || url.includes('.json?')) {
    event.respondWith(
      (async () => {
        const isDataFile = url.includes('songs_catalog') || url.includes('bible_chapters');
        const targetCacheName = isDataFile ? DATA_CACHE_NAME : CACHE_NAME;
        
        const primaryCache = await caches.open(targetCacheName);
        let cachedResponse = await primaryCache.match(event.request, { ignoreSearch: true });
        
        if (!cachedResponse && isDataFile) {
          const appCache = await caches.open(CACHE_NAME);
          cachedResponse = await appCache.match(event.request, { ignoreSearch: true });
        }

        // Return immediately if already cached (save data!)
        if (cachedResponse && !event.request.cache.includes('reload')) {
          return cachedResponse;
        }

        // Otherwise fetch from network and cache
        try {
          const netRes = await fetch(event.request);
          if (netRes && netRes.status === 200) {
            const cacheToSave = await caches.open(targetCacheName);
            cacheToSave.put(event.request, netRes.clone());
            return netRes;
          }
        } catch (err) {}

        if (cachedResponse) return cachedResponse;
        return new Response(null, { status: 503, statusText: 'Service Unavailable Offline' });
      })()
    );
    return;
  }

  // 4. JS & CSS Assets: Network First with Cache Fallback
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

  // 5. Static Assets (Media, Images, Videos, Fonts): Strict Cache First (Zero background bandwidth consumption)
  event.respondWith(
    (async () => {
      const cache = await caches.open(CACHE_NAME);
      const cachedResponse = await cache.match(event.request, { ignoreSearch: true });
      if (cachedResponse && !event.request.cache.includes('reload')) {
        return cachedResponse;
      }

      try {
        const networkResponse = await fetch(event.request);
        if (networkResponse && networkResponse.status === 200) {
          cache.put(event.request, networkResponse.clone());
        }
        return networkResponse;
      } catch (err) {
        if (cachedResponse) return cachedResponse;
        return new Response('', { status: 200, statusText: 'OK' });
      }
    })()
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
