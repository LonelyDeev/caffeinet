/* ============================================================
   کافی‌نت آنلاین — اسکریپت صفحه فرود
   (بدون jQuery — سبک و مستقل)
   ============================================================ */
(function () {
    'use strict';

    var doc = document;
    var $ = function (sel, ctx) { return (ctx || doc).querySelector(sel); };

    /* ---------- فعال‌بودن JS (برای reveal تدریجی) ---------- */
    doc.documentElement.classList.add('js');

    /* ---------- هدر: سایه هنگام اسکرول ---------- */
    var header = $('#siteHeader');
    var toTop = $('#toTop');

    function onScroll() {
        var y = window.scrollY || window.pageYOffset || 0;
        if (header) header.classList.toggle('is-scrolled', y > 12);
        if (toTop) toTop.classList.toggle('is-visible', y > 620);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ---------- منوی موبایل ---------- */
    var burger = $('#navBurger');
    var mobileNav = $('#mobileNav');

    function closeMenu() {
        if (!burger || !mobileNav) return;
        burger.setAttribute('aria-expanded', 'false');
        mobileNav.classList.remove('is-open');
    }

    if (burger && mobileNav) {
        burger.addEventListener('click', function () {
            var open = burger.getAttribute('aria-expanded') === 'true';
            burger.setAttribute('aria-expanded', open ? 'false' : 'true');
            mobileNav.classList.toggle('is-open', !open);
        });

        // بستن با کلیک روی لینک‌ها
        mobileNav.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', closeMenu);
        });

        // بستن با Escape
        doc.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });

        // بستن با کلیک بیرون
        doc.addEventListener('click', function (e) {
            if (mobileNav.classList.contains('is-open') &&
                !mobileNav.contains(e.target) && !burger.contains(e.target)) {
                closeMenu();
            }
        });
    }

    /* ---------- دکمه بازگشت به بالا ---------- */
    if (toTop) {
        toTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ---------- انیمیشن ورود (IntersectionObserver) ---------- */
    var reveals = doc.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -36px 0px' });

        reveals.forEach(function (el) { io.observe(el); });

        // اطمینان: هر چیزی بعد از ۲.۲ ثانیه هنوز مخفی ماند (IO خراب/فول‌پیج) مرئی شود
        window.setTimeout(function () {
            reveals.forEach(function (el) { el.classList.add('is-in'); });
        }, 2200);
    } else {
        reveals.forEach(function (el) { el.classList.add('is-in'); });
    }

    /* ---------- شمارندهٔ عددی آمار ---------- */
    function faDigits(n) {
        return String(n).replace(/\d/g, function (d) {
            return '۰۱۲۳۴۵۶۷۸۹'[+d];
        });
    }

    function animateCount(el) {
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (target <= 0) { el.textContent = faDigits(0); return; }

        var duration = 1600;
        var start = null;

        function frame(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / duration, 1);
            // easeOutCubic
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = faDigits(Math.max(1, Math.round(target * eased)));
            if (p < 1) window.requestAnimationFrame(frame);
            else el.textContent = faDigits(target);
        }

        window.requestAnimationFrame(frame);
    }

    var counters = doc.querySelectorAll('.stat-num[data-count]');

    if (counters.length && 'IntersectionObserver' in window) {
        var cio = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCount(entry.target);
                    cio.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        counters.forEach(function (el) { cio.observe(el); });
    } else {
        counters.forEach(function (el) {
            el.textContent = faDigits(el.getAttribute('data-count') || 0);
        });
    }

    /* ---------- سوالات متداول (آکاردئون) ---------- */
    doc.querySelectorAll('.faq-item').forEach(function (item) {
        var btn = item.querySelector('.faq-q');
        var panel = item.querySelector('.faq-a');
        if (!btn || !panel) return;

        btn.addEventListener('click', function () {
            var isOpen = item.classList.contains('is-open');

            // بستن بقیه (آکاردئون تک‌بازشو)
            doc.querySelectorAll('.faq-item.is-open').forEach(function (other) {
                if (other === item) return;
                other.classList.remove('is-open');
                other.querySelector('.faq-a').style.maxHeight = '';
                other.querySelector('.faq-q').setAttribute('aria-expanded', 'false');
            });

            item.classList.toggle('is-open', !isOpen);
            btn.setAttribute('aria-expanded', String(!isOpen));
            panel.style.maxHeight = !isOpen ? (panel.scrollHeight + 24) + 'px' : '';
        });
    });

    /* ---------- اسکرول نرم برای لینک‌های داخلی (fallback مرورگرهای قدیمی) ---------- */
    doc.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var id = a.getAttribute('href');
            if (!id || id === '#') return;
            var target = doc.querySelector(id);
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
