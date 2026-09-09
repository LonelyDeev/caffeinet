/**
 * کافی‌نت آنلاین — اسکریپت صفحه «داشبورد» (پنل سازمان)
 * فایل مستقل (Blade + jQuery + Chart.js vendored) — بدون Node / بدون بیلد
 *
 * فاز ۹: نمودار «واریزی کیف پول سازمان» و «سفارش کافی‌نت‌های زیرمجموعه»
 */
(function () {
    'use strict';

    function renderCharts() {
        if (typeof window.PanelCharts === 'undefined' || !window.PanelCharts.isSupported()) {
            return;
        }

        const data = App.pageData() || {};
        const credits = data.credits || [];
        const coffeenets = data.coffeenets || [];

        if (document.getElementById('org-credits')) {
            PanelCharts.line('org-credits', {
                labels: credits.map(c => c.label),
                datasets: [
                    { label: 'واریزی کیف پول (تومان)', data: credits.map(c => c.credit), key: 'teal', money: true }
                ],
                emptyMessage: '۳۰ روز اخیر واریزی به کیف شما ثبت نشده است'
            });
        }

        if (document.getElementById('org-coffeenets')) {
            const reversed = coffeenets.slice().reverse();
            PanelCharts.bars('org-coffeenets', {
                labels: reversed.map(c => truncate(c.name, 16)),
                datasets: [
                    { label: 'سفارش', data: reversed.map(c => c.count), key: 'copper', money: false }
                ],
                horizontal: true,
                xMoney: false,
                emptyMessage: 'کافی‌نت‌های شما در ۳۰ روز اخیر سفارشی نداشته‌اند'
            });
        }
    }

    function truncate(s, len) {
        s = String(s ?? '');
        return s.length > len ? s.slice(0, len - 1) + '…' : s;
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
