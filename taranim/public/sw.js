const CACHE_NAME = 'sunday_school_taranim_v20260806_v32';

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
  './playlists.json',
  './song_scales_map.json',
  './bible_books_data.json'
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
    }).then(() => self.clients.claim())
  );
});

// FETCH STRATEGY: NETWORK-FIRST WITH TIMEOUT WHEN ONLINE, INSTANT CACHE WHEN OFFLINE
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);
  const isApi = url.pathname.includes('api.php') || url.pathname.includes('/api/') || url.search.includes('action=live');

  // Bypass API requests to let browser handle them directly
  if (isApi) {
    return;
  }

  const isCodeOrNav = event.request.mode === 'navigate' ||
                      url.pathname.endsWith('/taranim/') ||
                      url.pathname.endsWith('/taranim') ||
                      url.pathname.includes('index.html') ||
                      url.pathname.includes('obs.html') ||
                      url.pathname.includes('install.html') ||
                      url.pathname.includes('app.js') ||
                      url.pathname.includes('style.css') ||
                      url.pathname.includes('sw.js');

  // Application code & Navigation
  if (isCodeOrNav) {
    event.respondWith(
      (async () => {
        // Fast offline check — zero delay
        if (!self.navigator.onLine) {
          const cached = await caches.match(event.request, { ignoreSearch: true }) ||
                         await caches.match('./index.html', { ignoreSearch: true }) ||
                         await caches.match('./', { ignoreSearch: true });
          if (cached) return cached;
        }

        try {
          const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
          const timeoutId = controller ? setTimeout(() => controller.abort(), 1800) : null;
          const fetchOpts = { cache: 'no-cache' };
          if (controller) fetchOpts.signal = controller.signal;

          const networkRes = await fetch(event.request, fetchOpts);
          if (timeoutId) clearTimeout(timeoutId);

          if (networkRes && networkRes.status === 200) {
            const clone = networkRes.clone();
            const cache = await caches.open(CACHE_NAME);
            await cache.put(event.request, clone);
          }
          return networkRes;
        } catch (err) {
          const cached = await caches.match(event.request, { ignoreSearch: true }) ||
                         await caches.match('./index.html', { ignoreSearch: true }) ||
                         await caches.match('./', { ignoreSearch: true });
          if (cached) return cached;
          return new Response('<!doctype html><meta charset="utf-8"><title>Offline</title><body dir="rtl" style="font-family:sans-serif;padding:24px">غير متصل بالإنترنت</body>', {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' }
          });
        }
      })()
    );
    return;
  }

  // Cache-First strategy for large static data files (songs catalog, dictionary, images, fonts)
  event.respondWith(
    (async () => {
      const cached = await caches.match(event.request, { ignoreSearch: true });
      if (cached) return cached;

      if (!self.navigator.onLine) {
        return new Response('Offline', { status: 503 });
      }

      try {
        const networkRes = await fetch(event.request);
        if (networkRes && (networkRes.status === 200 || networkRes.status === 0)) {
          const clone = networkRes.clone();
          const cache = await caches.open(CACHE_NAME);
          await cache.put(event.request, clone);
        }
        return networkRes;
      } catch (err) {
        return new Response('Offline', { status: 503 });
      }
    })()
  );
});

// MESSAGE HANDLER FOR PRECACHING DATA WHEN ONLINE ONLY
self.addEventListener('message', (event) => {
  if (event.data?.type === 'PRECACHE_OFFLINE_DATA' || event.data?.type === 'VERIFY_AND_PRECACHE_DATA') {
    if (!self.navigator.onLine) return; // ONLINE ONLY!
    event.waitUntil(
      caches.open(CACHE_NAME).then(async (cache) => {
        for (const url of PRECACHE_URLS) {
          try {
            const match = await cache.match(url, { ignoreSearch: true });
            if (!match) {
              const res = await fetch(url, { cache: 'no-cache' });
              if (res && res.status === 200) {
                await cache.put(url, res);
              }
            }
          } catch (err) {}
        }
      })
    );
  }
});
