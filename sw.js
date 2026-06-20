// ╔══════════════════════════════════════════════════════════════╗
// ║  Sunday School PWA — Service Worker v14                     ║
// ╚══════════════════════════════════════════════════════════════╝
const SW_VERSION        = new URL(self.location.href).searchParams.get('v') || 'v14';
const CACHE_NAME        = `sunday-school-${SW_VERSION}`;
const SYNC_TAG          = 'sync-attendance';
const PERIODIC_SYNC_TAG = 'check-registrations';

// Only these API actions should be queued for background sync.
// Everything else (login, photo upload, settings) should fail normally when offline.
const QUEUEABLE_ACTIONS = ['submitAttendance', 'updateCoupons'];

const SHELL_URLS = [
    '/favicon.ico','/logo.png',
    '/manifest.json','/manifest.webmanifest',
    '/uncle/dashboard','/uncle/dashboard/','/uncle/dashboard/index.php',
    '/uncle/church',
    '/uncle/church/','/uncle/church/index.html',
    '/uncle/church/dashboard','/uncle/church/dashboard/',
    '/uncle/church/trips',
    '/uncle/church/trips/','/uncle/church/trips/index.html',
    '/uncle/trip',
    '/uncle/trip/','/uncle/trip/index.html',
    '/uncle/trip/filter',
    '/uncle/trip/filter/','/uncle/trip/filter/index.html',
    '/uncle/trip/points',
    '/uncle/trip/points/','/uncle/trip/points/index.html',
    'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=IBM+Plex+Mono:wght@400;600&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js',
];

// ── INSTALL ───────────────────────────────────────────────────
self.addEventListener('install', e => {
    self.skipWaiting();
    e.waitUntil(caches.open(CACHE_NAME).then(async cache => {
        for (const url of SHELL_URLS) {
            try {
                const response = await fetch(url, { cache: 'no-store' });
                if (response.ok) {
                    const isCookieCheck = await _isCookieCheckResponse(response);
                    if (!isCookieCheck) {
                        const copy = response.clone();
                        await cache.put(url, copy);
                    }
                }
            } catch (err) {
                // Ignore precache failures for individual URLs
            }
        }
    }));
});

// ── ACTIVATE ──────────────────────────────────────────────────
self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))))
            .then(() => self.clients.claim())
            .then(() => _registerPeriodicSync())
    );
});

