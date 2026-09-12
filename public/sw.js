/* =============================================================
 * کافی‌نت آنلاین — Service Worker (فاز ۱۴ PWA)
 * -------------------------------------------------------------
 * استراتژی‌ها:
 *   • پیش‌کش نصب        → صفحه آفلاین + مانیفست + آیکون‌ها
 *   • ناوبری (HTML)     → Network-First با مهلت ۵ ثانیه،
 *                         جایگزین: کش runtime، سپس صفحه آفلاین
 *   • فایل‌های استاتیک  → Stale-While-Revalidate (css/js/img/font)
 *   • فونت‌های گوگل     → Cache-First (تا وقتی آنلاین شد)
 *   • API (/api/*)      → هیچ‌وقت کش نمی‌شود؛ در آفلاین JSON 503
 *   • متدهای غیر GET    → دست‌نخورده (CSRF/سشن‌محور)
 * به‌روزرسانی: پیام SKIP_WAITING → skipWaiting → reload توسط pwa.js
 * v25: هندلر push با فرمت FCM (notification+data) + پیام SIMULATE_PUSH
 * v26: هندلر push برای هر سه سرویس (وب‌پوش داخلی/پوشر Beams/فایربیس)
 *       — همهٔ همان قالب {notification, data} را می‌فرستند + deep_link
 * ============================================================= */

const VERSION       = 'v1.1.5';
const STATIC_CACHE  = `cn-static-${VERSION}`;
const RUNTIME_CACHE = `cn-runtime-${VERSION}`;
const NAV_LIMIT     = 24;   // حداکثر HTML کش‌شده (LRU ساده)
const NAV_TIMEOUT   = 5000; // مهلت شبکه برای ناوبری

const PRECACHE_URLS = [
    '/offline',
    '/assets/js/offline.js?v=2',   // منطق صفحه آفلاین (CSP اسکریپت درون‌خطی را بلاک می‌کند)
    // مانیفست‌ها از روت سرو می‌شوند و در آفلاین نیاز نیست (فاز ۱۴ تفکیک‌شده)
    '/icons/icon-48.png',
    '/icons/icon-96.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/apple-touch-icon.png',
];

/* ---------- چرخه حیات ---------- */

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((k) => k !== STATIC_CACHE && k !== RUNTIME_CACHE)
                    .map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

/* (هندلر message — شامل SKIP_WAITING و SIMULATE_PUSH — انتهای فایل تعریف شده) */

/* ---------- کمک‌یاب‌ها ---------- */

/** تطبیق هوشمند کش: اول URL دقیق (شامل query نسخه‌دار ?v=)،
 *  بعد مسیر خالی — تا نسخه‌های قدیمی (مثل app.css?v=10) هرگز جای نسخه
 *  جدید را نگیرند؛ در عوض دارایی‌های پیش‌کش (آیکون‌ها/آفلاین) هم با
 *  query پیدا شوند. (رفع باگ ignoreSearch که cache-bust را خنثی می‌کرد) */
function smartMatch(cache, request) {
    return cache.match(request).then(function (hit) {
        if (hit) return hit;
        try {
            return cache.match(new URL(request.url).pathname);
        } catch (e) { return undefined; }
    });
}

function cacheable(res) {
    return res && res.ok && res.type === 'basic';
}

function timeoutFetch(request, ms) {
    return Promise.race([
        fetch(request),
        new Promise((_, reject) => setTimeout(() => reject(new Error('sw-nav-timeout')), ms)),
    ]);
}

async function trimCache(cacheName, maxEntries) {
    const cache = await caches.open(cacheName);
    const keys  = await cache.keys();
    if (keys.length > maxEntries) {
        await cache.delete(keys[0]);
        return trimCache(cacheName, maxEntries);
    }
}

/* ---------- استراتژی‌ها ---------- */

