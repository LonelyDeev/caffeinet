/**
 * کافی‌نت آنلاین — نوتیف دستگاه (Web Push / Firebase) — v25
 * -------------------------------------------------------------
 * فایل مشترک همهٔ لایه‌ها (۴ پنل + اپ مشتری) — بدون Node / بدون بیلد.
 * پیکربندی CSP-safe از data-push-config روی تگ خودِ اسکریپت:
 *   { enabled, provider, senderId, apiKey, projectId, appId, hasDevice, registerUrl }
 *
 * مسئولیت‌ها:
 *  • enable(): گرفتن اجازه + ثبت SW + گرفتن توکن FCM + ارسال به سرور
 *  • تازه‌سازی خودکار توکن (onTokenRefresh) هنگام لود صفحه (اگر اجازه هست)
 *  • simulate(): نمایش نوتیف آزمایشی از طریق SIMULATE_PUSH به SW
 *    (برای «پیش‌نمایش» در تنظیمات)
 *
 * SDK فایربیس (compat) به‌صورت «local vendor» بارگذاری می‌شود —
 * نیازی به gstatic در زمان اجرا نیست (برای شبکهٔ ایران).
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
    cfg.vapidKey = cfg.vapidKey || null;

    var sdkLoading = null;
    var messaging = null;

    /* ---------- ابزارها ---------- */

    function toast(msg, kind) {
        try {
            if (window.App && typeof App.toast === 'function') { App.toast(msg, kind || 'info'); return; }
            if (window.CN && typeof CN.toast === 'function') { CN.toast(msg, kind || 'info'); return; }
        } catch (e) { /* noop */ }
    }

    /* چون فایل در هر دو سمت (پنل/اپ) لود می‌شود، فرستندهٔ توکن را انتخاب می‌کنیم */
    function postToken(token, platform) {
        if (window.CN && typeof CN.api === 'function') {
            // اپ مشتری — CN.api خودش Authorization Bearer را اضافه می‌کند
            CN.api('/push/token', {
                method: 'POST',
                data: { token: token, platform: platform },
                success: function () { /* noop */ },
                error: function () { toast('ثبت دستگاه روی سرور ناموفق بود.', 'error'); }
            });
            return;
        }

        if (window.App && cfg.registerUrl) {
            App.ajax(cfg.registerUrl, {
                method: 'POST',
                body: { token: token, platform: platform }
            }).then(function (res) {
                if (!res.ok) { toast('ثبت دستگاه روی سرور ناموفق بود.', 'error'); }
            }).catch(function () { toast('ثبت دستگاه روی سرور ناموفق بود.', 'error'); });
        }
    }

    function detectPlatform() {
        var ua = navigator.userAgent || '';
        if (/iPhone|iPad|iPod/i.test(ua)) { return 'ios'; }
        if (/Android/i.test(ua)) { return 'android'; }
        if (/Windows/i.test(ua)) { return 'windows'; }
        return 'web';
    }

    /* ---------- بارگذاری SDK (فقط هنگام نیاز) ---------- */

    function loadSdk() {
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

        // تازه‌سازی توکن (مثلاً بعد از پاک شدن کش مرورگر)
        try {
            messaging.onTokenRefresh(function () {
                ensureToken(true);
            });
        } catch (e) { /* noop */ }

        return messaging;
    }

    /* ---------- هسته ---------- */

    function ensureToken(force) {
        if (!cfg.enabled) { return Promise.reject(new Error('disabled')); }

        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return Promise.reject(new Error('unsupported'));
        }

        if (typeof Notification === 'undefined') {
            return Promise.reject(new Error('unsupported'));
        }

        var permission = Notification.permission;

        if (permission === 'denied') {
            return Promise.reject(new Error('denied'));
        }

        if (permission !== 'granted' && !force) {
            return Promise.reject(new Error('no-permission'));
        }

        return navigator.serviceWorker.ready
            .then(function (reg) {
                return loadSdk().then(function () {
                    var m = initMessaging();

                    var opts = { serviceWorkerRegistration: reg };
                    if (cfg.vapidKey) { opts.vapidKey = cfg.vapidKey; }

                    return m.getToken(opts);
                });
            })
            .then(function (token) {
                if (!token) { throw new Error('no-token'); }

                postToken(token, detectPlatform());
                cfg.hasDevice = true;
                notifyButtons();

                return token;
            });
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

                if (e && (e.message === 'denied' || (e.code || '').indexOf('messaging/permission') !== -1)) {
                    msg = 'اجازهٔ نمایش نوتیف رد شده است؛ از تنظیمات سایت در مرورگر، اعلان‌ها را مجاز کنید.';
                } else if (e && e.message === 'unsupported') {
                    msg = 'این مرورگر نوتیف دستگاه (Push) را پشتیبانی نمی‌کند.';
                } else if (e && (e.code || '').indexOf('messaging/unsupported-browser') !== -1) {
                    msg = 'این مرورگر/دستگاه نوتیف دستگاه را پشتیبانی نمی‌کند.';
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

    /** نمایش نوتیف آزمایشی محلی (بدون رفت‌وبرگشت به گوگل) — برای تست/E2E */
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
        simulate: simulate,
        bindButton: bindButton,
        onRegister: null // callback قابل ست‌کردن از صفحات
    };

    window.CNPush = CNPush;

    /* ---------- بوت ---------- */
    // اگر اجازه قبلاً داده شده، توکن را بی‌صدا تازه/ثابت نگه می‌داریم
    // (SW بعد از آپدیت ممکن است توکن تازه بسازد؛ این ثبت را از دست نمی‌دهیم)
    if (cfg.enabled && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        setTimeout(function () {
            ensureToken(true).catch(function () { /* بی‌صدا */ });
        }, 2500);
    }
})();
