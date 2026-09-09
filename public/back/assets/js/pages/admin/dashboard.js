/**
 * کافی‌نت آنلاین — اسکریپت صفحه «داشبورد» (پنل مدیریت کل)
 * فایل مستقل (Blade + jQuery + Chart.js vendored) — بدون Node / بدون بیلد
 *
 * فاز ۹: رندر نمودارهای «روند ۱۴ روز اخیر» و «وضعیت سفارش‌ها»
 */
(function () {
    'use strict';

    /* شمارنده انیمیشنی آمار */
    document.querySelectorAll('[data-count]').forEach(function (el) {
        var target = parseInt(el.dataset.count, 10) || 0;
        var start = null, dur = 900;
        function step(t) {
            if (!start) start = t;
            var p = Math.min((t - start) / dur, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString('fa-IR');
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });

    /* ---------- نمودارهای فاز ۹ ---------- */
    function renderCharts() {
        if (typeof window.PanelCharts === 'undefined' || !window.PanelCharts.isSupported()) {
            return;
        }

        var data = App.pageData() || {};
        var daily = data.daily || [];
        var status = data.status || [];

        if (document.getElementById('dash-daily')) {
            PanelCharts.line('dash-daily', {
                labels: daily.map(function (d) { return d.label; }),
                datasets: [
                    { label: 'سفارش ثبت‌شده', data: daily.map(function (d) { return d.orders; }), key: 'copper', money: false, axis: 'right' },
                    { label: 'حجم پرداخت (تومان)', data: daily.map(function (d) { return d.volume; }), key: 'amber', money: true }
                ],
                emptyMessage: '۱۴ روز اخیر سفارشی ثبت نشده است'
            });
        }

        if (document.getElementById('dash-status')) {
            PanelCharts.donut('dash-status', {
                labels: status.map(function (s) { return s.label; }),
                values: status.map(function (s) { return s.count; }),
                keys: status.map(function (s) { return s.color; }),
                emptyMessage: 'سفارشی در ۱۴ روز اخیر نیست'
            });
        }
    }

    var booted = false;
    function boot() {
        if (booted) { return; }
        if (typeof window.App === 'undefined') { return; }
        booted = true;
        renderCharts();
    }

    if (typeof window.App !== 'undefined' && typeof window.PanelCharts !== 'undefined') { boot(); }
    else {
        window.addEventListener('app:ready', boot, { once: true });
        document.addEventListener('DOMContentLoaded', function () {
            // PanelCharts بعد از DOMContentLoaded init می‌شود — کمی تأخیر امن
            setTimeout(boot, 60);
        });
        setTimeout(boot, 2500);
    }
})();