/** ناوبری: شبکه اول؛ در شکست → کش؛ در نبود → صفحه آفلاین */
async function networkFirstNavigation(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    try {
        const res = await timeoutFetch(request, NAV_TIMEOUT);
        if (cacheable(res)) {
            cache.put(request, res.clone());
            trimCache(RUNTIME_CACHE, NAV_LIMIT);
        }
        return res;
    } catch (err) {
        const cached = await cache.match(request, { ignoreSearch: true });
        if (cached) return cached;

        // صفحه آفلاین + حفظ مسیر اصلی برای دکمه «تلاش دوباره»
        const static_ = await caches.open(STATIC_CACHE);
        const offline = await static_.match('/offline');
        if (offline) {
            const url = new URL(request.url);
            if (url.pathname === '/offline') return offline;
            try {
                return Response.redirect('/offline?to=' + encodeURIComponent(url.pathname + url.search), 302);
            } catch (e) {
                return offline;
            }
        }

        return new Response('<h1 dir="rtl">آفلاین هستید</h1>', {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
}

/** استاتیک: نسخه کش فوری + به‌روزرسانی بی‌صدا پس‌زمینه
 *  (اول runtime، بعد precache استاتیک — تا دارایی‌های پیش‌کش آفلاین جواب بدهند) */
async function staleWhileRevalidate(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    let cached = await smartMatch(cache, request);

    if (!cached) {
        const pre = await caches.open(STATIC_CACHE);
        cached = await smartMatch(pre, request);
    }

    const refresh = fetch(request)
        .then((res) => {
            if (cacheable(res)) cache.put(request, res.clone());
            return res;
        })
        .catch(() => null);

    if (cached) return cached;

    const fresh = await refresh;
    return fresh || new Response('', { status: 504 });
}

/** فونت/دارایی خارجی: کش اول (به‌روزرسانی نسخه SW کافی است) */
async function cacheFirst(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;

    try {
        const res = await fetch(request);
        if (res.ok && (res.type === 'cors' || res.type === 'basic')) {
            cache.put(request, res.clone());
        }
        return res;
    } catch (err) {
        return new Response('', { status: 504 });
    }
}

/** API آفلاین: پاسخ استاندارد 503 تا JS اپ به‌خوبی مدیریتش کند */
function offlineApiBody() {
    return new Response(
        JSON.stringify({
            message: 'اتصال اینترنت قطع است؛ لطفاً پس از اتصال مجدد تلاش کنید.',
            offline: true,
        }),
        {
            status: 503,
            headers: { 'Content-Type': 'application/json; charset=utf-8' },
        }
    );
}

/* ---------- روتر fetch ---------- */

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return; // POST/PUT/DELETE → شبکه خام

    const url = new URL(request.url);

    // فونت‌های گوگل (فقط css/woff2)
    if (url.hostname.endsWith('fonts.googleapis.com') || url.hostname.endsWith('fonts.gstatic.com')) {
        event.respondWith(cacheFirst(request));
        return;
    }
    if (url.origin !== self.location.origin) return;

    // API سشن‌محور: هرگز کش نمی‌شود
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(request).catch(() => offlineApiBody())
        );
        return;
    }

    // ناوبری صفحه
    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    // دارایی‌های استاتیک خودی (css/js/تصویر/فونت/آیکون)
    if (/\.(css|js|mjs|png|jpe?g|webp|gif|svg|ico|woff2?|ttf|otf|mp3|mp4|webm)$/i.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }
    // بقیه (مثلاً XHR همان‌ج Origin خارج از /api) → دست‌نخورده
});

/* ---------- نوتیفیکیشن (Web Push — v26) ----------
 *
 * هر سه سرویس نوتیف دستگاه همان قالب را می‌فرستند:
 *   { notification: { title, body, … }, data: { url, event, tag } }
 *  • وب‌پوش داخلی (پیش‌فرض): سرور خودمان رمزنگاری RFC 8291 می‌کند
 *  • پوشر Beams: payload وبِ Beams (deep_link برای مقصد کلیک)
 *  • فایربیس: FCM HTTP v1
 * هر دو حالت (notification-payload و data-only) پشتیبانی می‌شود؛
 * کلیک روی نوتیف، پنجرهٔ موجود را فوکاس و به url هدف می‌برد.
 */

function parsePushPayload(raw) {
    raw = raw && typeof raw === 'object' ? raw : {};

    const note = (raw.notification && typeof raw.notification === 'object') ? raw.notification : {};
    const data = (raw.data && typeof raw.data === 'object') ? raw.data : {};

    // مقصد کلیک: data.url (خودی) یا deep_link مطلق (Beams)
    let url = data.url || raw.url || note.deep_link || '/app';
    if (url && /^https?:\/\/[^\/]/i.test(url)) {
        try {
            const u = new URL(url, self.location.origin);
            url = (u.origin === self.location.origin) ? (u.pathname + u.search) : '/app';
        } catch (e) { url = '/app'; }
    }

    return {
        title: note.title || raw.title || 'کافی‌نت آنلاین',
        body:  note.body  || raw.body  || 'اطلاعیهٔ جدیدی دارید.',
        url:   url,
        tag:   data.tag   || raw.tag   || 'cn-notif',
        event: data.event || raw.event || null,
    };
}

function showPushNotification(d) {
    return self.registration.showNotification(d.title, {
        body: d.body,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-96.png',
        dir: 'rtl',
        lang: 'fa',
        tag: d.tag,
        renotify: true,
        data: { url: d.url, event: d.event },
        vibrate: [80, 40, 80],
    });
}

self.addEventListener('push', (event) => {
    let raw = {};

    try {
        if (event.data) { raw = event.data.json(); }
    } catch (e) {
        try {
            const text = event.data ? event.data.text() : '';
            if (text) { raw = { body: text }; }
        } catch (e2) { /* noop */ }
    }

    const d = parsePushPayload(raw);

    event.waitUntil(showPushNotification(d));
});

/* شبیه‌سازی نوتیف از داخل صفحه (تست تنظیمات / E2E) — بدون رفت‌وبرگشت گوگل */
self.addEventListener('message', (event) => {
    const msg = event.data || {};

    if (msg.type === 'SIMULATE_PUSH') {
        const d = parsePushPayload(msg.payload || {});
        event.waitUntil(showPushNotification(d));
        return;
    }

    if (msg.type === 'SKIP_WAITING') { self.skipWaiting(); }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || '/app';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            // اولین پنجرهٔ هم‌خاستگاه فوکاس می‌شود و به مقصد می‌رود
            for (const client of list) {
                if ('focus' in client) {
                    client.focus();
                    if ('navigate' in client && target) client.navigate(target).catch(() => {});
                    return;
                }
            }
            return self.clients.openWindow(target);
        })
    );
});
