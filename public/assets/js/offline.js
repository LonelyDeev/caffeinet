/* کافی‌نت آنلاین — منطق صفحه آفلاین (PWA فاز ۱۴)
 * فایل خارجی است چون CSP پروژه اسکریپت درون‌خطی را بلاک می‌کند (script-src 'self').
 * توسط Service Worker پیش‌کش می‌شود تا در حالت آفلاین هم کار کند.
 */
(function () {
    'use strict';

    var status = document.getElementById('status');
    var statusText = document.getElementById('statusText');
    var retry = document.getElementById('retry');

    if (!status || !retry) return;

    function paint() {
        var on = navigator.onLine;
        status.classList.toggle('on', on);
        statusText.textContent = on
            ? 'اتصال برقرار است — «تلاش دوباره» را بزنید'
            : 'آفلاین';
    }
    paint();

    function go() {
        if (!navigator.onLine) { return; }
        retry.disabled = true;
        retry.classList.add('spin');
        // اگر صفحه از مسیری آفلاین شده بود، به همان مسیر برگرد؛ وگرنه خانه
        var back = location.search.match(/[?&]to=([^&]+)/);
        location.replace(back ? decodeURIComponent(back[1]) : '/');
    }

    retry.addEventListener('click', function () {
        if (navigator.onLine) { go(); return; }
        paint();
    });

    // بازگشت خودکار فقط با رویداد واقعیِ «آنلاین شدن» (نه لود صفحه) — ضد-حلقه
    window.addEventListener('online', function () {
        statusText.textContent = 'آنلاین شد — در حال بازگشت…';
        setTimeout(go, 600);
    });
    window.addEventListener('offline', paint);
})();
