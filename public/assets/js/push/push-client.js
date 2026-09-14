/**
 * کافی‌نت آنلاین — نوتیف دستگاه (Web Push) — v26.1
 * -------------------------------------------------------------
 * فایل مشترک همهٔ لایه‌ها (۴ پنل + اپ مشتری) — بدون Node / بدون بیلد.
 * پیکربندی CSP-safe از data-push-config روی تگ خودِ اسکریپت:
 *   { enabled, provider, hasDevice, registerUrl, … }
 *
 * سه سرویس پشتیبانی می‌شود (انتخاب مدیر کل در تنظیمات → اعلان‌ها):
 *   • default  وب‌پوش داخلی — pushManager.subscribe با کلید VAPID
 *              سامانه (بدون SDK؛ endpoint + p256dh/auth به سرور)
 *   • pusher   Pusher Beams — SDK محلی + interest کاربر
 *   • firebase FCM گوگل — SDK compat محلی + توکن FCM
 *
 *  • enable(): گرفتن اجازه + ثبت SW + اشتراک/توکن + ارسال به سرور
 *  • تازه‌سازی خودکار هنگام لود صفحه (اگر اجازه هست)
 *  • simulate(): نمایش نوتیف آزمایشی از طریق SIMULATE_PUSH به SW
 *
 * v26.1 — رفع باگ تکرار توکن در موبایل:
 *  برخی مرورگرهای موبایل applicationServerKey را null برمی‌گردانند؛
 *  مقایسهٔ کلید در هر لود شکست می‌خورد و اشتراک هر بار از نو ساخته
 *  می‌شد (یک رکورد push_tokens در هر رفرش!). حالا آخرین اشتراک
 *  ساخته‌شده در localStorage نشانه‌گذاری می‌شود (cnPushSub):
 *  تا وقتی کلید VAPID همان است، اشتراک موجود «بازاستفاده» می‌شود —
 *  و توکن‌های مرده/جایگزین‌شده از سرور هم پاک می‌شوند.
 *
 * global CNPush
 */
