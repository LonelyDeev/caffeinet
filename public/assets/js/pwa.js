/* =============================================================
 * کافی‌نت آنلاین — PWA Runtime (ثبت SW + مودال نصب مشتری + به‌روزرسانی)
 * -------------------------------------------------------------
 * بدون وابستگی (jQuery خیر) — روی اپ مشتری و پنل‌ها کار می‌کند.
 * (صفحه فرود این اسکریپت را لود نمی‌کند؛ صفحه فرند عمداً «سایت» است.)
 *
 * مودال نصب (سمت مشتری):
 *   • فقط صفحات «اپ مشتری» (/app) — نه صفحه فرود، نه پنل‌های مدیریت
 *   • مودال از پایین صفحه + دکمه نصب + چک‌باکس «دیگه نمایش نده»
 *   • تا وقتی نصب نشده در هر مراجعه دوباره نمایش داده می‌شود؛
 *     اگر چک‌باکس موقع بستن تیک خورده باشد دیگر هرگز نمایش داده نمی‌شود
 *   • در iOS (سافاری) راهنمای ۳ مرحله‌ای نصب باز می‌شود
 *   • نصب پنل‌ها (admin/org/coffeenet/operator) مودال ندارد؛
 *     از منوی خود مرورگر (نصب برنامه) انجام می‌شود
 *
 * همه استایل‌ها inline و prefixed با cnpwa- تا با هیچ تمی تداخل نکند
 * ============================================================= */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) return;

    var INSTALL_KEY = 'cnpwa-installed';   // نصب انجام شده — دیگر هرگز
    var NEVER_KEY   = 'cnpwa-never-ask';   // چک‌باکس «دیگه نمایش نده»
    var VISIT_KEY   = 'cnpwa-asked-visit'; // یک بار در هر مراجعه (sessionStorage)

    /* ---------- وضعیت نصب ---------- */

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || window.matchMedia('(display-mode: minimal-ui)').matches
            || window.navigator.standalone === true;
    }

    function isInstalled() {
        if (isStandalone()) return true;
        try { return localStorage.getItem(INSTALL_KEY) === '1'; } catch (e) { return false; }
    }

    function neverAskAgain() {
        try { return localStorage.getItem(NEVER_KEY) === '1'; } catch (e) { return false; }
    }

    function askedThisVisit() {
        try { return sessionStorage.getItem(VISIT_KEY) === '1'; } catch (e) { return false; }
    }

    function markAsked() {
        try { sessionStorage.setItem(VISIT_KEY, '1'); } catch (e) {}
    }

    function isMobileish() {
        return window.matchMedia('(max-width: 820px)').matches
            || (window.matchMedia('(pointer: coarse)').matches && window.matchMedia('(max-width: 1100px)').matches);
    }

    function isIOS() {
        return /iphone|ipod|ipad/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    /* پیشنهاد نصب فقط در صفحات «اپ مشتری» (/app): صفحه فرود مانیفست ندارد
     * (غیرقابل نصب) و پنل‌های مدیریت/سازمان/کافی‌نت/اپراتور و صفحات پرداخت
     * (جریان حساس درگاه) هم مودال ندارند — نصب پنل‌ها از منوی مرورگر. */
    function isCustomerPage() {
        return /^\/app(\/|$)/i.test(window.location.pathname);
    }

    /* ---------- استایل سراسری این ماژول ---------- */

    var STYLE_ADDED = false;
    function ensureStyle() {
        if (STYLE_ADDED) return;
        STYLE_ADDED = true;
        var css = [
            '.cnpwa-root *{box-sizing:border-box;font-family:Vazirmatn,Tahoma,-apple-system,"Segoe UI",sans-serif}',

            /* ---- مودال نصب (از پایین) ---- */
            '.cnpwa-modal{position:fixed;inset:0;z-index:99998;display:flex;align-items:flex-end;justify-content:center;',
            'background:rgba(15,9,3,.62);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);',
            'opacity:0;pointer-events:none;transition:opacity .32s ease;direction:rtl}',
            '.cnpwa-modal.cnpwa-show{opacity:1;pointer-events:auto}',
            '.cnpwa-isheet{width:100%;max-width:460px;margin:0 12px 14px;position:relative;text-align:center;',
            'padding:30px 22px calc(18px + env(safe-area-inset-bottom));border-radius:30px;color:#f7ead9;',
            'transform:translateY(90px);transition:transform .42s cubic-bezier(.2,.9,.25,1.12);',
            'background:linear-gradient(168deg,#2e1c0a 0%,#241608 55%,#1d1206 100%);',
            'border:1px solid rgba(226,186,133,.26);box-shadow:0 -22px 70px rgba(0,0,0,.55),inset 0 1px 0 rgba(226,186,133,.1)}',
            '.cnpwa-modal.cnpwa-show .cnpwa-isheet{transform:translateY(0)}',
            '.cnpwa-isheet .cnpwa-grip{width:42px;height:4.5px;border-radius:99px;background:rgba(226,186,133,.25);margin:0 auto 16px}',
            '.cnpwa-isheet .cnpwa-x{position:absolute;top:16px;left:16px;appearance:none;background:transparent;border:0;cursor:pointer;',
            'width:36px;height:36px;border-radius:12px;display:grid;place-items:center;color:rgba(247,234,217,.5);',
            'transition:background .2s ease,color .2s ease}',
            '.cnpwa-isheet .cnpwa-x:hover{background:rgba(247,234,217,.09);color:#f7ead9}',
            '.cnpwa-isheet .cnpwa-appicon{width:72px;height:72px;border-radius:22px;margin:0 auto 14px;display:block;',
            'box-shadow:0 14px 34px rgba(0,0,0,.45),0 0 0 6px rgba(226,186,133,.09),0 0 0 1px rgba(226,186,133,.3)}',
            '.cnpwa-isheet h3{margin:0 0 7px;font-size:16.5px;font-weight:800;color:#fdf8f3;letter-spacing:-.01em}',
            '.cnpwa-isheet .cnpwa-sub{margin:0 auto 18px;font-size:12px;font-weight:400;line-height:2;color:rgba(247,234,217,.6);max-width:330px}',
            '.cnpwa-feats{list-style:none;margin:0 0 20px;padding:0;display:grid;gap:9px;text-align:right}',
            '.cnpwa-feats li{display:flex;align-items:center;gap:11px;padding:10px 13px;border-radius:15px;',
            'background:rgba(247,234,217,.05);border:1px solid rgba(226,186,133,.13)}',
            '.cnpwa-feats .cnpwa-fico{width:32px;height:32px;border-radius:10px;flex:none;display:grid;place-items:center;',
            'background:linear-gradient(135deg,rgba(196,127,61,.3),rgba(168,101,46,.3));color:#e2ba85}',
            '.cnpwa-feats p{margin:0;font-size:12px;font-weight:600;color:rgba(247,234,217,.88);line-height:1.8}',
            '.cnpwa-feats p small{display:block;font-size:10.5px;font-weight:400;color:rgba(247,234,217,.45)}',
            '.cnpwa-btn{appearance:none;border:0;cursor:pointer;flex:none;',
            'display:inline-flex;align-items:center;justify-content:center;gap:8px;',
            'padding:13px 20px;border-radius:16px;font:inherit;font-size:13px;font-weight:800;color:#fff;',
            'background:linear-gradient(90deg,#c47f3d,#a8652e);box-shadow:0 12px 30px rgba(168,101,46,.45);',
            'transition:transform .22s ease,box-shadow .22s ease}',
            '.cnpwa-btn:hover{transform:translateY(-1px);box-shadow:0 16px 38px rgba(168,101,46,.58)}',
            '.cnpwa-btn:active{transform:translateY(0)}',
            '.cnpwa-btn:disabled{opacity:.55;cursor:wait;transform:none}',
            '.cnpwa-isheet .cnpwa-install{width:100%;padding:15px 20px;font-size:14px}',
            /* چک‌باکس «دیگه نمایش نده» */
            '.cnpwa-check{display:flex;align-items:center;justify-content:center;gap:9px;margin-top:14px;',
            'cursor:pointer;user-select:none;-webkit-user-select:none;-webkit-tap-highlight-color:transparent}',
            '.cnpwa-check input{appearance:none;-webkit-appearance:none;width:19px;height:19px;flex:none;cursor:pointer;',
            'border-radius:7px;border:1.6px solid rgba(226,186,133,.5);background:rgba(247,234,217,.06);',
            'display:grid;place-items:center;margin:0;transition:background .2s ease,border-color .2s ease}',
            '.cnpwa-check input:checked{background:linear-gradient(135deg,#c47f3d,#a8652e);border-color:#c47f3d}',
            '.cnpwa-check input:checked::after{content:"";width:5px;height:9px;margin-top:-2px;',
            'border:solid #fff;border-width:0 2.5px 2.5px 0;transform:rotate(45deg)}',
            '.cnpwa-check span{font-size:11.5px;font-weight:500;color:rgba(247,234,217,.58)}',

            /* ---- شیت مراحل iOS ---- */
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

            /* ---- توست ---- */
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

    /* ---------- مودال نصب (سمت مشتری) ---------- */

    var deferredPrompt = null;
    var modal = null;

    function canPromptInstall() {
        if (isInstalled()) return false;          // نصب شده — دیگر هرگز
        if (neverAskAgain()) return false;        // چک‌باکس «دیگه نمایش نده»
        if (askedThisVisit()) return false;       // این مراجعه پرسیده‌ایم
        if (!isCustomerPage()) return false;      // فقط صفحات مشتری
        if (!isMobileish()) return false;         // تجربه نصب مخصوص موبایل
        return !!(deferredPrompt || isIOS());     // مرورگر نصب را پشتیبانی می‌کند
    }

    function showModal() {
        if (modal || !canPromptInstall()) return;
        ensureStyle();
        markAsked();

        modal = document.createElement('div');
        modal.className = 'cnpwa-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'نصب اپلیکیشن کافی‌نت آنلاین');
        modal.innerHTML =
            '<div class="cnpwa-isheet">' +
            '<button type="button" class="cnpwa-x" aria-label="بستن">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
            '</button>' +
            '<div class="cnpwa-grip" aria-hidden="true"></div>' +
            '<img class="cnpwa-appicon" src="/icons/icon-192.png" width="72" height="72" alt="آیکون کافی‌نت آنلاین">' +
            '<h3>کافی‌نت آنلاین را روی گوشی نصب کنید</h3>' +
            '<p class="cnpwa-sub">اپلیکیشن کافی‌نت سریع‌تر از مرورگر باز می‌شود و همیشه در دسترس شماست.</p>' +
            '<ul class="cnpwa-feats">' +
            '<li><span class="cnpwa-fico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.58-1.82c1.98-1.39 4.09-2.18 6.58-2.18s4.6.79 6.58 2.18A1 1 0 0 1 16.4 14Z"/><path d="m13 12 2 2"/><path d="m18 11 2 2"/><path d="m2 19 20-8"/></svg></span>' +
            '<p>دسترسی سریع به خدمات<small>بدون باز کردن مرورگر و جست‌وجو</small></p></li>' +
            '<li><span class="cnpwa-fico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/></svg></span>' +
            '<p>حتی وقتی اینترنت نیست<small>صفحه‌های باز شده آفلاین در دسترس می‌مانند</small></p></li>' +
            '<li><span class="cnpwa-fico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.813 6.5 8 11l4.5 7.5H7l4.5-7.5"/><path d="M17.5 6.5 14.7 11l4.6 7.5H15l4.6-7.5"/><path d="M14.7 11 17.5 6.5"/></svg></span>' +
            '<p>پیگیری لحظه‌ای سفارش‌ها<small>گفتگو با اپراتور و وضعیت سفارش همیشه همراه شما</small></p></li>' +
            '</ul>' +
            '<button type="button" class="cnpwa-btn cnpwa-install">' +
            '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3"/><path d="m7 10 5 5 5-5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>' +
            'نصب اپلیکیشن</button>' +
            '<label class="cnpwa-check"><input type="checkbox" id="cnpwa-never"><span>دیگه نمایش نده</span></label>' +
            '</div>';

        document.body.appendChild(modal);
        requestAnimationFrame(function () { requestAnimationFrame(function () { modal.classList.add('cnpwa-show'); }); });

        var box = modal.querySelector('#cnpwa-never');

        function closeModal(remember) {
            if (!modal) return;
            var el = modal;
            modal = null;
            el.classList.remove('cnpwa-show');
            setTimeout(function () { el.remove(); }, 420);
            if (remember) {
                try { localStorage.setItem(NEVER_KEY, '1'); } catch (e) {}
            }
        }

        /* بستن با دکمه ✕ یا لمس پس‌زمینه — اگر چک‌باکس تیک خورده باشد دیگر نمایش نده */
        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.closest('.cnpwa-x')) {
                closeModal(box && box.checked);
            }
        });

        /* دکمه نصب */
        var installBtn = modal.querySelector('.cnpwa-install');
        installBtn.addEventListener('click', function () {
            if (deferredPrompt) {
                installBtn.disabled = true;
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function (choice) {
                    if (choice && choice.outcome === 'accepted') {
                        try { localStorage.setItem(INSTALL_KEY, '1'); } catch (e) {}
                        closeModal(false);
                        toast('کافی‌نت آنلاین نصب شد؛ از صفحه اصلی بازش کنید ✓');
                    } else {
                        /* رد کردن پرامپت نصب — مراجعه بعدی دوباره پیشنهاد می‌شود */
                        closeModal(false);
                    }
                    deferredPrompt = null;
                }).catch(function () { installBtn.disabled = false; });
            } else {
                openIOSSteps();
                closeModal(false);
            }
        });

        /* بستن با دکمه Esc */
        document.addEventListener('keydown', escClose);
        function escClose(e) {
            if (e.key === 'Escape' && modal) {
                closeModal(box && box.checked);
                document.removeEventListener('keydown', escClose);
            }
        }
    }

    /* ---------- شیت مراحل نصب در iOS (Safari) ---------- */
    function openIOSSteps() {
        ensureStyle();
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
        // مشتری که وارد شد و مرورگر اجازه نصب داد → پیشنهاد نصب
        setTimeout(showModal, 1600);
    });

    window.addEventListener('appinstalled', function () {
        try { localStorage.setItem(INSTALL_KEY, '1'); } catch (e) {}
        if (modal) modal.remove(), modal = null;
        toast('کافی‌نت آنلاین نصب شد ✓');
    });

    /* iOS سافاری beforeinstallprompt ندارد — بعد از لود بررسی می‌کنیم */
    window.addEventListener('load', function () {
        if (isStandalone()) return;
        setTimeout(showModal, 2600);
    });

    /* ---------- شروع ---------- */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerSW);
    } else {
        registerSW();
    }

    /* قلاب دیباگ (E2E) — نمایش دستی مودال بدون beforeinstallprompt */
    window.__cnpwa = {
        showModal: function () { deferredPrompt = deferredPrompt || { prompt: function () {}, userChoice: Promise.resolve({ outcome: 'dismissed' }) }; if (!askedThisVisit()) { try { sessionStorage.removeItem(VISIT_KEY); } catch (e) {} } showModal(); },
        hideModal: function () { if (modal) { modal.remove(); modal = null; } },
        toast: toast,
        openIOSSteps: openIOSSteps,
        state: function () {
            return {
                installed: isInstalled(), never: neverAskAgain(), askedVisit: askedThisVisit(),
                customer: isCustomerPage(), mobile: isMobileish(), ios: isIOS(), prompt: !!deferredPrompt
            };
        },
        reset: function () {
            try {
                localStorage.removeItem(INSTALL_KEY);
                localStorage.removeItem(NEVER_KEY);
                sessionStorage.removeItem(VISIT_KEY);
            } catch (e) {}
        }
    };
})();
