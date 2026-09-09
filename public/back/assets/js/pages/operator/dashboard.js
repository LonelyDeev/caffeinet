/**
 * کافی‌نت آنلاین — اسکریپت صفحه «داشبورد» پنل اپراتور
 * فایل مستقل (Blade + jQuery + Chart.js vendored) — بدون Node / بدون بیلد
 *
 * فاز ۹: نمودار «روند کارهای من» (تحویل + درآمد ۱۴ روز اخیر)
 */
(function () {
    /* شمارندهٔ متحرک همهٔ کارت‌های آماری */
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.count-up').forEach(el => {
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
        });
        function fa(n) { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
    });

    /* ---------- نمودار فاز ۹ ---------- */
    function renderCharts() {
        if (typeof window.PanelCharts === 'undefined' || !window.PanelCharts.isSupported()) {
            return;
        }
        if (!document.getElementById('op-daily')) { return; }

        const data = App.pageData() || {};
        const daily = data.daily || [];

        PanelCharts.line('op-daily', {
            labels: daily.map(d => d.label),
            datasets: [
                { label: 'تحویل‌شده', data: daily.map(d => d.delivered), key: 'copper', money: false, axis: 'right' },
                { label: 'درآمد تسویه (تومان)', data: daily.map(d => d.earnings), key: 'emerald', money: true }
            ],
            emptyMessage: '۱۴ روز اخیر کاری تحویل نداده‌اید'
        });
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