// ── FETCH ─────────────────────────────────────────────────────
self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);

    // Bypass Service Worker for security verification resources
    // This prevents blocking aes.js (which contains decryption logic) and avoids redirect deadlocks
    if (
        url.pathname.includes('aes.js') || 
        url.pathname.includes('cookies.html') || 
        url.hostname.includes('ifastnet.com')
    ) {
        return;
    }

    const isSameOrigin = url.origin === self.location.origin;
    const isOfflineShellFriendly =
        isSameOrigin && (
            url.pathname.startsWith('/uncle/dashboard') ||
            url.pathname.startsWith('/uncle/church') ||
            url.pathname.startsWith('/uncle/trip')
        );
    const isSessionSensitive =
        isSameOrigin && (
            url.pathname === '/' ||
            url.pathname.includes('/login') ||
            url.pathname.includes('/uncle/dashboard') ||
            url.pathname.includes('/uncle/church') ||
            url.pathname.includes('/user/profile')
        );

    // Manifest must always return valid JSON (never plain "Offline")
    if (url.pathname === '/manifest.json' || url.pathname === '/manifest.webmanifest') {
        const manifestPath = url.pathname === '/manifest.webmanifest' ? '/manifest.webmanifest' : '/manifest.json';
        e.respondWith(
            (async () => {
                try {
                    const r = await fetch(e.request, { cache: 'no-store' });
                    if (r && r.ok) {
                        const isCookieCheck = await _isCookieCheckResponse(r);
                        if (!isCookieCheck) {
                            const copy = r.clone();
                            const cache = await caches.open(CACHE_NAME);
                            await cache.put(manifestPath, copy);
                        }
                    }
                    return r;
                } catch (err) {
                    const cached = await caches.match(manifestPath);
                    if (cached) return cached;
                    return new Response(JSON.stringify({
                        name: 'Sunday School',
                        short_name: 'Sunday School',
                        start_url: '/uncle/dashboard/',
                        display: 'standalone',
                        icons: []
                    }), {
                        headers: { 'Content-Type': 'application/manifest+json; charset=utf-8' },
                        status: 200
                    });
                }
            })()
        );
        return;
    }

    // Build version must always be fetched fresh so updates can be detected.
    if (url.pathname === '/version.php') {
        e.respondWith(fetch(e.request, { cache: 'no-store' }).catch(() => new Response(JSON.stringify({
            success: false,
            version: 'dev',
            source: 'offline'
        }), {
            headers: { 'Content-Type': 'application/json; charset=utf-8' },
            status: 503
        })));
        return;
    }

    // API / POST calls
    if (e.request.method === 'POST' || url.pathname.includes('api.php')) {
        e.respondWith(
            (async () => {
                try {
                    const r = await fetch(e.request.clone());
                    const isCookieCheck = await _isCookieCheckResponse(r);
                    if (isCookieCheck) {
                        // Force reload the client window(s) to let the cookie check complete
                        const clientsList = await self.clients.matchAll({ type: 'window' });
                        for (const client of clientsList) {
                            if (client.url) {
                                client.navigate(client.url).catch(() => {});
                            }
                        }
                        return new Response(JSON.stringify({
                            success: false,
                            offline: true,
                            cookie_check: true,
                            message: 'Checking cookies...'
                        }), {
                            status: 503,
                            headers: { 'Content-Type': 'application/json; charset=utf-8' }
                        });
                    }
                    return r;
                } catch (err) {
                    if (e.request.method === 'POST') {
                        // Only queue attendance/coupon actions — not login, photos, etc.
                        const queued = await _maybeQueueRequest(e.request.clone());
                        if (queued) {
                            return new Response(JSON.stringify({
                                success: false, offline: true,
                                message: 'محفوظ محلياً — سيُرفع عند الاتصال'
                            }), { headers: { 'Content-Type': 'application/json' } });
                        }
                    }
                    return new Response(JSON.stringify({
                        success: false, offline: true, message: 'غير متصل'
                    }), { headers: { 'Content-Type': 'application/json' }, status: 503 });
                }
            })()
        );
        return;
    }

    // Session-sensitive PHP pages must not be served cache-first, otherwise
    // switching churches can show the previous church's rendered dashboard.
    if (e.request.mode === 'navigate') {
        e.respondWith(
            (async () => {
                const remoteVersion = isOfflineShellFriendly ? await _getRemoteBuildVersion() : null;
                const versionMismatch = !!remoteVersion && remoteVersion !== SW_VERSION;

                if (isOfflineShellFriendly && versionMismatch) {
                    try {
                        const fresh = await fetch(e.request, { cache: 'no-store' });
                        if (await _isCacheableAppResponse(fresh)) {
                            const copy = fresh.clone();
                            caches.open(CACHE_NAME).then(c => c.put(e.request, copy)).catch(() => {});
                        }
                        return fresh;
                    } catch (_) { }
                }

                try {
                    // Force no-store for all navigations to prevent browser caching the cookie-check pages
                    const r = await fetch(e.request, { cache: 'no-store' });
                    if (isOfflineShellFriendly && await _isCacheableAppResponse(r)) {
                        const copy = r.clone();
                        caches.open(CACHE_NAME).then(c => c.put(e.request, copy)).catch(() => {});
                    }
                    return r;
                } catch (_) {
                    const cached = await _matchOfflineShell(e.request, url);
                    if (cached && (isOfflineShellFriendly || !isSessionSensitive)) return cached;
                    return new Response('<!doctype html><meta charset="utf-8"><title>Offline</title><body dir="rtl" style="font-family:sans-serif;padding:24px">غير متصل بالإنترنت</body>', {
                        status: 503,
                        headers: { 'Content-Type': 'text/html; charset=utf-8' }
                    });
                }
            })()
        );
        return;
    }

    // Never cache session-sensitive GETs either.
    if (isSessionSensitive && !isOfflineShellFriendly) {
        e.respondWith(fetch(e.request, { cache: 'no-store' }).catch(() => new Response('Offline', { status: 503 })));
        return;
    }

    // Static assets — cache-first
    e.respondWith(
        caches.match(e.request).then(cached => {
            if (cached) return cached;
            return (async () => {
                try {
                    const r = await fetch(e.request, { cache: 'no-store' });
                    const isCookieCheck = await _isCookieCheckResponse(r);
                    if (isCookieCheck) {
                        const clientsList = await self.clients.matchAll({ type: 'window' });
                        for (const client of clientsList) {
                            if (client.url) {
                                client.navigate(client.url).catch(() => {});
                            }
                        }
                        return new Response('Cookie check required', { status: 503 });
                    }
                    if (e.request.method === 'GET' && await _isCacheableAppResponse(r)) {
                        const cl = r.clone();
                        caches.open(CACHE_NAME).then(c => c.put(e.request, cl)).catch(() => {});
                    }
                    return r;
                } catch (err) {
                    return new Response('Offline', { status: 503 });
                }
            })();
        })
    );
});

