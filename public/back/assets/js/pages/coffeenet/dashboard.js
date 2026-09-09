/**
 * کافی‌نت آنلاین — اسکریپت صفحه «داشبورد» (پنل کافی‌نت)
 * فایل مستقل (Blade + jQuery + Chart.js vendored) — بدون Node / بدون بیلد
 *
 * فاز ۹: نمودار «روند ۱۴ روز اخیر» (سفارش + واریزی کیف) و «وضعیت سفارش‌ها»
 */
(function () {
    'use strict';

    /* شمارنده متحرک کارت کارمندان */
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.querySelector('.count-up');
        if (!el) return;
        const target = +el.dataset.value || 0;
        if (target <= 0) { el.textContent = fa(0); return; }
        const dur = 800, t0 = performance.now();
        function tick(t) {
            const p = Math.min(1, (t - t0) / dur);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = fa(Math.round(target * eased));
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
        function fa(n) { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
    });

    /* ---------- نمودارهای فاز ۹ ---------- */
    function renderCharts() {
        if (typeof window.PanelCharts === 'undefined' || !window.PanelCharts.isSupported()) {
            return;
        }

        const data = App.pageData() || {};
        const daily = data.daily || [];
        const status = data.status || [];
        const credits = data.credits || [];

        // برچسب واریزی کیف از روی سری سفارش‌ها (هم‌طول درآمد نمی‌شود؛ از خودش)
        if (document.getElementById('net-daily')) {
            PanelCharts.line('net-daily', {
                labels: daily.map(d => d.label),
                datasets: [
                    { label: 'سفارش ثبت‌شده', data: daily.map(d => d.orders), key: 'copper', money: false, axis: 'right' },
                    { label: 'واریزی کیف پول (تومان)', data: credits.map(c => c.credit), key: 'teal', money: true }
                ],
                emptyMessage: '۱۴ روز اخیر سفارشی ثبت نشده است'
            });
        }

        if (document.getElementById('net-status')) {
            PanelCharts.donut('net-status', {
                labels: status.map(s => s.label),
                values: status.map(s => s.count),
                keys: status.map(s => s.color),
                emptyMessage: 'سفارشی در ۱۴ روز اخیر نیست'
            });
        }
    }

    let booted = false;
    function boot() {
        if (booted) return;
        if (typeof window.App === 'undefined') return;
        booted = true;
        renderCharts();
    }

    if (typeof window.App !== 'undefined' && typeof window.PanelCharts !== 'undefined') boot();
    else {
        window.addEventListener('app:ready', boot, { once: true });
        setTimeout(boot, 2500);
    }
})();
