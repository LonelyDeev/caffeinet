/**
 * کافی‌نت آنلاین — اسکریپت مشترک لایه پنل‌های مدیریت
 * (سایدبار موبایل + خروج AJAX + بج گفتگوهای ناخوانده) — مشترک بین پنل‌ها
 *
 * مسیرها از data-attribute روی <body> خوانده می‌شوند:
 *   <body data-logout-url="/admin/logout" data-login-url="/admin/login"
 *         data-chat-badge-url="/operator/chat/badge">   ← فقط پنل اپراتور (فاز ۷)
 */
(function () {
    /* ---------- منوی موبایل ---------- */
    const sidebar = document.getElementById('panel-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const toggle = document.getElementById('sidebar-toggle');

    function openSidebar() {
        sidebar.classList.remove('translate-x-full');
        overlay.classList.remove('hidden');
    }
    function closeSidebar() {
        sidebar.classList.add('translate-x-full');
        overlay.classList.add('hidden');
    }

    toggle?.addEventListener('click', () => {
        sidebar.classList.contains('translate-x-full') ? openSidebar() : closeSidebar();
    });
    overlay?.addEventListener('click', closeSidebar);

    /* ---------- اسکرول سایدبار به منوی فعال ----------
       وقتی صفحه‌ای باز می‌شود، منوی فعال (اگر پایین‌تر از دید باشد)
       در مرکز ناحیهٔ ناوبری قرار می‌گیرد (فقط اسکرول داخلی nav،
       بدون جابجایی خود صفحه). */
    function scrollToActiveNav() {
        const nav = sidebar?.querySelector('nav');
        if (!nav) return;
        const active = nav.querySelector('a.is-active, .nav-link.is-active');
        if (!active) return;
        const target = active.offsetTop - (nav.clientHeight / 2) + (active.offsetHeight / 2);
        if (target > 0) nav.scrollTop = target;
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scrollToActiveNav, { once: true });
    } else {
        scrollToActiveNav();
    }

    /* ---------- خروج (AJAX) با مودال زیبا ---------- */
    document.querySelectorAll('.logout-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (window.PanelUI) {
                window.PanelUI.confirm(
                    {
                        title: 'خروج از حساب',
                        desc: 'آیا مطمئن هستید که می‌خواهید از پنل خارج شوید؟',
                        okText: 'خروج از حساب',
                        danger: true,
                        icon: 'question'
                    },
                    doLogout
                );
            } else {
                doLogout();
            }
        });
    });

    async function doLogout() {
        const logoutUrl = document.body.dataset.logoutUrl || '/admin/logout';
        const loginUrl = document.body.dataset.loginUrl || '/admin/login';
        try {
            const res = await App.ajax(logoutUrl, { method: 'POST' });
            const data = await res.json().catch(() => ({}));
            window.location = data.redirect || loginUrl;
        } catch {
            window.location = loginUrl;
        }
    }

    /* ---------- بج گفتگوهای ناخوانده (فاز ۷ — فقط پنل اپراتور) ---------- */
    const badgeUrl = document.body.dataset.chatBadgeUrl;
    const badgeEl = document.getElementById('chatUnreadBadge');

    if (badgeUrl && badgeEl && typeof App !== 'undefined') {
        let badgeTimer = null;

        async function refreshBadge() {
            if (document.hidden) return; // تب مخفی — نیازی نیست
            try {
                const res = await App.ajax(badgeUrl);
                if (!res.ok) return;
                const data = await res.json();
                renderBadge(data.unseen || 0);
            } catch { /* بی‌صدا */ }
        }

        function renderBadge(count) {
            if (count > 0) {
                badgeEl.textContent = count > 99 ? '۹۹+' : fa(count);
                badgeEl.classList.add('on');
            } else {
                badgeEl.textContent = '';
                badgeEl.classList.remove('on');
            }
        }

        function fa(n) {
            return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        }

        // رویداد سراسری: صفحهٔ چت بعد از هر پولینگ بج را هم تازه می‌کند
        window.addEventListener('chat:unseen', (e) => renderBadge(e.detail || 0));

        refreshBadge();
        badgeTimer = setInterval(refreshBadge, 20000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshBadge();
        });
    }

    /* ---------- بج درخواست‌های در انتظار پذیرش (فاز ۱۱ — پنل اپراتور) ---------- */
    const reqBadgeUrl = document.body.dataset.requestsBadgeUrl;
    const reqBadgeEl = document.getElementById('requestsCountBadge');
    const reqPulseEl = document.getElementById('requestsPulseDot');

    if (reqBadgeUrl && (reqBadgeEl || reqPulseEl) && typeof App !== 'undefined') {
        async function refreshRequestsBadge() {
            if (document.hidden) return;
            try {
                const res = await App.ajax(reqBadgeUrl);
                if (!res.ok) return;
                const data = await res.json();
                renderRequestsBadge(data.count || 0);
            } catch { /* بی‌صدا */ }
        }

        function renderRequestsBadge(count) {
            if (reqBadgeEl) {
                if (count > 0) {
                    reqBadgeEl.textContent = count > 99 ? '۹۹+' : faReq(count);
                    reqBadgeEl.classList.add('on');
                } else {
                    reqBadgeEl.textContent = '';
                    reqBadgeEl.classList.remove('on');
                }
            }
            if (reqPulseEl) {
                reqPulseEl.classList.toggle('hidden', !(count > 0));
            }
            window.dispatchEvent(new CustomEvent('requests:count', { detail: count || 0 }));
        }

        function faReq(n) {
            return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        }

        refreshRequestsBadge();
        setInterval(refreshRequestsBadge, 15000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshRequestsBadge();
        });
    }
})();