async function _matchOfflineShell(request, url) {
    const direct = await caches.match(request, { ignoreSearch: true });
    if (direct) return direct;

    const sameOriginPath = url && url.origin === self.location.origin ? url.pathname : '';
    const fallbackCandidates = [];

    if (sameOriginPath.startsWith('/uncle/church/dashboard')) {
        fallbackCandidates.push('/uncle/church/', '/uncle/church/index.html');
    } else if (sameOriginPath.startsWith('/uncle/dashboard')) {
        fallbackCandidates.push('/uncle/dashboard/', '/uncle/dashboard/index.php', '/uncle/church/', '/uncle/church/index.html');
    } else if (sameOriginPath.startsWith('/uncle/church/trips')) {
        fallbackCandidates.push('/uncle/church/trips/', '/uncle/church/trips/index.html');
    } else if (sameOriginPath.startsWith('/uncle/church')) {
        fallbackCandidates.push('/uncle/church/', '/uncle/church/index.html');
    } else if (sameOriginPath.startsWith('/uncle/trip/filter')) {
        fallbackCandidates.push('/uncle/trip/filter/', '/uncle/trip/filter/index.html');
    } else if (sameOriginPath.startsWith('/uncle/trip/points')) {
        fallbackCandidates.push('/uncle/trip/points/', '/uncle/trip/points/index.html');
    } else if (sameOriginPath.startsWith('/uncle/trip')) {
        fallbackCandidates.push('/uncle/trip/', '/uncle/trip/index.html');
    }

    for (const candidate of fallbackCandidates) {
        const cached = await caches.match(candidate);
        if (cached) return cached;
    }

    return null;
}

async function _isCookieCheckResponse(response) {
    if (!response) return true;
    if (!response.ok) return false;

    const responseUrl = response.url || '';
    if (responseUrl.indexOf('ifastnet.com/cookies.html') !== -1 || responseUrl.indexOf('/cookies.html') !== -1) {
        return true;
    }

    const contentType = response.headers && response.headers.get('content-type') || '';
    if (/text\/html|javascript|json|plain/i.test(contentType) || responseUrl === '' || !contentType) {
        try {
            const copy = response.clone();
            const text = await copy.text();
            if (
                text.indexOf('__test') !== -1 ||
                text.indexOf('slowAES') !== -1 ||
                text.indexOf('aes.js') !== -1 ||
                text.indexOf('cookies.html') !== -1 ||
                text.indexOf('Cookies are not enabled') !== -1 ||
                text.indexOf('browser is not accepting cookies') !== -1
            ) {
                return true;
            }
        } catch (_) {}
    }
    return false;
}

async function _isCacheableAppResponse(response) {
    if (!response || !response.ok || await _isCookieCheckResponse(response)) return false;
    const copy = response.clone();
    const contentType = copy.headers.get('content-type') || '';

    if (!/text\/html/i.test(contentType)) return true;

    try {
        const text = await copy.text();
        return text.indexOf('sv101.ifastnet.com/cookies.html') === -1 &&
            text.indexOf('Cookies are not enabled') === -1 &&
            text.indexOf('It appears your browser is not accepting cookies') === -1 &&
            text.indexOf('document.cookie="__test="') === -1 &&
            text.indexOf('document.cookie = "__test="') === -1 &&
            text.indexOf('/aes.js') === -1 &&
            text.indexOf('?i=1') === -1;
    } catch (_) {
        return false;
    }
}

