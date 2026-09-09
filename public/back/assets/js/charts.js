/**
 * کافی‌نت آنلاین — رَپر مشترک نمودارها (فاز ۹)
 * فایل مستقل — بدون Node / بدون بیلد (Chart.js UMD از vendor لود شده است)
 *
 * PanelCharts.line(id, cfg)   → نمودار خطی/سطحی (+ پشتیبانی محور دوم)
 * PanelCharts.bars(id, cfg)   → نمودار ستونی (افقی هم پشتیبانی می‌شود)
 * PanelCharts.donut(id, cfg)  → نمودار دایره‌ای
 * PanelCharts.updateTheme()   → همگام‌سازی رنگ‌ها با تم روشن/تاریک
 *
 * سازوکار تم: رنگ متن/خط شبکه از توکن‌های CSS تم (--th-*) خوانده می‌شود؛
 * با رویداد ui:theme (PanelUI.theme) همهٔ نمودارهای ثبت‌شده بازرنگ می‌شوند.
 */
window.PanelCharts = (function () {
    'use strict';

    var registry = [];   // همهٔ نمودارهای ساخته‌شده صفحه
    var ready = typeof window.Chart !== 'undefined';

    /* ---------- پالت برند (بدون آبی/نیلی — هماهنگ با تم قهوه‌ای-کهربایی) ---------- */
    var PALETTE = {
        amber:   { light: '#d97706', dark: '#f5b453' },
        copper:  { light: '#c47f3d', dark: '#e0a165' },
        teal:    { light: '#0d9488', dark: '#2dd4bf' },
        emerald: { light: '#059669', dark: '#34d399' },
        orange:  { light: '#ea580c', dark: '#fb923c' },
        rose:    { light: '#e11d48', dark: '#fb7185' },
        sky:     { light: '#0e7490', dark: '#38bdf8' },
        stone:   { light: '#78716c', dark: '#b5aba0' }
    };

    var SERIES_KEYS = ['amber', 'copper', 'teal', 'emerald', 'orange', 'rose', 'sky', 'stone'];

    function isDark() {
        return document.documentElement.classList.contains('dark');
    }

    function token(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    /* رنگ‌های تم: متن/خط/شبکه — از توکن‌های theme.css */
    function themeColors() {
        return {
            ink: token('--th-ink', '#44403c'),
            soft: token('--th-ink-soft', '#78716c'),
            faint: token('--th-ink-faint', '#a8a29e'),
            line: token('--th-line', '#e7e5e4'),
            grid: isDark() ? 'rgba(181, 171, 160, 0.14)' : 'rgba(120, 113, 108, 0.13)'
        };
    }

    function color(key) {
        var c = PALETTE[key] || PALETTE.amber;
        return isDark() ? c.dark : c.light;
    }

    function hexA(hex, alpha) {
        // hex → rgba با شفافیت
        if (hex.charAt(0) !== '#') { return hex; }
        var r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    function fa(v) {
        return (Number(v) || 0).toLocaleString('fa-IR', { maximumFractionDigits: 0 });
    }

    function faMoney(v) {
        return fa(v) + ' تومان';
    }

    /* ---------- اعمال پیش‌فرض‌ها ---------- */
    function applyDefaults() {
        if (!ready) { return; }
        var t = themeColors();
        var d = Chart.defaults;
        d.font.family = "'Vazirmatn', 'Segoe UI', sans-serif";
        d.font.size = 11;
        d.color = t.soft;
        d.borderColor = t.line;
        d.plugins.legend.labels.color = t.soft;
        d.plugins.legend.labels.usePointStyle = true;
        d.plugins.legend.labels.boxWidth = 8;
        d.plugins.legend.labels.boxHeight = 8;
        d.plugins.legend.labels.padding = 14;
        d.plugins.legend.rtl = true;
        d.plugins.legend.textDirection = 'rtl';
        d.plugins.tooltip.rtl = true;
        d.plugins.tooltip.textDirection = 'rtl';
        d.plugins.tooltip.backgroundColor = isDark() ? 'rgba(42, 38, 35, 0.96)' : 'rgba(68, 64, 60, 0.94)';
        d.plugins.tooltip.titleColor = '#faf8f3';
        d.plugins.tooltip.bodyColor = '#e9e2d9';
        d.plugins.tooltip.padding = 10;
        d.plugins.tooltip.cornerRadius = 10;
        d.plugins.tooltip.boxPadding = 5;
        d.plugins.tooltip.displayColors = true;
        d.transitions.active.animation.duration = 180;
    }

    /* ---------- خالی‌بودن داده + پوشش «داده‌ای نیست» ---------- */
    function emptyState(canvas, message) {
        var wrap = canvas.parentElement;
        if (!wrap || !wrap.classList.contains('an-chart')) { return; }
        var existing = wrap.querySelector('.an-empty');
        if (existing) { existing.remove(); }

        var el = document.createElement('div');
        el.className = 'an-empty';
        el.innerHTML = '<span class="an-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
            'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/>' +
            '<path d="M7 16h.01"/><path d="M11 12h.01"/><path d="M15 8h.01"/><path d="M19 4h.01"/></svg></span>' +
            '<p>' + (message || 'داده‌ای برای نمایش نیست') + '</p>';
        wrap.appendChild(el);
    }

    function clearEmptyState(canvas) {
        var wrap = canvas.parentElement;
        var existing = wrap ? wrap.querySelector('.an-empty') : null;
        if (existing) { existing.remove(); }
    }

    function hasData(datasets) {
        return datasets.some(function (ds) {
            return ds.data && ds.data.some(function (v) { return (Number(v) || 0) !== 0; });
        });
    }

    /* بوم را آزاد می‌کند (نمودار قبلی تخریب) + حالت خالی — برای دادهٔ تهی */
    function resetCanvas(canvasId, message) {
        if (!ready) { return; }
        var canvas = toCanvas(canvasId);
        if (!canvas) { return; }
        try {
            var existing = Chart.getChart(canvas);
            if (existing) {
                existing.destroy();
                registry = registry.filter(function (c) { return c !== existing; });
            }
        } catch (e) { /* noop */ }
        emptyState(canvas, message);
    }

    /* ---------- آپشن‌های مشترک محورها ---------- */
    function axisMoney(extra) {
        return Object.assign({
            ticks: {
                color: themeColors().soft,
                callback: function (v) { return fa(v); },
                maxTicksLimit: 6
            },
            grid: { color: themeColors().grid, drawTicks: false }
        }, extra || {});
    }

    function axisCount(extra) {
        return Object.assign({
            beginAtZero: true,
            ticks: {
                color: themeColors().soft,
                precision: 0,
                callback: function (v) { return fa(v); },
                maxTicksLimit: 6
            },
            grid: { color: themeColors().grid, drawTicks: false }
        }, extra || {});
    }

    function categoryAxis() {
        return {
            ticks: { color: themeColors().soft, autoSkip: true, maxRotation: 0, minRotation: 0 },
            grid: { display: false }
        };
    }

    /* دیتاست با تخصیص خودکار رنگ از پالت */
    function paletteAt(i) {
        return SERIES_KEYS[i % SERIES_KEYS.length];
    }

    /* ---------- سازندهٔ عمومی ---------- */
    function build(canvasId, config) {
        if (!ready) { return null; }
        var canvas = typeof canvasId === 'string' ? document.getElementById(canvasId) : canvasId;
        if (!canvas) { return null; }

        // تخریب نمودار قبلی روی همین بوم (رندر مجدد امن — Chart.js اجازهٔ بومِ اشغالی نمی‌دهد)
        try {
            var existing = Chart.getChart(canvas);
            if (existing) {
                existing.destroy();
                registry = registry.filter(function (c) { return c !== existing; });
            }
        } catch (e) { /* بوم آزاد است */ }

        var instance = new Chart(canvas.getContext('2d'), config);
        instance.__pcId = typeof canvasId === 'string' ? canvasId : (canvas.id || '');
        registry.push(instance);
        return instance;
    }


    /* رزولوشن بوم: canvasId می‌تواند ID رشته‌ای یا خودِ element باشد */
    function toCanvas(canvasId) {
        return typeof canvasId === 'string' ? document.getElementById(canvasId) : canvasId;
    }

    /* ================== نمودار خطی/سطحی ==================
     * cfg = { labels: [], datasets: [{label, data, key?, money?, axis?|'count'|'right'}],
     *        fill?, smooth?, yMoney?, title? }
     */
    function line(canvasId, cfg) {
        if (!ready) { return null; }
        cfg = cfg || {};
        var datasets = (cfg.datasets || []).map(function (ds, i) {
            var key = ds.key || paletteAt(i);
            var hex = color(key);
            var money = ds.money !== undefined ? ds.money : true;
            return {
                label: ds.label || '',
                data: ds.data || [],
                __key: key,
                __money: money,
                borderColor: hex,
                backgroundColor: cfg.fill === false ? 'transparent' : hexA(hex, isDark() ? 0.22 : 0.14),
                borderWidth: 2.4,
                fill: cfg.fill === false ? false : true,
                tension: cfg.smooth === false ? 0 : 0.38,
                pointRadius: 0,
                pointHoverRadius: 4.5,
                pointHoverBackgroundColor: hex,
                pointHoverBorderColor: '#fff',
                yAxisID: ds.axis === 'right' ? 'y1' : 'y',
                __money: money
            };
        });

        if (!hasData(datasets)) {
            resetCanvas(canvasId, cfg.emptyMessage);
            return null;
        }
        clearEmptyState(toCanvas(canvasId));

        var hasRight = datasets.some(function (ds) { return ds.yAxisID === 'y1'; });

        var scales = {
            x: categoryAxis(),
            y: Object.assign(cfg.yMoney === false ? axisCount() : axisMoney(), { position: 'left' })
        };
        if (hasRight) {
            // محور دوم (تعداد) سمت راست — جدا از محور پول سمت چپ
            scales.y1 = Object.assign(cfg.y1Money === false ? axisCount() : axisMoney(), { position: 'right', grid: { display: false } });
        }

        return build(canvasId, {
            type: 'line',
            data: { labels: cfg.labels || [], datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: { duration: 620 },
                plugins: {
                    legend: { display: datasets.length > 1, position: 'bottom', align: 'end' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var money = ctx.dataset.__money !== false;
                                return ' ' + ctx.dataset.label + ': ' + (money ? faMoney(ctx.parsed.y) : fa(ctx.parsed.y));
                            }
                        }
                    }
                },
                scales: scales
            }
        });
    }

    /* ================== نمودار ستونی ==================
     * cfg = { labels, datasets: [{label, data, key?, money?}], horizontal?, stacked?, yMoney? }
     */
    function bars(canvasId, cfg) {
        if (!ready) { return null; }
        cfg = cfg || {};
        var horizontal = !!cfg.horizontal;

        var datasets = (cfg.datasets || []).map(function (ds, i) {
            var key = ds.key || paletteAt(i);
            var hex = color(key);
            var money = ds.money !== undefined ? ds.money : true;
            return {
                label: ds.label || '',
                data: ds.data || [],
                __key: key,
                __money: money,
                backgroundColor: hexA(hex, isDark() ? 0.78 : 0.72),
                hoverBackgroundColor: hex,
                borderColor: hex,
                borderWidth: 1.4,
                borderRadius: 7,
                borderSkipped: false,
                maxBarThickness: horizontal ? 22 : 38,
                __money: money
            };
        });

        if (!hasData(datasets)) {
            resetCanvas(canvasId, cfg.emptyMessage);
            return null;
        }
        clearEmptyState(toCanvas(canvasId));

        var yScale = horizontal ? Object.assign(categoryAxis(), { position: 'right' }) : (cfg.yMoney === false ? axisCount() : axisMoney());
        var xScale = horizontal ? Object.assign((cfg.xMoney === false ? axisCount() : axisMoney()), { reverse: true }) : categoryAxis();

        if (cfg.stacked) {
            yScale = Object.assign({}, yScale, { stacked: true });
            xScale = Object.assign({}, xScale, { stacked: true });
        }

        return build(canvasId, {
            type: 'bar',
            data: { labels: cfg.labels || [], datasets: datasets },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 620 },
                plugins: {
                    legend: { display: datasets.length > 1, position: 'bottom', align: 'end' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var money = ctx.dataset.__money !== false;
                                var v = horizontal ? ctx.parsed.x : ctx.parsed.y;
                                return ' ' + ctx.dataset.label + ': ' + (money ? faMoney(v) : fa(v));
                            }
                        }
                    }
                },
                scales: { x: xScale, y: yScale }
            }
        });
    }

    /* ================== نمودار دایره‌ای ==================
     * cfg = { labels: [], values: [], keys: [] یا colors: [], center?, emptyMessage? }
     */
    function donut(canvasId, cfg) {
        if (!ready) { return null; }
        cfg = cfg || {};
        var labels = cfg.labels || [];
        var values = (cfg.values || []).map(function (v) { return Number(v) || 0; });
        var total = values.reduce(function (a, b) { return a + b; }, 0);

        if (total <= 0) {
            resetCanvas(canvasId, cfg.emptyMessage);
            return null;
        }
        clearEmptyState(toCanvas(canvasId));

        var colors = values.map(function (_, i) {
            var key = (cfg.keys && cfg.keys[i]) || paletteAt(i);
            return color(key);
        });

        var instance = build(canvasId, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    __keys: (cfg.keys || SERIES_KEYS.slice()),
                    backgroundColor: colors,
                    hoverBackgroundColor: colors,
                    borderColor: isDark() ? '#2a2623' : '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                animation: { duration: 620, animateRotate: true },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: { padding: 14 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                                return ' ' + ctx.label + ': ' + fa(ctx.parsed) + ' (' + fa(pct) + '٪)';
                            }
                        }
                    }
                }
            }
        });

        if (instance) {
            instance.data.datasets[0].__keys = cfg.keys || SERIES_KEYS.slice();
        }

        return instance;
    }

    /* ================== تعویض تم ================== */
    function updateTheme() {
        if (!ready) { return; }
        applyDefaults();
        var t = themeColors();

        registry.slice().forEach(function (chart) {
            try {
                var type = chart.config.type;
                var opts = chart.options;

                // محورها
                Object.keys(opts.scales || {}).forEach(function (axisId) {
                    var axis = opts.scales[axisId];
                    if (axis.ticks) { axis.ticks.color = (axisId === 'y1' || axisId === 'y') ? t.soft : t.soft; }
                    if (axis.grid) { axis.grid.color = t.grid; }
                });

                // دیتاست‌ها
                (chart.data.datasets || []).forEach(function (ds, i) {
                    var hex = color(ds.__key || paletteAt(i));
                    if (type === 'line') {
                        ds.borderColor = hex;
                        if (ds.fill && ds.backgroundColor !== 'transparent') {
                            ds.backgroundColor = hexA(hex, isDark() ? 0.22 : 0.14);
                        }
                        ds.pointHoverBackgroundColor = hex;
                    } else if (type === 'bar') {
                        ds.backgroundColor = hexA(hex, isDark() ? 0.78 : 0.72);
                        ds.hoverBackgroundColor = hex;
                        ds.borderColor = hex;
                    } else if (type === 'doughnut' || type === 'pie') {
                        ds.backgroundColor = ds.backgroundColor.map(function (_, j) {
                            var key = (ds.__keys && ds.__keys[j]) || paletteAt(j);
                            return color(key);
                        });
                        ds.hoverBackgroundColor = ds.backgroundColor;
                        ds.borderColor = isDark() ? '#2a2623' : '#ffffff';
                    }
                });

                chart.update('none');
            } catch (e) { /* نمودار نیمه‌ساخته — نادیده */ }
        });
    }

    /* ثبت رویداد تعویض تم (jQuery event از PanelUI) + MutationObserver پشتیبان */
    function listenTheme() {
        if (window.jQuery) {
            window.jQuery(document).on('ui:theme', function () { updateTheme(); });
        }
        // پشتیبان: تغییر مستقیم کلاس .dark روی <html>
        if (window.MutationObserver) {
            var mo = new MutationObserver(function (records) {
                records.forEach(function (r) {
                    if (r.attributeName === 'class') { updateTheme(); }
                });
            });
            mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        }
    }

    /* ---------- شروع ---------- */
    function init() {
        if (!ready) {
            console.warn('[PanelCharts] Chart.js یافت نشد — vendor/chart.umd.min.js را قبل از این فایل لود کنید.');
            return;
        }
        applyDefaults();
        listenTheme();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* API عمومی */
    return {
        line: line,
        bars: bars,
        donut: donut,
        updateTheme: updateTheme,
        color: color,
        fa: fa,
        faMoney: faMoney,
        isSupported: function () { return ready; }
    };
})();
