/**
 * کافی‌نت آنلاین — اسکریپت صفحه «گزارش تحلیلی» (فاز ۹)
 * فایل مستقل (Blade + jQuery + Chart.js vendored) — بدون Node / بدون بیلد
 *
 * فیلتر بازه (پریست/سفارشی) → GET /admin/analytics/data → رندر نمودارها + جدول‌ها + خلاصه
 * دکمه‌های خروجی CSV → /admin/analytics/export?scope=…
 */
(function () {
    'use strict';

    var state = { preset: '30', from: '', to: '', custom: false };
    var charts = {};   // نمودارهای ساخته‌شده برای تخریب هنگام رفرش

    var $rangeLabel = null;

    /* ---------- خواندن وضعیت اولیه از سرور ---------- */
    function initState() {
        var data = App.pageData() || {};
        state.preset = data.preset || '30';
        state.from = data.from || '';
        state.to = data.to || '';
        state.custom = state.preset === 'custom';
        $rangeLabel = document.getElementById('range-label');
    }

    /* ---------- پارامترهای درخواست ---------- */
    function query() {
        var params = new URLSearchParams();
        if (state.custom && state.from && state.to) {
            params.set('from', state.from);
            params.set('to', state.to);
        } else {
            params.set('preset', state.preset);
        }
        return params;
    }

    /* ---------- به‌روزرسانی لینک‌های خروجی ---------- */
    function syncExportLinks() {
        var params = query().toString();
        var base = (App.pageData('exportBase') || '/admin/analytics/export');
        document.querySelectorAll('a.an-export[data-scope]').forEach(function (a) {
            a.setAttribute('href', App.url(base + '?' + params + '&scope=' + a.dataset.scope));
        });
    }

    /* ---------- حالت بارگذاری (اسکلتون) ---------- */
    function setLoading(on) {
        ['operators-rows', 'coffeenets-rows', 'customers-rows'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) { return; }
            var span = id === 'customers-rows' ? 3 : 4;
            el.innerHTML = on
                ? '<tr><td colspan="' + span + '" class="!py-6"><div class="space-y-3">' +
                  '<div class="ui-skeleton h-3.5 w-full"></div><div class="ui-skeleton h-3.5 w-3/4"></div>' +
                  '<div class="ui-skeleton h-3.5 w-2/3"></div></div></td></tr>'
                : el.innerHTML;
        });
    }

    /* ---------- تخریب نمودارهای قبلی ---------- */
    function destroyCharts() {
        Object.keys(charts).forEach(function (key) {
            if (charts[key] && typeof charts[key].destroy === 'function') {
                charts[key].destroy();
            }
        });
        charts = {};
    }

    /* ---------- بارگذاری داده و رندر ---------- */
    async function load() {
        setLoading(true);
        syncExportLinks();

        try {
            var res = await App.ajax('/admin/analytics/data?' + query().toString());
            if (!res.ok) { throw new Error('http ' + res.status); }
            var data = await res.json();

            if ($rangeLabel && data.range && data.range.label) {
                $rangeLabel.textContent = data.range.label;
            }

            renderSummary(data.summary || {});
            renderDaily(data.daily || []);
            renderStatus(data.status || []);
            renderServices(data.services || []);
            renderOperators(data.operators || []);
            renderCoffeenets(data.coffeenets || []);
            renderCustomers(data.customers || []);
        } catch (e) {
            App.toast('بارگذاری گزارش تحلیلی ناموفق بود.', 'error');
            setLoading(false);
            return;
        }

        setLoading(false);
    }

    /* ---------- کارت‌های خلاصه ---------- */
    function renderSummary(s) {
        var grid = document.getElementById('summary-grid');
        if (!grid) { return; }

        var mini = [
            ['سفارش ثبت‌شده', App.digits(s.orders || 0), ''],
            ['پرداخت‌شده', App.digits(s.paid || 0), 'text-emerald-600'],
            ['حجم پرداخت', App.money(s.volume || 0, false), 'text-amber-600'],
            ['تسویهٔ کمیسیون', App.money(s.settled || 0, false), 'text-teal-600'],
            ['درآمد اپراتورها', App.money(s.operator_earnings || 0, false), 'text-rose-500'],
            ['مشتری فعال', App.digits(s.customers || 0), '']
        ];

        grid.innerHTML = mini.map(function (m) {
            return '<div class="an-mini"><div class="min-w-0">' +
                '<p class="an-mini-label truncate">' + m[0] + '</p>' +
                '<p class="an-mini-value ' + m[2] + '">' + m[1] + '</p></div></div>';
        }).join('');
    }

    /* ---------- روند روزانه (خطی دو-محوره) ---------- */
    function renderDaily(daily) {
        var labels = daily.map(function (d) { return d.label; });
        var orders = daily.map(function (d) { return d.orders; });
        var volume = daily.map(function (d) { return d.volume; });

        charts.daily = PanelCharts.line('chart-daily', {
            labels: labels,
            datasets: [
                { label: 'سفارش ثبت‌شده', data: orders, key: 'copper', money: false, axis: 'right' },
                { label: 'حجم پرداخت (تومان)', data: volume, key: 'amber', money: true }
            ],
            yMoney: true,
            y1Money: false,
            emptyMessage: 'در این بازه سفارشی ثبت نشده است'
        });
    }

    /* ---------- وضعیت (دونات) ---------- */
    function renderStatus(status) {
        var labels = status.map(function (s) { return s.label; });
        var values = status.map(function (s) { return s.count; });
        var keys = status.map(function (s) { return s.color; });

        charts.status = PanelCharts.donut('chart-status', {
            labels: labels,
            values: values,
            keys: keys,
            emptyMessage: 'سفارشی در این بازه نیست'
        });
    }

    /* ---------- خدمات پرتقاضا (ستونی افقی) ---------- */
    function renderServices(services) {
        var reversed = services.slice(0, 8).reverse(); // بزرگ‌ترین بالا
        var labels = reversed.map(function (s) { return truncate(s.name, 22); });
        var data = reversed.map(function (s) { return s.count; });

        charts.services = PanelCharts.bars('chart-services', {
            labels: labels,
            datasets: [{ label: 'تعداد سفارش', data: data, key: 'teal', money: false }],
            horizontal: true,
            xMoney: false,
            emptyMessage: 'سفارشی برای خدمات ثبت نشده است'
        });
    }

    /* ---------- جدول اپراتورها ---------- */
    function renderOperators(operators) {
        var el = document.getElementById('operators-rows');
        if (!el) { return; }

        if (!operators.length) {
            el.innerHTML = emptyRow(4, 'اپراتوری در این بازه سفارش نداشته است.');
            return;
        }

        el.innerHTML = operators.map(function (o) {
            return '<tr>' +
                '<td class="text-xs font-bold text-stone-700 max-w-44 truncate" title="' + esc(o.name) + '">' + esc(o.name) + '</td>' +
                '<td class="tabular-nums text-xs text-stone-600">' + App.digits(o.count) + '</td>' +
                '<td class="tabular-nums text-xs font-bold text-teal-600">' + App.digits(o.delivered) + '</td>' +
                '<td class="tabular-nums text-xs font-extrabold text-rose-500">' + App.money(o.earnings, false) + '</td>' +
                '</tr>';
        }).join('');
    }

    /* ---------- جدول کافی‌نت‌ها ---------- */
    function renderCoffeenets(coffeenets) {
        var el = document.getElementById('coffeenets-rows');
        if (!el) { return; }

        if (!coffeenets.length) {
            el.innerHTML = emptyRow(4, 'کافی‌نتی در این بازه سفارش نداشته است.');
            return;
        }

        el.innerHTML = coffeenets.map(function (c) {
            return '<tr>' +
                '<td class="text-xs font-bold text-stone-700 max-w-44 truncate" title="' + esc(c.name) + '">' + esc(c.name) + '</td>' +
                '<td class="tabular-nums text-xs text-stone-600">' + App.digits(c.count) + '</td>' +
                '<td class="tabular-nums text-xs text-stone-600">' + App.money(c.volume, false) + '</td>' +
                '<td class="tabular-nums text-xs font-extrabold text-amber-600">' + App.money(c.commission, false) + '</td>' +
                '</tr>';
        }).join('');
    }

    /* ---------- جدول مشتریان برتر ---------- */
    function renderCustomers(customers) {
        var el = document.getElementById('customers-rows');
        if (!el) { return; }

        if (!customers.length) {
            el.innerHTML = emptyRow(3, 'مشتری‌ای در این بازه سفارش نداشته است.');
            return;
        }

        el.innerHTML = customers.map(function (c) {
            return '<tr>' +
                '<td class="text-xs font-bold text-stone-700 max-w-44 truncate" title="' + esc(c.name) + '">' + esc(c.name) + '</td>' +
                '<td class="tabular-nums text-xs text-stone-600">' + App.digits(c.count) + '</td>' +
                '<td class="tabular-nums text-xs font-extrabold text-emerald-600">' + App.money(c.spent, false) + '</td>' +
                '</tr>';
        }).join('');
    }

    /* ---------- رویداد فیلترها ---------- */
    function bindFilters() {
        document.querySelectorAll('.an-chip[data-preset]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                document.querySelectorAll('.an-chip[data-preset]').forEach(function (c) { c.classList.remove('is-active'); });
                chip.classList.add('is-active');
                state.preset = chip.dataset.preset;
                state.custom = false;
                document.getElementById('apply-custom').style.display = 'none';
                load();
            });

            if (chip.dataset.preset === state.preset) {
                chip.classList.add('is-active');
            }
        });

        // حالت سفارشی: تغییر تاریخ‌ها دکمهٔ «اعمال» را نمایان می‌کند
        var fromInput = document.getElementById('from-input');
        var toInput = document.getElementById('to-input');
        var applyBtn = document.getElementById('apply-custom');

        function revealApply() {
            if (fromInput.value && toInput.value) {
                applyBtn.style.display = '';
            }
        }

        fromInput.addEventListener('change', revealApply);
        toInput.addEventListener('change', revealApply);

        applyBtn.addEventListener('click', function () {
            var gFrom = document.getElementById('from-input-g')?.value || '';
            var gTo = document.getElementById('to-input-g')?.value || '';
            if (!gFrom || !gTo) {
                App.toast('هر دو تاریخ «از» و «تا» را انتخاب کنید.', 'warn');
                return;
            }
            state.custom = true;
            state.from = gFrom;
            state.to = gTo;
            document.querySelectorAll('.an-chip[data-preset]').forEach(function (c) { c.classList.remove('is-active'); });
            load();
        });
    }

    /* ---------- ابزارها ---------- */
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function truncate(s, len) {
        s = String(s ?? '');
        return s.length > len ? s.slice(0, len - 1) + '…' : s;
    }

    function emptyRow(span, message) {
        return '<tr><td colspan="' + span + '" class="!py-10"><div class="ui-empty">' +
            '<span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 16h.01"/><path d="M11 12h.01"/><path d="M15 8h.01"/><path d="M19 4h.01"/></svg></span>' +
            '<p class="text-sm font-semibold text-stone-500">' + message + '</p></div></td></tr>';
    }

    /* ---------- شروع ---------- */
    var booted = false;
    function boot() {
        if (booted) { return; }
        if (typeof window.App === 'undefined' || !document.getElementById('chart-daily')) { return; }
        booted = true;

        initState();
        bindFilters();
        load();
    }

    if (typeof window.App !== 'undefined') { boot(); }
    window.addEventListener('app:ready', boot, { once: true });
    setTimeout(boot, 2500);
})();
