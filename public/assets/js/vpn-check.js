/* =============================================================
 * کافی‌نت آنلاین — VPN Check (v19)
 * -------------------------------------------------------------
 * مودال «برای بهره‌بری سریع‌تر، VPN را خاموش کنید»
 *
 * • فقط پنل‌ها این فایل را لود می‌کنند (partials/vpn-modal) —
 *   اپ مشتری، ادمین، سازمان، کافی‌نت، اپراتور + صفحات ورودشان.
 *   صفحهٔ فرود (landing) هرگز include نمی‌کند.
 * • منطق: GET /api/v1/vpn-status (عمومی) — اگر آی‌پی کاربر
 *   خارج از ایران باشد → مودال توصیهٔ خاموش‌کردن VPN.
 * • سیاست نمایش (v20):
 *   - حداکثر یک بار در هر مراجعه (sessionStorage) — بستنِ ساده
 *     فقط تا پایان همین مراجعه (تب جاری) ممانعت می‌کند؛ در ورود
 *     بعدی به برنامه (تب/نشست جدید) دوباره نمایش داده می‌شود.
 *   - چک‌باکس «دیگه نمایش نده» → دیگر هرگز (localStorage)
 *   - اگر مودال نصب PWA باز باشد صبر می‌کند تا بسته شود
 *   - با فعال‌شدن مجدد تب، اگر ۳+ دقیقه گذشته باشد دوباره چک می‌شود
 * • بدون وابستگی (بدون jQuery) — همه استایل‌ها inline و
 *   prefixed با cnvpn- تا با هیچ تمی تداخل نکند
 * ============================================================= */
