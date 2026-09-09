/**
 * کافی‌نت آنلاین — راهنمای پنل (فاز ۱۳) — صفحهٔ راهنما
 * «خواندم» با localStorage + اسکرول نرم فهرست مطالب
 */
(function () {
    'use strict';

    var KEY = 'guide-read:' + location.pathname.split('/')[1];

    function readSet() {
        try { return new Set(JSON.parse(localStorage.getItem(KEY) || '[]')); }
        catch (e) { return new Set(); }
    }

    var markBtn = document.getElementById('gd-mark');
    var slug = markBtn ? markBtn.getAttribute('data-slug') : null;

    function setDone(done) {
        var set = readSet();
        if (done) { set.add(slug); } else { set.delete(slug); }
        try { localStorage.setItem(KEY, JSON.stringify(Array.from(set))); } catch (e) { /* noop */ }
    }

    function syncBtn() {
        if (! markBtn) { return; }
        var done = readSet().has(slug);
        markBtn.classList.toggle('done', done);
        var span = markBtn.querySelector('span');
        if (span) { span.textContent = done ? 'خوانده شد ✓ — لغو' : 'خواندم — علامت‌گذاری'; }
    }

    if (markBtn && slug) {
        markBtn.addEventListener('click', function () {
            var done = ! readSet().has(slug);
            setDone(done);
            syncBtn();
            if (done && window.App && App.toast) {
                App.toast('عالی! این راهنما خوانده‌شده علامت خورد.', 'success');
            }
        });
        syncBtn();
    }

    // اسکرول نرم برای فهرست مطالب
    document.querySelectorAll('.gd-toc a[href^="#gd-sec-"]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var target = document.querySelector(link.getAttribute('href'));
            if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });
})();
