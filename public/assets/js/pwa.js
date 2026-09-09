/* =============================================================
 * کافی‌نت آنلاین — PWA Runtime (ثبت SW + بنر نصب + به‌روزرسانی)
 * -------------------------------------------------------------
 * بدون وابستگی (jQuery خیر) — روی همه layouts کار می‌کند:
 * landing / اپ مشتری / پنل‌ها / صفحات لاگین
 * همه استایل‌ها inline و prefixed با cnpwa- تا با هیچ تمی تداخل نکند
 * ============================================================= */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) return;

    var DISMISS_KEY  = 'cnpwa-banner-dismissed';
    var DISMISS_TTL  = 7 * 24 * 60 * 60 * 1000; // بازظهور بنر پس از ۷ روز
    var INSTALL_KEY  = 'cnpwa-installed';

    /* ---------- وضعیت نصب ---------- */

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || window.matchMedia('(display-mode: minimal-ui)').matches
            || window.navigator.standalone === true;
    }

    function isMobileish() {
        return window.matchMedia('(max-width: 820px)').matches
            || (window.matchMedia('(pointer: coarse)').matches && window.matchMedia('(max-width: 1100px)').matches);
    }

    function isIOS() {
        return /iphone|ipod|ipad/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    function dismissedRecently() {
        try {
            var t = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
            return t && (Date.now() - t) < DISMISS_TTL;
        } catch (e) { return false; }
    }

    function markDismissed() {
        try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
    }

    /* ---------- استایل سراسری این ماژول ---------- */

    var STYLE_ADDED = false;
    function ensureStyle() {
        if (STYLE_ADDED) return;
        STYLE_ADDED = true;
        var css = [
            '.cnpwa-root *{box-sizing:border-box;font-family:Vazirmatn,Tahoma,-apple-system,"Segoe UI",sans-serif}',
            // بنر نصب
            '.cnpwa-banner{position:fixed;left:14px;right:14px;bottom:14px;z-index:99998;direction:rtl;',
            'display:flex;align-items:center;gap:12px;padding:14px 14px 14px 10px;max-width:480px;margin:0 auto;',
            'border-radius:22px;color:#f7ead9;',
            'background:linear-gradient(155deg,rgba(46,28,10,.97),rgba(29,18,6,.98));',
            'border:1px solid rgba(226,186,133,.28);',
            'box-shadow:0 24px 60px rgba(0,0,0,.5),inset 0 1px 0 rgba(226,186,133,.12);',
            'transform:translateY(130%);opacity:0;transition:transform .45s cubic-bezier(.2,.9,.25,1.2),opacity .45s ease}',
            '.cnpwa-banner.cnpwa-show{transform:translateY(0);opacity:1}',
            '.cnpwa-banner .cnpwa-icon{width:46px;height:46px;border-radius:14px;flex:none;',
            'box-shadow:0 8px 20px rgba(0,0,0,.4);border:1px solid rgba(226,186,133,.35)}',
            '.cnpwa-banner .cnpwa-body{min-width:0;flex:1}',
            '.cnpwa-banner .cnpwa-title{font-size:13.5px;font-weight:800;letter-spacing:-.01em;margin:0 0 3px;color:#fdf8f3}',
            '.cnpwa-banner .cnpwa-sub{font-size:11px;font-weight:400;margin:0;line-height:1.8;color:rgba(247,234,217,.62);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
            '.cnpwa-btn{appearance:none;border:0;cursor:pointer;flex:none;',
            'display:inline-flex;align-items:center;justify-content:center;gap:7px;',
            'padding:11px 18px;border-radius:14px;font:inherit;font-size:12.5px;font-weight:800;color:#fff;',
            'background:linear-gradient(90deg,#c47f3d,#a8652e);box-shadow:0 10px 26px rgba(168,101,46,.45);',
            'transition:transform .22s ease,box-shadow .22s ease}',
            '.cnpwa-btn:hover{transform:translateY(-1px);box-shadow:0 14px 32px rgba(168,101,46,.58)}',
            '.cnpwa-btn:active{transform:translateY(0)}',
            '.cnpwa-btn:disabled{opacity:.55;cursor:wait;transform:none}',
            '.cnpwa-x{appearance:none;background:transparent;border:0;cursor:pointer;flex:none;',
            'width:34px;height:34px;border-radius:11px;display:grid;place-items:center;color:rgba(247,234,217,.5);',
            'transition:background .2s ease,color .2s ease}',
            '.cnpwa-x:hover{background:rgba(247,234,217,.08);color:#f7ead9}',
            // شیت مراحل iOS
            '.cnpwa-steps{position:fixed;inset:0;z-index:99999;display:flex;align-items:flex-end;justify-content:center;',
            'background:rgba(15,9,3,.6);backdrop-filter:blur(3px);opacity:0;pointer-events:none;transition:opacity .3s ease;direction:rtl}',
            '.cnpwa-steps.cnpwa-show{opacity:1;pointer-events:auto}',
            '.cnpwa-sheet{width:100%;max-width:480px;margin:0 12px 14px;padding:24px 20px calc(24px + env(safe-area-inset-bottom));',
            'border-radius:26px;color:#f7ead9;transform:translateY(60px);transition:transform .35s cubic-bezier(.2,.9,.25,1.15);',
            'background:linear-gradient(165deg,rgba(46,28,10,.98),rgba(29,18,6,.99));',
            'border:1px solid rgba(226,186,133,.25);box-shadow:0 -20px 60px rgba(0,0,0,.55)}',
            '.cnpwa-steps.cnpwa-show .cnpwa-sheet{transform:translateY(0)}',
            '.cnpwa-sheet h3{margin:0 0 4px;font-size:15px;font-weight:800;color:#fdf8f3}',
            '.cnpwa-sheet .cnpwa-hint{margin:0 0 18px;font-size:11.5px;color:rgba(247,234,217,.55);font-weight:400}',
            '.cnpwa-step{display:flex;align-items:center;gap:13px;padding:12px 14px;border-radius:16px;margin-bottom:10px;',
            'background:rgba(247,234,217,.055);border:1px solid rgba(226,186,133,.14)}',
            '.cnpwa-step .n{width:30px;height:30px;border-radius:10px;flex:none;display:grid;place-items:center;',
            'background:linear-gradient(135deg,rgba(196,127,61,.35),rgba(168,101,46,.35));color:#e2ba85;',
            'font-size:13px;font-weight:800}',
            '.cnpwa-step p{margin:0;font-size:12.5px;font-weight:600;color:rgba(247,234,217,.9);line-height:1.9}',
            '.cnpwa-step p small{display:block;font-weight:400;font-size:10.5px;color:rgba(247,234,217,.5)}',
            '.cnpwa-sheet .cnpwa-btn{width:100%;margin-top:10px}',
            // توست
            '.cnpwa-toast{position:fixed;top:14px;left:50%;transform:translate(-50%,-90px);z-index:99999;',
            'direction:rtl;display:flex;align-items:center;gap:11px;padding:11px 13px;border-radius:16px;max-width:min(92vw,430px);',
            'background:linear-gradient(155deg,rgba(46,28,10,.97),rgba(29,18,6,.98));color:#f7ead9;',
            'border:1px solid rgba(226,186,133,.3);box-shadow:0 18px 48px rgba(0,0,0,.45);',
            'opacity:0;transition:transform .4s cubic-bezier(.2,.9,.25,1.2),opacity .4s ease}',
            '.cnpwa-toast.cnpwa-show{transform:translate(-50%,0);opacity:1}',
            '.cnpwa-toast .cnpwa-tico{width:34px;height:34px;border-radius:11px;flex:none;display:grid;place-items:center;',
            'background:rgba(52,211,153,.16);color:#6ee7b7}',
            '.cnpwa-toast p{margin:0;font-size:12.5px;font-weight:700;color:#fdf8f3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
            '.cnpwa-toast .cnpwa-btn{padding:9px 15px;font-size:11.5px;border-radius:11px;white-space:nowrap}'
        ].join('');
        var st = document.createElement('style');
        st.id = 'cnpwa-style';
        st.textContent = css;
        document.head.appendChild(st);
    }

    /* ---------- توست سبک ---------- */

    function toast(text, actionText, actionFn) {
        ensureStyle();
        var el = document.createElement('div');
        el.className = 'cnpwa-toast';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.innerHTML =
            '<span class="cnpwa-tico" aria-hidden="true">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>' +
            '</span><p>' + text + '</p>';
        if (actionText && typeof actionFn === 'function') {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cnpwa-btn';
            btn.textContent = actionText;
            btn.addEventListener('click', function () { actionFn(); el.remove(); });
            el.appendChild(btn);
        }
        document.body.appendChild(el);
        requestAnimationFrame(function () { requestAnimationFrame(function () { el.classList.add('cnpwa-show'); }); });
        setTimeout(function () {
            el.classList.remove('cnpwa-show');
            setTimeout(function () { el.remove(); }, 450);
        }, actionText ? 14000 : 3800);
        return el;
    }

    /* ---------- بنر نصب ---------- */

    var deferredPrompt = null;
    var banner = null;

    function positionBanner() {
        if (!banner) return;
        var nav = document.querySelector('.bottom-nav');
        var bottom = 14;
        if (nav && getComputedStyle(nav).display !== 'none') {
            var rect = nav.getBoundingClientRect();
            bottom = Math.max(bottom, (window.innerHeight - rect.top) + 10);
        }
        banner.style.bottom = 'calc(' + bottom + 'px + env(safe-area-inset-bottom))';
    }

    function showBanner() {
        if (banner || isStandalone() || dismissedRecently()) return;
        if (window.matchMedia('(min-width: 1100px)').matches && window.matchMedia('(pointer: fine)').matches) return;
        ensureStyle();

        var ios = isIOS() && !deferredPrompt;

        banner = document.createElement('div');
        banner.className = 'cnpwa-banner';
        banner.setAttribute('role', 'alert');
        banner.setAttribute('aria-label', 'نصب اپلیکیشن کافی‌نت آنلاین');
        banner.innerHTML =
            '<img class="cnpwa-icon" src="/icons/icon-192.png" width="46" height="46" alt=""> ' +
            '<span class="cnpwa-body">' +
            '<p class="cnpwa-title">کافی‌نت آنلاین را نصب کنید</p>' +
            '<p class="cnpwa-sub">سریع‌تر باز می‌شود · بدون مرورگر · حتی آفلاین در دسترس</p>' +
            '</span>' +
            '<button type="button" class="cnpwa-btn cnpwa-install">نصب</button>' +
            '<button type="button" class="cnpwa-x" aria-label="بستن">' +
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
            '</button>';

        document.body.appendChild(banner);
        positionBanner();
        window.addEventListener('resize', positionBanner);

        requestAnimationFrame(function () { requestAnimationFrame(function () { banner.classList.add('cnpwa-show'); }); });

        var close = banner.querySelector('.cnpwa-x');
        close.addEventListener('click', function () {
            hideBanner();
            markDismissed();
        });

        var install = banner.querySelector('.cnpwa-install');
        install.addEventListener('click', function () {
            if (deferredPrompt) {
                install.disabled = true;
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function (choice) {
                    if (choice && choice.outcome === 'accepted') {
                        try { localStorage.setItem(INSTALL_KEY, '1'); } catch (e) {}
                        hideBanner();
                        toast('کافی‌نت آنلاین نصب شد؛ از صفحه اصلی بازش کنید ✓');
                    } else {
                        hideBanner();
                        markDismissed();
                    }
                    deferredPrompt = null;
                }).catch(function () { install.disabled = false; });
            } else {
                openIOSSteps();
            }
        });

        if (ios) {
            install.textContent = 'نصب';
            install.insertAdjacentHTML('beforeend', '');
        }
    }

    function hideBanner() {
        if (!banner) return;
        var el = banner;
        el.classList.remove('cnpwa-show');
        setTimeout(function () { el.remove(); }, 500);
        banner = null;
        window.removeEventListener('resize', positionBanner);
    }

    /* شیت مراحل نصب در iOS (Safari) */
    function openIOSSteps() {
        ensureStyle();
        hideBanner();
        var ov = document.createElement('div');
        ov.className = 'cnpwa-steps';
        ov.innerHTML =
            '<div class="cnpwa-sheet" role="dialog" aria-modal="true" aria-label="مراحل نصب در آیفون">' +
            '<h3>نصب روی صفحه اصلی (آیفون)</h3>' +
            '<p class="cnpwa-hint">سه قدم ساده در سافاری:</p>' +
            '<div class="cnpwa-step"><span class="n">۱</span><p>دکمه «اشتراک‌گذاری» را در نوار پایین سافاری بزنید <small>آیکون مربع با فلش رو به بالا</small></p></div>' +
            '<div class="cnpwa-step"><span class="n">۲</span><p>گزینه «افزودن به صفحه اصلی» را انتخاب کنید <small>Add to Home Screen</small></p></div>' +
            '<div class="cnpwa-step"><span class="n">۳</span><p>دکمه «افزودن» را بزنید — تمام! <small>آیکون کافی‌نت روی صفحه اصلی شماست</small></p></div>' +
            '<button type="button" class="cnpwa-btn">متوجه شدم</button>' +
            '</div>';
        document.body.appendChild(ov);
        requestAnimationFrame(function () { ov.classList.add('cnpwa-show'); });
        ov.addEventListener('click', function (e) {
            if (e.target === ov || e.target.closest('.cnpwa-btn')) {
                ov.classList.remove('cnpwa-show');
                setTimeout(function () { ov.remove(); }, 350);
            }
        });
    }

    /* ---------- ثبت Service Worker + به‌روزرسانی ---------- */

    function registerSW() {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/', updateViaCache: 'none' })
            .then(function (reg) {
                // بررسی دوره‌ای نسخه جدید (هر ۶۰ دقیقه + هنگام بازگشت به تب)
                setInterval(function () { reg.update().catch(function () {}); }, 60 * 60 * 1000);
                document.addEventListener('visibilitychange', function () {
                    if (!document.hidden) reg.update().catch(function () {});
                });

                reg.addEventListener('updatefound', function () {
                    var nw = reg.installing;
                    if (!nw) return;
                    nw.addEventListener('statechange', function () {
                        if (nw.state === 'installed' && navigator.serviceWorker.controller) {
                            var refreshing = false;
                            var onController = function () {
                                if (refreshing) return;
                                refreshing = true;
                                location.reload();
                            };
                            navigator.serviceWorker.addEventListener('controllerchange', onController, { once: true });
                            toast('نسخه جدید آماده است', 'به‌روزرسانی', function () {
                                nw.postMessage({ type: 'SKIP_WAITING' });
                            });
                        }
                    });
                });
            })
            .catch(function (err) {
                // SW اختیاری است — خطا نباید صفحه را بشکند
                if (window.console && console.debug) console.debug('PWA register failed:', err);
            });
    }

    /* ---------- رویدادهای نصب ---------- */

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        maybeShowBannerSoon();
    });

    window.addEventListener('appinstalled', function () {
        try { localStorage.setItem(INSTALL_KEY, '1'); } catch (e) {}
        hideBanner();
        toast('کافی‌نت آنلاین نصب شد ✓');
    });

    function maybeShowBannerSoon() {
        if (isStandalone()) return;
        if (!isMobileish() && !deferredPrompt) return;
        // تأخیر کوتاه تا صفحه نفس بکشد
        setTimeout(showBanner, 2200);
    }

    /* iOS سافاری beforeinstallprompt ندارد — بعد از لود بررسی می‌کنیم */
    window.addEventListener('load', function () {
        if (isStandalone()) return;
        if (isIOS() && isMobileish() && !dismissedRecently()) {
            setTimeout(showBanner, 3200);
        }
    });

    /* ---------- شروع ---------- */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerSW);
    } else {
        registerSW();
    }

    /* قلاب دیباگ (E2E) — نمایش دستی بنر بدون beforeinstallprompt */
    window.__cnpwa = {
        showBanner: function () { deferredPrompt = deferredPrompt || { prompt: function () {}, userChoice: Promise.resolve({ outcome: 'dismissed' }) }; showBanner(); },
        hideBanner: hideBanner,
        toast: toast,
        openIOSSteps: openIOSSteps
    };
})();
