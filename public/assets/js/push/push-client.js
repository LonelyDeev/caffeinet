/**
 * کافی‌نت آنلاین — نوتیف دستگاه (Web Push) — v26
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

    function ensureWebpushSubscription(force) {
        if (!cfg.vapidKey) {
            return Promise.reject(new Error('no-vapid'));
        }

        return swReady().then(function (reg) {
            return reg.pushManager.getSubscription().then(function (existing) {
                // اشتراک سالمِ هم‌کلید → استفادهٔ مجدد (بی‌صدا)
                if (existing && sameApplicationKey(existing, cfg.vapidKey)) {
                    return existing;
                }

                // اشتراک قدیمی با کلید دیگر → حذف و اشتراک تازه
                if (existing) {
                    return existing.unsubscribe().then(function () {
                        return reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlB64ToUint8Array(cfg.vapidKey)
                        });
                    });
                }

                if (!force && Notification.permission !== 'granted') {
                    return null; // فقط با اجازهٔ صریح اشتراک می‌سازیم
                }

                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlB64ToUint8Array(cfg.vapidKey)
                });
            });
        }).then(function (sub) {
            if (!sub) { return null; }

            var raw = sub.toJSON ? sub.toJSON() : sub;
            var key = raw.keys || {};

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
            .then(function () {
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
                } else if (m === 'no-vapid' || m === 'no-beams' || m === 'no-user') {
                    msg = 'پیکربندی سرویس نوتیف دستگاه کامل نیست؛ با مدیر سامانه هماهنگ کنید.';
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

    /* ---------- بوت ---------- */
    // اگر اجازه قبلاً داده شده، اشتراک/توکن را بی‌صدا تازه/ثابت نگه می‌داریم
    if (cfg.enabled && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        setTimeout(function () {
            ensureToken(true).catch(function () { /* بی‌صدا */ });
        }, 2500);
    }
})();
