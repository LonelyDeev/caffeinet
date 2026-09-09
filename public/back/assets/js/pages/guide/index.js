/**
 * کافی‌نت آنلاین — راهنمای پنل (فاز ۱۳) — فهرست راهنماها
 * پیشرفت مطالعه با localStorage (سمت کاربر — بدون سرور)
 */
(function () {
    'use strict';

    var KEY = 'guide-read:' + location.pathname.split('/')[1];

    function readSet() {
        try { return new Set(JSON.parse(localStorage.getItem(KEY) || '[]')); }
        catch (e) { return new Set(); }
    }

    function saveSet(set) {
        try { localStorage.setItem(KEY, JSON.stringify(Array.from(set))); } catch (e) { /* noop */ }
    }

    function faDigits(n) {
        return String(n).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
    }

    var set = readSet();
    var cards = document.querySelectorAll('[data-slug]');
    var total = cards.length;

    // نشان خوانده‌شده روی کارت‌ها
    cards.forEach(function (card) {
        var slug = card.getAttribute('data-slug');
        if (set.has(slug)) {
            var dot = document.querySelector('[data-read="' + slug + '"]');
            if (dot) { dot.classList.remove('hidden'); card.classList.add('guide-read'); }
        }
    });

    // نوار پیشرفت
    var pct = total ? Math.round(set.size / total * 100) : 0;
    var bar = document.getElementById('guide-progress-bar');
    var num = document.getElementById('guide-progress-num');
    if (bar) { bar.style.width = pct + '%'; }
    if (num) { num.textContent = faDigits(pct) + '٪'; }
})();
