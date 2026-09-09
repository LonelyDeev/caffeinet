/**
 * بوت تم (ضد-FOUC) — باید در <head> و قبل از استایل‌ها به‌صورت parser-blocking لود شود.
 * (فایل خارجی = سازگار با CSP سخت‌گیرانه؛ اسکریپت درون‌خطی لازم نیست)
 *
 * ترتیب اولویت:
 *   ۱. تم ذخیره‌شدهٔ کاربر (localStorage «caffeinet-theme»)
 *   ۲. تم سیستم‌عامل (prefers-color-scheme)
 */
(function () {
    'use strict';

    try {
        var stored = localStorage.getItem('caffeinet-theme');

        var wantsDark = stored === 'dark' || (!stored && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);

        if (wantsDark) {
            document.documentElement.classList.add('dark');
        }

        // اگر تم سیستم عوض شود و کاربر انتخاب صریح نداشته باشد، همگام بمان (فقط اولین لود)
        if (!stored && window.matchMedia) {
            try {
                matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
                    try { localStorage.removeItem('caffeinet-theme'); } catch (err) { /* noop */ }
                    if (window.PanelUI && window.PanelUI.theme) {
                        window.PanelUI.theme.set(e.matches ? 'dark' : 'light', { persist: false });
                    } else {
                        document.documentElement.classList.toggle('dark', e.matches);
                    }
                });
            } catch (err) { /* Safari قدیمی */ }
        }
    } catch (e) { /* localStorage در دسترس نیست — تم سیستم اعمال نمی‌شود */ }
})();