(function () {
    'use strict';

    var script = document.currentScript
        || (function () { var all = document.querySelectorAll('script[data-push-config]'); return all[all.length - 1]; })();

    var cfg = {};

    try {
        cfg = JSON.parse(script ? (script.getAttribute('data-push-config') || '{}') : '{}') || {};
    } catch (e) { cfg = {}; }

    cfg.enabled = !!cfg.enabled;
    cfg.hasDevice = !!cfg.hasDevice;
    cfg.registerUrl = cfg.registerUrl || null;
    cfg.provider = cfg.provider || 'off';

    var sdkLoading = null;      // فایربیس
    var beamsLoading = null;    // پوشر Beams
    var messaging = null;

    /* ---------- ابزارها ---------- */

    function toast(msg, kind) {
        try {
            if (window.App && typeof App.toast === 'function') { App.toast(msg, kind || 'info'); return; }
            if (window.CN && typeof CN.toast === 'function') { CN.toast(msg, kind || 'info'); return; }
        } catch (e) { /* noop */ }
    }

    /** ثبت اشتراک/توکن روی سرور (هم مسیر پنل‌ها هم اپ مشتری) */
    function postRegistration(body) {
        if (window.CN && typeof CN.api === 'function') {
            // اپ مشتری — CN.api خودش Authorization Bearer را اضافه می‌کند
            CN.api('/push/token', {
                method: 'POST',
                data: body,
                success: function () { /* noop */ },
                error: function () { toast('ثبت دستگاه روی سرور ناموفق بود.', 'error'); }
            });
            return;
        }

        if (window.App && cfg.registerUrl) {
            App.ajax(cfg.registerUrl, { method: 'POST', body: body })
                .then(function (res) {
                    if (!res.ok) { toast('ثبت دستگاه روی سرور ناموفق بود.', 'error'); }
                })
                .catch(function () { toast('ثبت دستگاه روی سرور ناموفق بود.', 'error'); });
        }
    }

    function detectPlatform() {
        var ua = navigator.userAgent || '';
        if (/iPhone|iPad|iPod/i.test(ua)) { return 'ios'; }
        if (/Android/i.test(ua)) { return 'android'; }
        if (/Windows/i.test(ua)) { return 'windows'; }
        return 'web';
    }

    /** حذف توکن مرده از سرور (وقتی اشتراک جایش را به توکن جدید می‌دهد
     *  یا مرورگر اشتراک را از دست داده) — هر دو مسیر پنل/اپ پشتیبانی می‌شود */
    function postUnregistration(token) {
        if (!token) { return; }

        if (window.CN && typeof CN.api === 'function') {
            CN.api('/push/token', {
                method: 'DELETE',
                data: { token: token },
                success: function () { /* noop */ },
                error: function () { /* بی‌صدا — بعداً send منقضی می‌کندش */ }
            });
            return;
        }

        if (window.App && cfg.registerUrl) {
            App.ajax(cfg.registerUrl, { method: 'DELETE', body: { token: token } })
                .catch(function () { /* بی‌صدا */ });
        }
    }

    function urlB64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
        return out;
    }

    /** آیا کلید VAPID فعلی با کلید اشتراک موجود هم‌خوان است؟ */
    function sameApplicationKey(sub, vapidKey) {
        try {
            var appKey = new Uint8Array(sub.applicationServerKey);
            var want = urlB64ToUint8Array(vapidKey);
            if (appKey.length !== want.length) { return false; }
            for (var i = 0; i < want.length; i++) {
                if (appKey[i] !== want[i]) { return false; }
            }
            return true;
        } catch (e) { return false; }
    }

    function swReady() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return Promise.reject(new Error('unsupported'));
        }
        return navigator.serviceWorker.ready;
    }

    function requestPermission() {
        if (typeof Notification === 'undefined') {
            return Promise.reject(new Error('unsupported'));
        }
        if (Notification.permission === 'granted') {
            return Promise.resolve('granted');
        }
        if (Notification.permission === 'denied') {
            return Promise.reject(new Error('denied'));
        }
        return Notification.requestPermission();
    }

    /* ---------- پرووایدر ۱: وب‌پوش داخلی (پیش‌فرض) ---------- */

    /* نشانهٔ آخرین اشتراک در این مرورگر (رفع باگ تکرار توکن موبایل):
     * { vapid: کلید VAPID ساخت اشتراک, endpoint: آدرس اشتراک, at: زمان } */
    var SUB_STATE_KEY = 'cnPushSub';

    function loadSubState() {
        try {
            var s = JSON.parse(localStorage.getItem(SUB_STATE_KEY) || 'null');
            return (s && typeof s === 'object') ? s : {};
        } catch (e) { return {}; }
    }

    function saveSubState(state) {
        try { localStorage.setItem(SUB_STATE_KEY, JSON.stringify(state)); } catch (e) { /* noop */ }
    }

    function clearSubState() {
        try { localStorage.removeItem(SUB_STATE_KEY); } catch (e) { /* noop */ }
    }

    function subscribeFresh(reg) {
        return reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlB64ToUint8Array(cfg.vapidKey)
        });
    }

    function ensureWebpushSubscription(force) {
        if (!cfg.vapidKey) {
            return Promise.reject(new Error('no-vapid'));
        }

        return swReady().then(function (reg) {
            return reg.pushManager.getSubscription().then(function (existing) {
                var state = loadSubState();

                // ۱) اشتراک موجود + کلید VAPID همان است → بازاستفادهٔ بی‌صدا.
                //    (مقایسهٔ applicationServerKey فقط «کمکی» است؛ در برخی
                //    مرورگرهای موبایل null است و هرگز نباید ملاک باشد)
                if (existing) {
                    if ((state.vapid && state.vapid === cfg.vapidKey)
                        || (!state.vapid && sameApplicationKey(existing, cfg.vapidKey))) {
                        return existing;
                    }

                    // ۲) کلید VAPID عوض شده (یا نشانه‌ای نیست و کلید هم‌خوان نیست)
                    //    → فقط «یک بار» اشتراک تازه + حذف رکورد قدیمی از سرور
                    var oldEndpoint = existing.endpoint;

                    return existing.unsubscribe().then(function () {
                        return subscribeFresh(reg);
                    }).then(function (sub) {
                        if (oldEndpoint && (!sub || sub.endpoint !== oldEndpoint)) {
                            postUnregistration(oldEndpoint);
                        }
                        return sub;
                    });
                }

                // ۳) مرورگر اشتراک را ندارد ولی سرور رکورد دارد (اشتراک از دست
                //    رفته — مثلاً پاک‌شدن داده‌های سایت) → رکورد مرده پاک شود
                if (state.endpoint) {
                    postUnregistration(state.endpoint);
                    clearSubState();
                }

                if (!force && Notification.permission !== 'granted') {
                    return null; // فقط با اجازهٔ صریح اشتراک می‌سازیم
                }

                return subscribeFresh(reg);
            });
        }).then(function (sub) {
            if (!sub) { return null; }

            var raw = sub.toJSON ? sub.toJSON() : sub;
            var key = raw.keys || {};

            // نشانه‌گذاری اشتراک جاری — تا لودهای بعدی بازاستفاده شوند
            saveSubState({
                vapid: cfg.vapidKey,
                endpoint: sub.endpoint,
                at: Date.now()
            });

            postRegistration({
                token: sub.endpoint,
                provider: 'webpush',
                p256dh: key.p256dh || null,
                auth: key.auth || null,
                platform: detectPlatform()
            });

            cfg.hasDevice = true;
            notifyButtons();

            return sub;
        });
    }

    /* ---------- پرووایدر ۲: پوشر Beams ---------- */

    function loadBeamsSdk() {
        if (window.PusherPushNotifications) {
            return Promise.resolve();
        }

        if (beamsLoading) { return beamsLoading; }

        var base = (window.App && typeof App.url === 'function')
            ? App.url('/assets/js/vendor/')
            : '/assets/js/vendor/';

        beamsLoading = new Promise(function (resolve, reject) {
            var el = document.createElement('script');
            el.src = base + 'pusher-beams.js';
            el.async = true;
            el.onload = resolve;
            el.onerror = function () {
                beamsLoading = null;
                reject(new Error('beams-sdk-load'));
            };
            document.head.appendChild(el);
        });

        return beamsLoading;
    }

    function ensureBeams(force) {
        if (!cfg.beamsInstanceId) {
            return Promise.reject(new Error('no-beams'));
        }

        if (!cfg.userId) {
            return Promise.reject(new Error('no-user'));
        }

        return swReady().then(function (reg) {
            return loadBeamsSdk().then(function () {
                var client = new PusherPushNotifications.Client({
                    instanceId: cfg.beamsInstanceId,
                    serviceWorkerRegistration: reg
                });

                return client.start().then(function () {
                    // interest اختصاصی این کاربر — سرور به همین interest منتشر می‌کند
                    return client.addInterest('user-' + cfg.userId).then(function () {
                        return client.getDeviceId();
                    });
                }).then(function (deviceId) {
                    if (deviceId) {
                        postRegistration({
                            token: 'beams:' + deviceId,
                            provider: 'pusher',
                            platform: detectPlatform()
                        });
                    }

                    cfg.hasDevice = true;
                    notifyButtons();

                    return deviceId;
                });
            });
        });
    }

    /* ---------- پرووایدر ۳: فایربیس (FCM) ---------- */

    function loadFirebaseSdk() {
        if (window.firebase && window.firebase.messaging) {
            return Promise.resolve();
        }

        if (sdkLoading) { return sdkLoading; }

        function loadScript(src) {
            return new Promise(function (resolve, reject) {
                var el = document.createElement('script');
                el.src = src;
                el.async = true;
                el.onload = resolve;
                el.onerror = function () { reject(new Error('load fail: ' + src)); };
                document.head.appendChild(el);
            });
        }

        var base = (window.App && typeof App.url === 'function')
            ? App.url('/assets/js/vendor/')
            : '/assets/js/vendor/';

        sdkLoading = loadScript(base + 'firebase-app-compat.js')
            .then(function () { return loadScript(base + 'firebase-messaging-compat.js'); })
            .catch(function (e) {
                sdkLoading = null;
                throw e;
            });

        return sdkLoading;
    }

    function initMessaging() {
        if (messaging) { return messaging; }

        messaging = firebase.initializeApp({
            apiKey: cfg.apiKey,
            authDomain: (cfg.projectId || '') + '.firebaseapp.com',
            projectId: cfg.projectId,
            messagingSenderId: cfg.senderId,
            appId: cfg.appId
        }).messaging();

        try {
            messaging.onTokenRefresh(function () {
                ensureToken(true);
            });
        } catch (e) { /* noop */ }

        return messaging;
    }

    function ensureFcmToken(force) {
        return swReady().then(function (reg) {
            return loadFirebaseSdk().then(function () {
                var m = initMessaging();

                var opts = { serviceWorkerRegistration: reg };
                if (cfg.vapidKey) { opts.vapidKey = cfg.vapidKey; }

                return m.getToken(opts);
            });
        }).then(function (token) {
            if (!token) { throw new Error('no-token'); }

            postRegistration({
                token: token,
                provider: 'firebase',
                platform: detectPlatform()
            });

            cfg.hasDevice = true;
            notifyButtons();

            return token;
        });
    }

    /* ---------- هستهٔ مشترک ---------- */

    function ensureToken(force) {
        if (!cfg.enabled) { return Promise.reject(new Error('disabled')); }

        if (typeof Notification === 'undefined') {
            return Promise.reject(new Error('unsupported'));
        }

        if (Notification.permission === 'denied') {
            return Promise.reject(new Error('denied'));
        }

        if (Notification.permission !== 'granted' && !force) {
            return Promise.reject(new Error('no-permission'));
        }

        switch (cfg.provider) {
            case 'default':            // حالت پیش‌فرض = وب‌پوش داخلی
            case 'webpush':
                return ensureWebpushSubscription(force);
            case 'pusher':
                return ensureBeams(force);
            case 'firebase':
                return ensureFcmToken(force);
            default:
                return Promise.reject(new Error('disabled'));
        }
    }

    /* ---------- دکمه‌های «فعال‌سازی نوتیف دستگاه» ---------- */

    var boundButtons = [];

    function bindButton(btn) {
        if (!btn) { return; }
        boundButtons.push(btn);
        paintButton(btn);
        btn.addEventListener('click', function () { enable(btn); });
    }

    function notifyButtons() {
        boundButtons.forEach(function (btn) { paintButton(btn); });
    }

    function paintButton(btn) {
        if (!btn) { return; }

        var icon = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>';

        if (cfg.hasDevice) {
            btn.innerHTML = icon + '<span>نوتیف این دستگاه فعال است ✓</span>';
            btn.classList.add('cn-push-on');
            btn.classList.remove('cn-push-busy');
            btn.disabled = false;
            return;
        }

        if (!cfg.enabled) {
            btn.innerHTML = icon + '<span>نوتیف دستگاه (غیرفعال)</span>';
            btn.classList.remove('cn-push-on', 'cn-push-busy');
            btn.disabled = false;
            return;
        }

        btn.innerHTML = icon + '<span>فعال‌سازی نوتیف دستگاه</span>';
        btn.classList.remove('cn-push-on');
        btn.classList.remove('cn-push-busy');
        btn.disabled = false;
    }

    /* ---------- رابط عمومی ---------- */

    function enable(btn) {
        if (!cfg.enabled) {
            toast('نوتیف دستگاه توسط مدیریت سامانه غیرفعال است.');
            return;
        }

        if (btn) {
            btn.classList.add('cn-push-busy');
            btn.disabled = true;
        }

        requestPermission()
            .then(function (p) {
                if (p !== 'granted') {
                    throw new Error('denied');
                }
                return ensureToken(true);
            })
            .then(function (result) {
                if (result && result.endpoint) { tellSwVapidKey(); }
                toast('نوتیف این دستگاه فعال شد؛ از این پس حتی وقتی برنامه بسته است، خبرها می‌رسد. ✅', 'success');
                if (typeof CNPush.onRegister === 'function') { CNPush.onRegister(); }
            })
            .catch(function (e) {
                var msg = 'فعال‌سازی نوتیف دستگاه ناموفق بود.';
                var m = (e && e.message) || '';

                if (m === 'denied' || (e && (e.code || '').indexOf('messaging/permission') !== -1)) {
                    msg = 'اجازهٔ نمایش نوتیف رد شده است؛ از تنظیمات سایت در مرورگر، اعلان‌ها را مجاز کنید.';
                } else if (m === 'unsupported' || m === 'beams-sdk-load' || (e && (e.code || '').indexOf('messaging/unsupported-browser') !== -1)) {
                    msg = 'این مرورگر/دستگاه نوتیف دستگاه را پشتیبانی نمی‌کند.';

                    // آیفون: نوتیف فقط داخل اپ نصب‌شده (Add to Home Screen) کار می‌کند
                    var ua = navigator.userAgent || '';
                    var iOS = /iPhone|iPad|iPod/i.test(ua);
                    var standalone = navigator.standalone === true
                        || window.matchMedia('(display-mode: standalone)').matches;
                    if (iOS && !standalone) {
                        msg = 'در آیفون، نوتیف دستگاه فقط در «اپ نصب‌شده» کار می‌کند؛ از منوی اشتراک‌گذاری سافاری، «افزودن به صفحهٔ اصلی» را بزنید و از داخل همان اپ فعال کنید.';
                    }
                } else if (m === 'no-vapid' || m === 'no-beams' || m === 'no-user') {
                    msg = 'پیکربندی سرویس نوتیف دستگاه کامل نیست؛ با مدیر سامانه هماهنگ کنید.';
                } else if (e && e.name === 'AbortError') {
                    msg = 'کاربر یا مرورگر عملیات اشتراک نوتیف را لغو کرد؛ دوباره تلاش کنید.';
                }

                toast(msg, 'error');
            })
            .finally(function () {
                if (btn) {
                    btn.classList.remove('cn-push-busy');
                    btn.disabled = false;
                    paintButton(btn);
                }
            });
    }

    /** نمایش نوتیف آزمایشی محلی (بدون رفت‌وبرگشت به سرور) — برای تست/E2E */
    function simulate(payload) {
        if (!('serviceWorker' in navigator)) {
            return Promise.reject(new Error('unsupported'));
        }

        return navigator.serviceWorker.ready.then(function (reg) {
            if (reg.active) {
                reg.active.postMessage({
                    type: 'SIMULATE_PUSH',
                    payload: payload || {}
                });
                return true;
            }
            return false;
        });
    }

    var CNPush = {
        cfg: cfg,
        enable: enable,
        ensureToken: ensureToken,
        simulate: simulate,
        bindButton: bindButton,
        onRegister: null // callback قابل ست‌کردن از صفحات
    };

    window.CNPush = CNPush;

    /* ---------- همگام‌سازی با Service Worker (v26.1) ---------- */

    /** ارسال کلید VAPID به SW تا در کش STATIC ذخیره شود — برای تجدید
     *  خودکار اشتراک در رویداد pushsubscriptionchange (مرورگر بسته است) */
    function tellSwVapidKey() {
        if (!cfg.vapidKey || !('serviceWorker' in navigator)) { return; }

        try {
            navigator.serviceWorker.ready.then(function (reg) {
                if (reg.active) {
                    reg.active.postMessage({
                        type: 'SAVE_PUSH_VAPID',
                        vapidKey: cfg.vapidKey
                    });
                }
            }).catch(function () { /* noop */ });
        } catch (e) { /* noop */ }
    }

    // گوش دادن به تجدید اشتراک از سمت SW (اشتراک قبلی منقضی شده بود)
    if ('serviceWorker' in navigator) {
        try {
            navigator.serviceWorker.addEventListener('message', function (event) {
                var msg = (event && event.data) || {};

                if (msg.type === 'PUSH_SUBSCRIPTION_RENEWED' && msg.endpoint) {
                    saveSubState({
                        vapid: cfg.vapidKey,
                        endpoint: msg.endpoint,
                        at: Date.now()
                    });

                    postRegistration({
                        token: msg.endpoint,
                        provider: 'webpush',
                        p256dh: (msg.keys && msg.keys.p256dh) || null,
                        auth: (msg.keys && msg.keys.auth) || null,
                        platform: detectPlatform()
                    });
                }
            });
        } catch (e) { /* noop */ }
    }

    /* ---------- بوت ---------- */
    // اگر اجازه قبلاً داده شده، اشتراک/توکن را بی‌صدا تازه/ثابت نگه می‌داریم
    if (cfg.enabled && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        setTimeout(function () {
            ensureToken(true)
                .then(function (sub) {
                    if (sub && sub.endpoint) { tellSwVapidKey(); }
                })
                .catch(function () { /* بی‌صدا */ });
        }, 2500);
    }
})();