(function () {
    'use strict';

    var API_URL    = '/api/v1/vpn-status';
    var NEVER_KEY  = 'cnvpn-never';          // چک‌باکس «دیگه نمایش نده»
    var VISIT_KEY  = 'cnvpn-asked-visit';    // یک بار در هر مراجعه
    var LEGACY_KEY = 'cnvpn-dismissed-at';   // snooze قدیمی v19 — پاکسازی
    var START_DELAY = 2600;                  // بعد از لود صفحه (تداخل با لود اولیه)

    var modal = null;
    var lastState = null;   // آخرین پاسخ سرور (قلاب دیباگ)
    var lastCheckAt = 0;

    /* ---------- پرچم‌ها ---------- */

    function neverAsk() {
        try { return localStorage.getItem(NEVER_KEY) === '1'; } catch (e) { return false; }
    }

    /* v19 قدیمی کلید snooze هشت‌ساعته می‌نوشت؛ پاکسازی یک‌باره تا
       کاربرانی که مودال را بدون تیک بسته‌اند دوباره آن را ببینند */
    try { localStorage.removeItem(LEGACY_KEY); } catch (e) {}

    function askedThisVisit() {
        try { return sessionStorage.getItem(VISIT_KEY) === '1'; } catch (e) { return false; }
    }

    function markAsked() {
        try { sessionStorage.setItem(VISIT_KEY, '1'); } catch (e) {}
    }

    /* ---------- استایل (prefix: cnvpn-) ---------- */

    var STYLE_ADDED = false;
    function ensureStyle() {
        if (STYLE_ADDED) return;
        STYLE_ADDED = true;
        var css = [
            '.cnvpn-root *{box-sizing:border-box;font-family:Vazirmatn,Tahoma,-apple-system,"Segoe UI",sans-serif}',

            '.cnvpn-modal{position:fixed;inset:0;z-index:99998;display:flex;align-items:flex-end;justify-content:center;',
            'background:rgba(15,9,3,.62);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);',
            'opacity:0;pointer-events:none;transition:opacity .32s ease;direction:rtl}',
            '.cnvpn-modal.cnvpn-show{opacity:1;pointer-events:auto}',
            '.cnvpn-sheet{width:100%;max-width:460px;margin:0 12px 14px;position:relative;text-align:center;',
            'padding:28px 22px calc(18px + env(safe-area-inset-bottom));border-radius:30px;color:#f7ead9;',
            'transform:translateY(90px);transition:transform .42s cubic-bezier(.2,.9,.25,1.12);',
            'background:linear-gradient(168deg,#2e1c0a 0%,#241608 55%,#1d1206 100%);',
            'border:1px solid rgba(226,186,133,.26);box-shadow:0 -22px 70px rgba(0,0,0,.55),inset 0 1px 0 rgba(226,186,133,.1)}',
            '.cnvpn-modal.cnvpn-show .cnvpn-sheet{transform:translateY(0)}',
            '.cnvpn-sheet .cnvpn-grip{width:42px;height:4.5px;border-radius:99px;background:rgba(226,186,133,.25);margin:0 auto 14px}',
            '.cnvpn-sheet .cnvpn-x{position:absolute;top:16px;left:16px;appearance:none;background:transparent;border:0;cursor:pointer;',
            'width:36px;height:36px;border-radius:12px;display:grid;place-items:center;color:rgba(247,234,217,.5);',
            'transition:background .2s ease,color .2s ease}',
            '.cnvpn-sheet .cnvpn-x:hover{background:rgba(247,234,217,.09);color:#f7ead9}',

            /* آیکون هشدار */
            '.cnvpn-ico{width:74px;height:74px;border-radius:24px;margin:0 auto 14px;display:grid;place-items:center;',
            'background:linear-gradient(135deg,rgba(196,127,61,.32),rgba(168,101,46,.32));color:#e2ba85;',
            'box-shadow:0 14px 34px rgba(0,0,0,.45),0 0 0 6px rgba(226,186,133,.08),0 0 0 1px rgba(226,186,133,.28)}',
            '.cnvpn-sheet h3{margin:0 0 7px;font-size:16.5px;font-weight:800;color:#fdf8f3;letter-spacing:-.01em}',
            '.cnvpn-sheet .cnvpn-sub{margin:0 auto 16px;font-size:12.5px;font-weight:400;line-height:2;color:rgba(247,234,217,.66);max-width:340px}',

            /* چیپ کشور اتصال */
            '.cnvpn-chip{display:flex;align-items:center;justify-content:center;gap:8px;width:fit-content;margin:0 auto 18px;',
            'padding:7px 14px;border-radius:99px;background:rgba(226,186,133,.1);border:1px solid rgba(226,186,133,.22);',
            'font-size:11.5px;font-weight:700;color:#e2ba85}',
            '.cnvpn-chip .cnvpn-dot{width:8px;height:8px;border-radius:99px;background:#f0b45a;flex:none;',
            'box-shadow:0 0 0 3px rgba(240,180,90,.18)}',

            /* ردیف مزایا */
            '.cnvpn-feats{list-style:none;margin:0 0 18px;padding:0;display:grid;gap:9px;text-align:right}',
            '.cnvpn-feats li{display:flex;align-items:center;gap:11px;padding:10px 13px;border-radius:15px;',
            'background:rgba(247,234,217,.05);border:1px solid rgba(226,186,133,.13)}',
            '.cnvpn-feats .cnvpn-fico{width:32px;height:32px;border-radius:10px;flex:none;display:grid;place-items:center;',
            'background:linear-gradient(135deg,rgba(196,127,61,.3),rgba(168,101,46,.3));color:#e2ba85}',
            '.cnvpn-feats p{margin:0;font-size:12px;font-weight:600;color:rgba(247,234,217,.88);line-height:1.8}',
            '.cnvpn-feats p small{display:block;font-size:10.5px;font-weight:400;color:rgba(247,234,217,.45)}',

            /* دکمه */
            '.cnvpn-btn{appearance:none;border:0;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;',
            'width:100%;padding:15px 20px;border-radius:16px;font:inherit;font-size:14px;font-weight:800;color:#fff;',
            'background:linear-gradient(90deg,#c47f3d,#a8652e);box-shadow:0 12px 30px rgba(168,101,46,.45);',
            'transition:transform .22s ease,box-shadow .22s ease}',
            '.cnvpn-btn:hover{transform:translateY(-1px);box-shadow:0 16px 38px rgba(168,101,46,.58)}',
            '.cnvpn-btn:active{transform:translateY(0)}',

            /* چک‌باکس «دیگه نمایش نده» */
            '.cnvpn-check{display:flex;align-items:center;justify-content:center;gap:9px;margin-top:14px;',
            'cursor:pointer;user-select:none;-webkit-user-select:none;-webkit-tap-highlight-color:transparent}',
            '.cnvpn-check input{appearance:none;-webkit-appearance:none;width:19px;height:19px;flex:none;cursor:pointer;',
            'border-radius:7px;border:1.6px solid rgba(226,186,133,.5);background:rgba(247,234,217,.06);',
            'display:grid;place-items:center;margin:0;transition:background .2s ease,border-color .2s ease}',
            '.cnvpn-check input:checked{background:linear-gradient(135deg,#c47f3d,#a8652e);border-color:#c47f3d}',
            '.cnvpn-check input:checked::after{content:"";width:5px;height:9px;margin-top:-2px;',
            'border:solid #fff;border-width:0 2.5px 2.5px 0;transform:rotate(45deg)}',
            '.cnvpn-check span{font-size:11.5px;font-weight:500;color:rgba(247,234,217,.58)}',

            /* نکتهٔ پایانی */
            '.cnvpn-hint{margin:12px 0 0;font-size:10.5px;font-weight:400;color:rgba(247,234,217,.4);line-height:1.9}'
        ].join('');
        var st = document.createElement('style');
        st.id = 'cnvpn-style';
        st.textContent = css;
        document.head.appendChild(st);
    }

    /* ---------- بررسی وضعیت (GET /api/v1/vpn-status) ---------- */

    function check(force) {
        fetch(API_URL, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (body) {
                lastCheckAt = Date.now();
                var d = body && body.data;
                if (!d) return;
                lastState = d;
                if (d.vpn) showWhenClear(force);
            })
            .catch(function () { /* بی‌صدا — سرویس عمومی و غیرحیاتی است */ });
    }

    /* اگر مودال نصب PWA باز است صبر می‌کنیم تا بسته شود (دو مودال همزمان = تجربهٔ بد) */
    function pwaBusy() {
        return !!document.querySelector('.cnpwa-modal.cnpwa-show, .cnpwa-steps.cnpwa-show');
    }

    function canShow(force) {
        if (modal) return false;
        if (force) return true;
        if (neverAsk()) return false;      // چک‌باکس «دیگه نمایش نده»
        if (askedThisVisit()) return false;// این مراجعه پرسیده‌ایم
        return true;
    }

    function showWhenClear(force, tries) {
        if (!canShow(force)) return;
        if (pwaBusy()) {
            if ((tries || 0) < 70) setTimeout(function () { showWhenClear(force, (tries || 0) + 1); }, 900);
            return;
        }
        showModal();
    }

    /* ---------- مودال ---------- */

    function showModal() {
        ensureStyle();
        markAsked();

        modal = document.createElement('div');
        modal.className = 'cnvpn-root cnvpn-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'هشدار فعال بودن VPN');

        modal.innerHTML =
            '<div class="cnvpn-sheet">' +
            '<button type="button" class="cnvpn-x" aria-label="بستن">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
            '</button>' +
            '<div class="cnvpn-grip" aria-hidden="true"></div>' +

            /* آیکون وای‌فای قطع داخل شیلد */
            '<div class="cnvpn-ico" aria-hidden="true">' +
            '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m2 2 20 20"/><path d="M8.5 8.5c-2 1.3-3.3 2.9-3.9 4.1"/><path d="M15.5 8.5c1.4.9 2.5 2 3.2 3"/></svg>' +
            '</div>' +

            '<h3>VPN شما فعال است</h3>' +
            '<p class="cnvpn-sub">برای بهره‌بری سریع‌تر، اتصال پایدار و سرعت بهتر، لطفاً VPN خود را خاموش کنید.</p>' +

            /* چیپ کشور اتصال — داینامیک */
            '<div class="cnvpn-chip" hidden><span class="cnvpn-dot" aria-hidden="true"></span><span class="cnvpn-where"></span></div>' +

            '<ul class="cnvpn-feats">' +
            '<li><span class="cnvpn-fico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m13 2-2 2h3l1 3h-2l1 2h-3l-1 3h2l-1 2h3l1 3h-2l1 2 2-2h-3l-1-3h2l-1-2h3l1-3h-2l1-2h-3l-1-3h2l-1-2z"/></svg></span>' +
            '<p>سرعت بارگذاری بالاتر<small>ترافیک مستقیم به سرور داخلی، بدون دورزدن از سرور خارجی</small></p></li>' +
            '<li><span class="cnvpn-fico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.58-1.82c1.98-1.39 4.09-2.18 6.58-2.18s4.6.79 6.58 2.18A1 1 0 0 1 16.4 14Z"/><path d="m13 12 2 2"/><path d="m18 11 2 2"/><path d="m2 19 20-8"/></svg></span>' +
            '<p>اتصال پایدارتر در پنل‌ها<small>قطع‌وصل کمتر هنگام کار، گفتگو و آپلود مدارک</small></p></li>' +
            '<li><span class="cnvpn-fico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg></span>' +
            '<p>مصرف بهینهٔ اینترنت<small>ترافیک داخلی معمولاً نیم‌بها محاسبه می‌شود</small></p></li>' +
            '</ul>' +

            '<button type="button" class="cnvpn-btn">متوجه شدم، خاموش می‌کنم</button>' +
            '<label class="cnvpn-check"><input type="checkbox" class="cnvpn-never"><span>دیگه نمایش نده</span></label>' +
            '<p class="cnvpn-hint">پس از خاموش‌کردن VPN، صفحه را یک بار رفرش کنید تا اتصال جدید برقرار شود.</p>' +
            '</div>';

        /* چیپ کشور (اگر سرور نام کشور را برگرداند) */
        var where = modal.querySelector('.cnvpn-where');
        if (lastState && lastState.country_name) {
            where.textContent = 'اتصال فعلی از طریق: ' + lastState.country_name;
            modal.querySelector('.cnvpn-chip').hidden = false;
        }

        document.body.appendChild(modal);
        requestAnimationFrame(function () { requestAnimationFrame(function () { modal.classList.add('cnvpn-show'); }); });

        var box = modal.querySelector('.cnvpn-never');

        function closeModal(remember) {
            if (!modal) return;
            var el = modal;
            modal = null;
            el.classList.remove('cnvpn-show');
            setTimeout(function () { el.remove(); }, 420);
            /* فقط تیک «دیگه نمایش نده» ماندگار است؛ بستنِ ساده
               چیزی ذخیره نمی‌کند → در مراجعهٔ بعدی دوباره نمایش */
            if (remember) {
                try { localStorage.setItem(NEVER_KEY, '1'); } catch (e) {}
            }
        }

        /* بستن با ✕، لمس پس‌زمینه یا دکمهٔ اصلی */
        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.closest('.cnvpn-x')) {
                closeModal(box && box.checked);
            }
        });
        modal.querySelector('.cnvpn-btn').addEventListener('click', function () {
            closeModal(box && box.checked);
        });

        /* بستن با Esc */
        function escClose(e) {
            if (e.key === 'Escape' && modal) {
                closeModal(box && box.checked);
                document.removeEventListener('keydown', escClose);
            }
        }
        document.addEventListener('keydown', escClose);
    }

    /* ---------- شروع ---------- */

    function start() {
        setTimeout(function () { check(false); }, START_DELAY);

        /* با فعال‌شدن مجدد تب (بازگشت از تنظیمات VPN) بعد از ۳ دقیقه دوباره چک */
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && Date.now() - lastCheckAt > 3 * 60 * 1000) {
                check(false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    /* قلاب دیباگ (E2E) */
    window.__cnvpn = {
        check: function () { check(true); },
        show: function () { showWhenClear(true); },
        hide: function () { if (modal) { modal.remove(); modal = null; } },
        state: function () {
            return {
                last: lastState,
                never: neverAsk(),
                askedVisit: askedThisVisit(),
                pwaBusy: pwaBusy()
            };
        },
        reset: function () {
            try {
                localStorage.removeItem(NEVER_KEY);
                localStorage.removeItem(LEGACY_KEY);
                sessionStorage.removeItem(VISIT_KEY);
            } catch (e) {}
        }
    };
})();
