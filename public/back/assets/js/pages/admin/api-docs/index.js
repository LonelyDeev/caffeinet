/**
 * کافی‌نت آنلاین — اسکریپت صفحه «مستندات API» (ادمین)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    'use strict';

    const search = document.getElementById('ad-search');
    const expandBtn = document.getElementById('ad-expand');
    const printBtn = document.getElementById('ad-print');
    const empty = document.querySelector('[data-empty]');
    const groups = Array.from(document.querySelectorAll('[data-group]'));
    const eps = Array.from(document.querySelectorAll('[data-ep]'));

    /* ---------- قالب‌بندی JSON (کلیدها زرد، رشته‌ها سبز) ---------- */

    function highlight(json) {
        return json
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"([^"\\]*(?:\\.[^"\\]*)*)"(\s*:)/g, '<span class="k">"$1"</span>$2')
            .replace(/:\s*"([^"\\]*(?:\\.[^"\\]*)*)"/g, ': <span class="s">"$1"</span>');
    }

    document.querySelectorAll('.ad-code pre[data-raw]').forEach((pre) => {
        let raw = pre.dataset.raw || '';
        try {
            raw = JSON.stringify(JSON.parse(raw));
        } catch (e) { /* همان متن */ }
        pre.innerHTML = highlight(raw);
    });

    /* ---------- باز/بسته ---------- */

    eps.forEach((ep) => {
        const head = ep.querySelector('[data-ep-toggle]');
        if (!head) return;

        const toggle = () => {
            const open = ep.classList.toggle('is-open');
            head.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        head.addEventListener('click', toggle);
        head.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });
    });

    let allOpen = false;

    if (expandBtn) {
        expandBtn.addEventListener('click', () => {
            allOpen = !allOpen;
            eps.forEach((ep) => ep.classList.toggle('is-open', allOpen));
            expandBtn.textContent = allOpen ? 'بستن همه' : 'بازکردن همه';
        });
    }

    /* ---------- کپی (با فالبک execCommand برای محیط غیر-HTTPS) ---------- */

    async function copyText(text) {
        // مسیر مدرن (نیازمند secure context)
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (e) { /* فالبک پایین */ }
        }

        // فالبک: textarea موقت + execCommand
        try {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '-1000px';
            document.body.appendChild(ta);
            ta.select();
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            return ok;
        } catch (e) {
            return false;
        }
    }

    document.querySelectorAll('.ad-copy').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const pre = btn.closest('.ad-code').querySelector('pre');
            const text = pre ? (pre.dataset.raw || pre.textContent) : '';

            const ok = await copyText(text);

            btn.textContent = ok ? 'کپی شد ✓' : 'خطا';
            btn.classList.toggle('is-done', ok);
            setTimeout(() => {
                btn.textContent = 'کپی';
                btn.classList.remove('is-done');
            }, 1400);
        });
    });

    /* ---------- جستجو ---------- */

    function fold(text) {
        return text.toLowerCase().replace(/\u200c/g, ' ').trim();
    }

    if (search) {
        search.addEventListener('input', () => {
            const q = fold(search.value);
            let found = 0;

            eps.forEach((ep) => {
                const haystack = fold(ep.textContent || '');
                const hit = !q || haystack.includes(q);

                ep.classList.toggle('is-found', hit && q.length > 0);
                if (q.length === 0) ep.classList.remove('is-found');

                if (hit) found++;
            });

            groups.forEach((g) => {
                const any = Array.from(g.querySelectorAll('[data-ep]'))
                    .some((ep) => fold(ep.textContent || '').includes(q));
                g.classList.toggle('is-hidden', !any);
            });

            if (empty) empty.hidden = found > 0;
        });
    }

    /* ---------- چاپ ---------- */

    if (printBtn) {
        printBtn.addEventListener('click', () => window.print());
    }

    /* ---------- ناوبری فعال ---------- */

    const navLinks = Array.from(document.querySelectorAll('[data-nav]'));

    if (navLinks.length && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                navLinks.forEach((a) => {
                    a.classList.toggle('is-active', a.dataset.nav === entry.target.id);
                });
            });
        }, { rootMargin: '-30% 0px -60% 0px' });

        groups.forEach((g) => io.observe(g));
    }
})();
