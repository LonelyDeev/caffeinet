/*!
 * CNJdp — تقویم/دیت‌پیکر شمسی سبک و بدون وابستگی (vanilla JS)
 * ------------------------------------------------------------------
 * الگوریتم تبدیل جلالی↔میلادی برگرفته از jalaali-js (MIT) است.
 *
 * استفاده:
 *   <input type="text" data-jdp>                          → مقدار = تاریخ شمسی «Y/m/d» (ارقام انگلیسی)
 *   <input type="text" data-jdp data-jdp-mode="gregorian">
 *       → نمایش شمسی؛ مقدار میلادی Y-m-d در اینپوت مخفیِ خواهر/برادر (id = X-g) قرار می‌گیرد
 *
 * محدودیت‌ها:
 *   data-jdp-min="1390/01/01"   data-jdp-max="1405/12/29"          (شمسی)
 *   data-jdp-min-years-ago="100" data-jdp-max-years-ago="10"       (نسبی — مناسب تاریخ تولد)
 *
 * API عمومی: window.CNJdp = { bindAll(scope), bind(el), setGregorian(el, 'Y-m-d'|null), close() }
 */
(function () {
    'use strict';

    /* ================== هستهٔ تبدیل تقویم (jalaali-js, MIT) ================== */

    var breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    function div(a, b) { return ~~(a / b); }
    function mod(a, b) { return a - ~~(a / b) * b; }

    function jalCal(jy, withoutLeap) {
        var bl = breaks.length, gy = jy + 621, leapJ = -14, jp = breaks[0], jm, jump = 0, leap, leapG, march, n, i;

        if (jy < jp || jy >= breaks[bl - 1]) { return null; }

        for (i = 1; i < bl; i += 1) {
            jm = breaks[i];
            jump = jm - jp;
            if (jy < jm) { break; }
            leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
            jp = jm;
        }
        n = jy - jp;

        leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
        if (mod(jump, 33) === 4 && jump - n === 4) { leapJ += 1; }

        leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
        march = 20 + leapJ - leapG;

        if (!withoutLeap) {
            if (jump - n < 6) { n = n - jump + div(jump + 4, 33) * 33; }
            leap = mod(mod(n + 1, 33) - 1, 4);
            if (leap === -1) { leap = 4; }
        }

        return { leap: leap, gy: gy, march: march };
    }

    function g2d(gy, gm, gd) {
        var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
        d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
        return d;
    }

    function d2g(jdn) {
        var j = 4 * jdn + 139361631;
        j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        var i = div(mod(j, 1461), 4) * 5 + 308;
        var gd = div(mod(i, 153), 5) + 1;
        var gm = mod(div(i, 153), 12) + 1;
        var gy = div(j, 1461) - 100100 + div(8 - gm, 6);
        return { gy: gy, gm: gm, gd: gd };
    }

    function j2d(jy, jm, jd) {
        var r = jalCal(jy, true);
        if (!r) { return null; }
        return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
    }

    function d2j(jdn) {
        var gy = d2g(jdn).gy, jy = gy - 621, r = jalCal(jy, false), jdn1f = g2d(gy, 3, r.march), jd, jm, k;

        k = jdn - jdn1f;
        if (k >= 0) {
            if (k <= 185) {
                jm = 1 + div(k, 31);
                jd = mod(k, 31) + 1;
                return { jy: jy, jm: jm, jd: jd };
            } else {
                k -= 186;
            }
        } else {
            jy -= 1;
            k += 179;
            if (r.leap === 1) { k += 1; }
        }
        jm = 7 + div(k, 30);
        jd = mod(k, 30) + 1;
        return { jy: jy, jm: jm, jd: jd };
    }

    function jalaaliMonthLength(jy, jm) {
        if (jm <= 6) { return 31; }
        if (jm <= 11) { return 30; }
        var a = j2d(jy + 1, 1, 1), b = j2d(jy, 12, 1);
        return (a && b) ? (a - b) : 29;
    }

    /* شمسی → میلادی */
    function toGregorian(jy, jm, jd) {
        var jdn = j2d(jy, jm, jd);
        if (jdn === null || jdn === undefined) { return null; }
        return d2g(jdn);
    }

    /* میلادی → شمسی */
    function toJalaali(gy, gm, gd) {
        return d2j(g2d(gy, gm, gd));
    }

    /* ================== ابزارهای عمومی ================== */

    var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
    function faDigits(s) { return String(s).replace(/\d/g, function (d) { return FA_DIGITS[+d]; }); }
    function enDigits(s) { return String(s).replace(/[۰-۹]/g, function (d) { return String(FA_DIGITS.indexOf(d)); }); }
    function pad2(n) { return (n < 10 ? '0' : '') + n; }

    var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    var WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

    /* «۱۴۰۵/۰۶/۱۶» یا «1405-06-16» یا ارقام فارسی → {jy,jm,jd} */
    function parseJalali(str) {
        if (!str) { return null; }
        var s = enDigits(String(str).trim());
        var m = s.match(/^(\d{3,4})[/\-.](\d{1,2})[/\-.](\d{1,2})$/);
        if (!m) { return null; }
        var jy = +m[1], jm = +m[2], jd = +m[3];
        if (jm < 1 || jm > 12 || jd < 1 || jd > 31) { return null; }
        if (jd > jalaaliMonthLength(jy, jm)) { return null; }
        return { jy: jy, jm: jm, jd: jd };
    }

    function formatJalali(d) { return d ? (d.jy + '/' + pad2(d.jm) + '/' + pad2(d.jd)) : ''; }
    function formatGregorian(g) { return g ? (g.gy + '-' + pad2(g.gm) + '-' + pad2(g.gd)) : ''; }

    function jdnOf(d) { return j2d(d.jy, d.jm, d.jd); }

    /* ================== دیت‌پیکر ================== */

    var popup = null, backdropEl = null, currentInput = null;
    var view = { mode: 'days', jy: 0, jm: 0 }; // وضعیت نمایش فعلی

    function todayJ() { return toJalaali(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate()); }

    /* محدودهٔ مجاز یک اینپوت */
    function rangeOf(input) {
        var min = null, max = null;
        if (input.dataset.jdpMin) { min = parseJalali(input.dataset.jdpMin); }
        if (input.dataset.jdpMax) { max = parseJalali(input.dataset.jdpMax); }
        var t = todayJ();
        // محدودهٔ نسبی (سال‌های شمسیِ گذشته — مناسب تاریخ تولد)
        // توجه: t.jy سالِ شمسی است؛ کافی است سال را کم کنیم (بدون تبدیل مجدد)
        if (input.dataset.jdpMinYearsAgo) {
            min = { jy: t.jy - +input.dataset.jdpMinYearsAgo, jm: t.jm, jd: t.jd };
        }
        if (input.dataset.jdpMaxYearsAgo) {
            max = { jy: t.jy - +input.dataset.jdpMaxYearsAgo, jm: t.jm, jd: t.jd };
        }
        return { min: min, max: max };
    }

    /* مقدار فعلی اینپوت (نمایش شمسی → مقدار واقعی بر اساس mode) */
    function valueOf(input) {
        if (modeOf(input) === 'gregorian') {
            var h = hiddenOf(input);
            var g = h && h.value ? String(h.value).trim() : '';
            var m = g.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
            if (!m) { return null; }
            return toJalaali(+m[1], +m[2], +m[3]);
        }
        return parseJalali(input.value);
    }

    function modeOf(input) { return input.dataset.jdpMode === 'gregorian' ? 'gregorian' : 'jalali'; }

    function hiddenOf(input) {
        if (!input.id) { return null; }
        return document.getElementById(input.id + '-g');
    }

    function writeValue(input, jdate) {
        if (modeOf(input) === 'gregorian') {
            var h = hiddenOf(input);
            var g = jdate ? toGregorian(jdate.jy, jdate.jm, jdate.jd) : null;
            if (h) {
                h.value = g ? formatGregorian(g) : '';
                h.dispatchEvent(new Event('change', { bubbles: true }));
            }
            input.value = jdate ? formatJalali(jdate) : '';
        } else {
            input.value = jdate ? formatJalali(jdate) : '';
        }
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /* ---------- ساخت DOM ---------- */

    function ensurePopup() {
        if (popup) { return; }

        popup = document.createElement('div');
        popup.className = 'jdp-popup';
        popup.dir = 'rtl';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-label', 'انتخاب تاریخ شمسی');

        popup.innerHTML =
            '<div class="jdp-head">' +
            '   <button type="button" class="jdp-nav" data-nav="-1" aria-label="ماه قبل"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>' +
            '   <div class="jdp-titles">' +
            '       <button type="button" class="jdp-title jdp-title-month" aria-label="انتخاب ماه"></button>' +
            '       <button type="button" class="jdp-title jdp-title-year" aria-label="انتخاب سال"></button>' +
            '   </div>' +
            '   <button type="button" class="jdp-nav" data-nav="1" aria-label="ماه بعد"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>' +
            '</div>' +
            '<div class="jdp-views">' +
            '   <div class="jdp-view jdp-days" hidden>' +
            '       <div class="jdp-weekrow"></div>' +
            '       <div class="jdp-grid jdp-grid-days"></div>' +
            '   </div>' +
            '   <div class="jdp-view jdp-months" hidden><div class="jdp-grid jdp-grid-months"></div></div>' +
            '   <div class="jdp-view jdp-years" hidden><div class="jdp-grid jdp-grid-years"></div></div>' +
            '</div>' +
            '<div class="jdp-foot">' +
            '   <span class="jdp-foot-info" hidden></span>' +
            '   <span class="jdp-foot-spacer"></span>' +
            '   <button type="button" class="jdp-act jdp-today">امروز</button>' +
            '   <button type="button" class="jdp-act jdp-clear">پاک کردن</button>' +
            '</div>';

        document.body.appendChild(popup);

        backdropEl = document.createElement('div');
        backdropEl.className = 'jdp-backdrop';
        backdropEl.hidden = true;
        document.body.appendChild(backdropEl);

        /* رویدادها */
        popup.addEventListener('click', function (e) {
            var t = e.target;
            var nav = t.closest('.jdp-nav');
            if (nav) { e.stopPropagation(); navigate(+nav.dataset.nav); return; }

            var tm = t.closest('.jdp-title-month');
            if (tm) { e.stopPropagation(); showView('months'); return; }

            var ty = t.closest('.jdp-title-year');
            if (ty) { e.stopPropagation(); showView('years'); return; }

            var day = t.closest('.jdp-day');
            if (day && !day.classList.contains('is-disabled')) {
                e.stopPropagation();
                pickDay(+day.dataset.jd);
                return;
            }

            var mon = t.closest('.jdp-month');
            if (mon && !mon.classList.contains('is-disabled')) {
                e.stopPropagation();
                view.jm = +mon.dataset.jm;
                showView('days');
                return;
            }

            var yr = t.closest('.jdp-year');
            if (yr && !yr.classList.contains('is-disabled')) {
                e.stopPropagation();
                view.jy = +yr.dataset.jy;
                showView('months');
                return;
            }

            var today = t.closest('.jdp-today');
            if (today) { e.stopPropagation(); pickToday(); return; }

            var clear = t.closest('.jdp-clear');
            if (clear) { e.stopPropagation(); clearValue(); return; }
        });

        backdropEl.addEventListener('click', close);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) { close(); }
        });

        window.addEventListener('resize', function () { if (isOpen()) { position(); } });
        window.addEventListener('scroll', function () { if (isOpen()) { position(); } }, true);
    }

    function isOpen() { return popup && popup.classList.contains('is-open'); }

    /* ---------- ناوبری/رندر ---------- */

    function navigate(dir) {
        if (view.mode === 'days') {
            var jm = view.jm + dir;
            if (jm > 12) { view.jm = 1; view.jy += 1; }
            else if (jm < 1) { view.jm = 12; view.jy -= 1; }
            else { view.jm = jm; }
        } else if (view.mode === 'months') {
            view.jy += dir;
        } else {
            view.jy += dir * 24; /* صفحهٔ سال‌ها = ۲۴ سال */
        }
        render();
    }

    function showView(mode) {
        view.mode = mode;
        render();
    }

    function range() { return currentInput ? rangeOf(currentInput) : { min: null, max: null }; }

    function inRange(d) {
        var r = range();
        if (r.min && jdnOf(d) < jdnOf(r.min)) { return false; }
        if (r.max && jdnOf(d) > jdnOf(r.max)) { return false; }
        return true;
    }

    function render() {
        if (!popup || !currentInput) { return; }
        var t = todayJ();
        var selected = valueOf(currentInput);

        /* عناوین */
        popup.querySelector('.jdp-title-month').textContent = MONTHS[view.jm - 1] || '';
        popup.querySelector('.jdp-title-year').textContent = faDigits(view.jy);

        /* دکمه‌های ناوبری محدوده */
        var r = range();
        var prevBtn = popup.querySelector('[data-nav="-1"]');
        var nextBtn = popup.querySelector('[data-nav="1"]');
        var prevBlocked = false, nextBlocked = false;
        if (view.mode === 'days') {
            prevBlocked = r.min && (view.jy < r.min.jy || (view.jy === r.min.jy && view.jm <= r.min.jm));
            nextBlocked = r.max && (view.jy > r.max.jy || (view.jy === r.max.jy && view.jm >= r.max.jm));
        } else if (view.mode === 'months') {
            prevBlocked = r.min && view.jy <= r.min.jy;
            nextBlocked = r.max && view.jy >= r.max.jy;
        }

        popup.querySelector('.jdp-view.jdp-days').hidden = view.mode !== 'days';
        popup.querySelector('.jdp-view.jdp-months').hidden = view.mode !== 'months';
        popup.querySelector('.jdp-view.jdp-years').hidden = view.mode !== 'years';

        if (view.mode === 'days') {
            var weekrow = popup.querySelector('.jdp-weekrow');
            if (!weekrow.childElementCount) {
                WEEKDAYS.forEach(function (w) {
                    var s = document.createElement('span');
                    s.className = 'jdp-wd';
                    s.textContent = w;
                    weekrow.appendChild(s);
                });
            }

            var grid = popup.querySelector('.jdp-grid-days');
            grid.innerHTML = '';

            /* روزِ ۱ ماه جاری چه روزی از هفته است؟ (شنبه = ۰) */
            var g1 = toGregorian(view.jy, view.jm, 1);
            var dow1 = (new Date(g1.gy, g1.gm - 1, g1.gd).getDay() + 1) % 7;

            var monthLen = jalaaliMonthLength(view.jy, view.jm);

            /* سلول‌های خالی ابتدای ماه */
            for (var i = 0; i < dow1; i++) {
                var pad = document.createElement('span');
                pad.className = 'jdp-day is-pad';
                grid.appendChild(pad);
            }

            for (var d = 1; d <= monthLen; d++) {
                var cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'jdp-day';
                cell.dataset.jd = d;
                cell.textContent = faDigits(d);

                var jd = { jy: view.jy, jm: view.jm, jd: d };
                var disabled = !inRange(jd);
                if (disabled) { cell.classList.add('is-disabled'); }

                if (t.jy === view.jy && t.jm === view.jm && t.jd === d) { cell.classList.add('is-today'); }
                if (selected && selected.jy === view.jy && selected.jm === view.jm && selected.jd === d) { cell.classList.add('is-selected'); }

                grid.appendChild(cell);
            }
        }

        if (view.mode === 'months') {
            var gridM = popup.querySelector('.jdp-grid-months');
            gridM.innerHTML = '';
            MONTHS.forEach(function (name, idx) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'jdp-month' + (view.jm === idx + 1 ? ' is-selected' : '') +
                    (t.jy === view.jy && t.jm === idx + 1 ? ' is-today' : '');
                b.dataset.jm = idx + 1;
                b.textContent = name;
                var md = { jy: view.jy, jm: idx + 1, jd: 1 };
                if (!inRange(md)) { b.classList.add('is-disabled'); }
                gridM.appendChild(b);
            });
        }

        if (view.mode === 'years') {
            var gridY = popup.querySelector('.jdp-grid-years');
            gridY.innerHTML = '';
            var start = view.jy - 12;
            for (var y = start; y < start + 24; y++) {
                var by = document.createElement('button');
                by.type = 'button';
                by.className = 'jdp-year' + (view.jy === y ? ' is-selected' : '') + (t.jy === y ? ' is-today' : '');
                by.dataset.jy = y;
                by.textContent = faDigits(y);
                var yd = { jy: y, jm: 1, jd: 1 };
                var yOk = (!r.min || y >= r.min.jy) && (!r.max || y <= r.max.jy);
                if (!yOk) { by.classList.add('is-disabled'); }
                gridY.appendChild(by);
            }
        }

        /* نوار پایانی */
        var info = popup.querySelector('.jdp-foot-info');
        var sel = valueOf(currentInput);
        if (sel) {
            var gsel = toGregorian(sel.jy, sel.jm, sel.jd);
            info.hidden = false;
            info.textContent = 'میلادی: ' + formatGregorian(gsel);
        } else {
            info.hidden = true;
        }

        var todayBtn = popup.querySelector('.jdp-today');
        todayBtn.disabled = !inRange(t);

        prevBtn.disabled = !!prevBlocked;
        nextBtn.disabled = !!nextBlocked;
    }

    function pickDay(d) {
        writeValue(currentInput, { jy: view.jy, jm: view.jm, jd: d });
        close();
    }

    function pickToday() {
        var t = todayJ();
        writeValue(currentInput, t);
        close();
    }

    function clearValue() {
        writeValue(currentInput, null);
        close();
    }

    /* ---------- موقعیت‌یابی ---------- */

    function isMobile() { return window.innerWidth < 560; }

    function position() {
        if (!popup || !currentInput) { return; }

        if (isMobile()) {
            popup.classList.add('jdp-mobile');
            backdropEl.hidden = false;
            return;
        }

        popup.classList.remove('jdp-mobile');
        backdropEl.hidden = true;

        var rect = currentInput.getBoundingClientRect();
        var pw = popup.offsetWidth || 288;
        var ph = popup.offsetHeight || 360;

        var top = rect.bottom + 8;
        if (top + ph > window.innerHeight - 8) { top = rect.top - ph - 8; }
        if (top < 8) { top = Math.max(8, (window.innerHeight - ph) / 2); }

        var right = window.innerWidth - rect.right; /* چون RTL — لبهٔ راست پاپ‌آور با لبهٔ راست اینپوت تراز شود */
        if (right + pw > window.innerWidth - 8) { right = window.innerWidth - pw - 8; }
        if (right < 8) { right = 8; }

        popup.style.top = top + 'px';
        popup.style.right = right + 'px';
        popup.style.left = 'auto';
        popup.style.bottom = 'auto';

        /* جهت انیمیشن */
        popup.classList.toggle('from-above', top < rect.top);
    }

    /* ---------- باز/بسته ---------- */

    function open(input) {
        ensurePopup();

        if (currentInput && currentInput !== input) {
            currentInput.classList.remove('jdp-active');
        }
        currentInput = input;
        input.classList.add('jdp-active');

        var val = valueOf(input);
        var t = todayJ();
        if (val) { view = { mode: 'days', jy: val.jy, jm: val.jm }; }
        else { view = { mode: 'days', jy: t.jy, jm: t.jm }; }

        render();

        popup.hidden = false;
        popup.classList.add('is-open');
        position();
    }

    function close() {
        if (!popup) { return; }
        popup.classList.remove('is-open');
        popup.hidden = true;
        backdropEl.hidden = true;
        if (currentInput) {
            currentInput.classList.remove('jdp-active');
            currentInput = null;
        }
    }

    /* ---------- بایند ---------- */

    function bind(input) {
        if (!input || input.dataset.jdpBound === '1') { return; }
        input.dataset.jdpBound = '1';
        input.setAttribute('autocomplete', 'off');

        input.addEventListener('click', function (e) {
            e.preventDefault();
            if (isOpen() && currentInput === input) { close(); return; }
            open(input);
        });

        input.addEventListener('focus', function () {
            if (!isOpen() || currentInput !== input) { open(input); }
        });

        /* تایپ دستی: Enter/blur → اعتبارسنجی و نوشتن */
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                commitTyped(input);
                close();
            }
        });

        input.addEventListener('blur', function () {
            setTimeout(function () {
                if (isOpen() && currentInput === input && !popup.contains(document.activeElement)) {
                    commitTyped(input);
                }
            }, 120);
        });
    }

    function commitTyped(input) {
        var parsed = parseJalali(input.value);
        if (input.value.trim() === '') {
            writeValue(input, null);
            return;
        }
        if (!parsed) {
            /* مقدار نامعتبر → بازگرداندن مقدار قبلی معتبر */
            input.value = formatJalali(valueOf(input));
            return;
        }
        writeValue(input, parsed);
    }

    function bindAll(scope) {
        var root = scope || document;
        var list = root.querySelectorAll ? root.querySelectorAll('input[data-jdp]') : [];
        for (var i = 0; i < list.length; i++) { bind(list[i]); }
        /* اگر خود scope هم اینپوت باشد */
        if (root && root.matches && root.matches('input[data-jdp]')) { bind(root); }
    }

    /* مقداردهی برنامه‌ای برای اینپوت‌های gregorian (مثلاً چیپ‌های از قبل تعریف‌شده) */
    function setGregorian(input, gregorian /* 'Y-m-d' یا null */) {
        if (!input) { return; }
        var g = null;
        if (gregorian) {
            var m = String(gregorian).match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
            if (m) { g = { gy: +m[1], gm: +m[2], gd: +m[3] }; }
        }
        var j = g ? toJalaali(g.gy, g.gm, g.gd) : null;
        writeValue(input, j);
    }

    /* شروع: بایند خودکار همهٔ اینپوت‌های موجود */
    function boot() { bindAll(document); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.CNJdp = {
        bindAll: bindAll,
        bind: bind,
        setGregorian: setGregorian,
        close: close,
        toJalaali: toJalaali,
        toGregorian: toGregorian,
        formatJalali: formatJalali,
        formatGregorian: formatGregorian,
        parseJalali: parseJalali,
    };
})();