async function _getRemoteBuildVersion() {
    try {
        const resp = await fetch('/version.php', { cache: 'no-store' });
        if (!resp.ok) return null;
        const data = await resp.json();
        return data && data.version ? data.version : null;
    } catch (err) {
        return null;
    }
}

// ── BACKGROUND SYNC ───────────────────────────────────────────
self.addEventListener('sync', e => {
    if (e.tag === SYNC_TAG) e.waitUntil(_flushQueue());
});

// ── PERIODIC SYNC ─────────────────────────────────────────────
self.addEventListener('periodicsync', e => {
    if (e.tag === PERIODIC_SYNC_TAG) e.waitUntil(_checkNewRegistrations());
});

// ── PUSH ─────────────────────────────────────────────────────
self.addEventListener('push', e => {
    if (!e.data) return;
    let d;
    try { d = e.data.json(); } catch(_) { d = { title: 'مدارس الأحد', body: e.data.text() }; }

    const isReg = d.type === 'registration';
    const options = {
        body: d.body || '',
        icon: d.icon || '/logo.png',
        badge: '/logo.png',
        dir: 'rtl', lang: 'ar',
        tag: d.type || 'general',
        renotify: true,
        requireInteraction: isReg,
        data: { url: d.url || '/uncle/dashboard/', type: d.type, className: d.className }
    };
    if (isReg) {
        options.actions = [
            { action: 'open', title: 'عرض الطلب' },
            { action: 'dismiss', title: 'إغلاق' }
        ];
    }
    e.waitUntil(self.registration.showNotification(d.title || 'مدارس الأحد', options));
});

// ── NOTIFICATION CLICK ────────────────────────────────────────
self.addEventListener('notificationclick', e => {
    e.notification.close();
    const { url, type, className } = e.notification.data || {};
    if (e.action === 'dismiss') return;
    
    const isUserPath = url && url.includes('/user/');
    const targetSubstring = isUserPath ? '/user/' : '/uncle/';
    const defaultUrl = isUserPath ? '/user/' : '/uncle/dashboard/';

    e.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            const open = list.find(c => c.url.includes(targetSubstring));
            if (open) { 
                open.focus(); 
                open.postMessage({ type: 'NOTIFICATION_CLICK', notifType: type, className }); 
            } else {
                self.clients.openWindow(url || defaultUrl);
            }
        })
    );
});

// ── MESSAGE from page ─────────────────────────────────────────
self.addEventListener('message', e => {
    if (e.data?.type === 'SET_UNCLE_META') _idbSetMeta(e.data.meta).catch(() => {});
    if (e.data?.type === 'FLUSH_QUEUE') {
        _flushQueue().catch(() => {});
    }
});

// ── SELECTIVE QUEUE ──────────────────────────────────────────
// Reads the POST body to decide if this action should be queued.
// Returns true if queued, false if not (caller shows generic offline error).
async function _maybeQueueRequest(req) {
    try {
        const bodyText = await req.text();
        // Parse FormData-encoded body to find the 'action' field
        const action = _parseFormField(bodyText, 'action');
        if (!QUEUEABLE_ACTIONS.includes(action)) return false;

        const db = await _openDB();
        db.transaction('queue', 'readwrite').objectStore('queue').add({
            url: req.url, method: req.method, body: bodyText,
            headers: [...req.headers.entries()], ts: Date.now(), action
        });
        if (self.registration.sync) self.registration.sync.register(SYNC_TAG).catch(() => {});
        return true;
    } catch(err) { return false; }
}

// Extracts a field value from a URL-encoded FormData string
function _parseFormField(body, field) {
    try {
        const params = new URLSearchParams(body);
        return params.get(field) || '';
    } catch(_) { return ''; }
}

