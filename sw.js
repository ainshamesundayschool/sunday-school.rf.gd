// ╔══════════════════════════════════════════════════════════════╗
// ║  Sunday School PWA — Service Worker v4                      ║
// ╚══════════════════════════════════════════════════════════════╝
const CACHE_NAME        = 'sunday-school-v4';
const SYNC_TAG          = 'sync-attendance';
const PERIODIC_SYNC_TAG = 'check-registrations';

// Only these API actions should be queued for background sync.
// Everything else (login, photo upload, settings) should fail normally when offline.
const QUEUEABLE_ACTIONS = ['submitAttendance', 'updateCoupons'];

const SHELL_URLS = [
    '/uncle/dashboard/','/favicon.ico','/logo.png',
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
    e.waitUntil(caches.open(CACHE_NAME).then(cache =>
        Promise.allSettled(SHELL_URLS.map(url => cache.add(url).catch(() => {})))
    ));
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

    // API / POST calls
    if (e.request.method === 'POST' || url.pathname.includes('api.php')) {
        e.respondWith(
            fetch(e.request.clone()).catch(async () => {
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
            })
        );
        return;
    }

    // Navigation — cache-first with background refresh
    if (e.request.mode === 'navigate') {
        e.respondWith(
            caches.match(e.request).then(cached => {
                if (cached) {
                    fetch(e.request).then(r => {
                        if (r && r.ok) caches.open(CACHE_NAME).then(c => c.put(e.request, r));
                    }).catch(() => {});
                    return cached;
                }
                return fetch(e.request).then(r => {
                    if (r && r.ok) { const cl = r.clone(); caches.open(CACHE_NAME).then(c => c.put(e.request, cl)); }
                    return r;
                }).catch(() => caches.match('/uncle/dashboard/'));
            })
        );
        return;
    }

    // Static assets — cache-first
    e.respondWith(
        caches.match(e.request).then(cached => {
            if (cached) return cached;
            return fetch(e.request).then(r => {
                if (r && r.ok && e.request.method === 'GET') {
                    const cl = r.clone(); caches.open(CACHE_NAME).then(c => c.put(e.request, cl));
                }
                return r;
            }).catch(() => new Response('Offline', { status: 503 }));
        })
    );
});

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
    e.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            const open = list.find(c => c.url.includes('/uncle/'));
            if (open) { open.focus(); open.postMessage({ type: 'NOTIFICATION_CLICK', notifType: type, className }); }
            else self.clients.openWindow(url || '/uncle/dashboard/');
        })
    );
});

// ── MESSAGE from page ─────────────────────────────────────────
self.addEventListener('message', e => {
    if (e.data?.type === 'SET_UNCLE_META') _idbSetMeta(e.data.meta).catch(() => {});
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