// ── FLUSH QUEUE ───────────────────────────────────────────────
async function _flushQueue() {
    const db = await _openDB();
    const records = await _idbGetAll(db.transaction('queue', 'readonly').objectStore('queue'));
    if (!records.length) return;

    let flushed = 0;
    for (const rec of records) {
        try {
            const r = await fetch(rec.url, {
                method: rec.method,
                body: rec.body,
                headers: new Headers(rec.headers || [])
            });
            if (r.ok) {
                db.transaction('queue', 'readwrite').objectStore('queue').delete(rec.id);
                flushed++;
            }
        } catch(err) {}
    }

    if (!flushed) return;

    // Tell open windows (they show a toast)
    const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    allClients.forEach(c => c.postMessage({ type: 'SYNC_COMPLETE', count: flushed }));

    // Show system notification only if app is closed / all tabs hidden
    const anyVisible = allClients.some(c => c.visibilityState === 'visible');
    if (!anyVisible) {
        self.registration.showNotification('مدارس الأحد — تم المزامنة ✅', {
            body: `رُفع ${flushed} تغيير محفوظ محلياً بنجاح`,
            icon: '/logo.png', badge: '/logo.png',
            dir: 'rtl', lang: 'ar',
            tag: 'sync-complete', renotify: true,
            data: { url: '/uncle/dashboard/', type: 'sync' }
        }).catch(() => {});
    }
}

// ── BACKGROUND REGISTRATION CHECK ────────────────────────────
async function _checkNewRegistrations() {
    try {
        const meta = await _idbGetMeta();
        if (!meta?.apiUrl || !meta?.uncleId) return;

        const fd = new FormData();
        fd.append('action', 'getPendingRegistrationsCount');
        fd.append('uncle_id', meta.uncleId);
        const resp = await fetch(meta.apiUrl, { method: 'POST', body: fd });
        if (!resp.ok) return;
        const data = await resp.json();

        const newCount  = parseInt(data.count  || 0);
        const prevCount = parseInt(meta.lastRegCount || 0);

        if (newCount > prevCount) {
            const diff = newCount - prevCount;
            await self.registration.showNotification('مدارس الأحد — طلب تسجيل جديد 📋', {
                body: diff === 1 ? 'طلب تسجيل جديد بانتظار مراجعتك'
                                 : `${diff} طلبات تسجيل جديدة بانتظار مراجعتك`,
                icon: '/logo.png', badge: '/logo.png',
                dir: 'rtl', lang: 'ar',
                tag: 'registration', renotify: true, requireInteraction: true,
                actions: [
                    { action: 'open',    title: 'عرض الطلبات' },
                    { action: 'dismiss', title: 'إغلاق' }
                ],
                data: { url: '/uncle/dashboard/', type: 'registration' }
            }).catch(() => {});
        }
        await _idbSetMeta({ ...meta, lastRegCount: newCount });
    } catch(err) {}
}

// ── PERIODIC SYNC REGISTRATION ────────────────────────────────
async function _registerPeriodicSync() {
    try {
        if ('periodicSync' in self.registration) {
            await self.registration.periodicSync.register(PERIODIC_SYNC_TAG, {
                minInterval: 15 * 60 * 1000
            });
        }
    } catch(err) {}
}

// ── IDB helpers ───────────────────────────────────────────────
function _openDB() {
    return new Promise((res, rej) => {
        const req = indexedDB.open('ss-queue', 2);
        req.onupgradeneeded = ev => {
            const db = ev.target.result;
            if (!db.objectStoreNames.contains('queue'))
                db.createObjectStore('queue', { keyPath: 'id', autoIncrement: true });
            if (!db.objectStoreNames.contains('meta'))
                db.createObjectStore('meta', { keyPath: 'key' });
        };
        req.onsuccess = ev => res(ev.target.result);
        req.onerror   = ev => rej(ev);
    });
}

function _idbGetAll(store) {
    return new Promise((res, rej) => {
        const req = store.getAll();
        req.onsuccess = e => res(e.target.result || []);
        req.onerror   = e => rej(e);
    });
}

async function _idbGetMeta() {
    try {
        const db = await _openDB();
        return await new Promise((res, rej) => {
            const req = db.transaction('meta', 'readonly').objectStore('meta').get('uncle');
            req.onsuccess = e => res(e.target.result?.value || null);
            req.onerror   = e => rej(e);
        });
    } catch(err) { return null; }
}

async function _idbSetMeta(value) {
    const db = await _openDB();
    return new Promise((res, rej) => {
        const tx = db.transaction('meta', 'readwrite');
        tx.objectStore('meta').put({ key: 'uncle', value });
        tx.oncomplete = res;
        tx.onerror    = rej;
    });
}
