/**
 * کافی‌نت آنلاین — زنگ اعلان پنل‌ها (فاز ۱۰)
 * فایل مستقل — بدون Node / بدون بیلد (لینک مستقیم در لایه‌های پنل)
 *
 * مارک‌آپ مورد انتظار (partial notif-bell):
 *   <div class="notif-wrap">
 *     <button class="notif-bell" data-nb-toggle aria-expanded="false" aria-label="اعلان‌ها">
 *       …svg… <span class="nb-count"></span> <span class="nb-pulse"></span>
 *     </button>
 *     <div class="notif-panel" data-nb-panel role="region" aria-label="فهرست اعلان‌ها">…</div>
 *   </div>
 *
 * اندپوینت‌ها از data-* روی body خوانده می‌شوند:
 *   data-nb-badge / data-nb-data / data-nb-read
 */
(function () {
    'use strict';

    var POLL_MS = 30000;
    var body = document.body;
    var badgeUrl = body.getAttribute('data-nb-badge');
    var dataUrl = body.getAttribute('data-nb-data');
    var readUrl = body.getAttribute('data-nb-read');
    if (!badgeUrl || !dataUrl || !readUrl) { return; }

    var wrap = document.querySelector('.notif-wrap');
    if (!wrap) { return; }

    var btn = wrap.querySelector('[data-nb-toggle]');
    var panel = wrap.querySelector('[data-nb-panel]');
    var countEl = wrap.querySelector('.nb-count');
    var listEl = wrap.querySelector('.nb-list');
    var totalEl = wrap.querySelector('.nb-total');
    var markAllBtn = wrap.querySelector('.nb-markall');
    var isOpen = false;
    var loading = false;

    var ICONS = {
        order: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>',
        ticket: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 13a9 9 0 0 1 18 0"/><path d="M21 17v2a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Zm-18 0v2a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3Z"/></svg>',
        withdrawal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg>',
        settlement: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>',
        system: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="11" x2="12" y2="16.5"/><circle cx="12" cy="7.6" r="0.5" fill="currentColor" stroke="none"/></svg>'
    };

    function faDigits(n) {
        return String(n || 0).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function ajax(url, opts) {
        opts = opts || {};
        return window.App.ajax(url, opts);
    }

    /* ---------- بج ---------- */
    function setBadge(count) {
        count = Math.max(0, count | 0);
        btn.classList.toggle('nb-has-unread', count > 0);

        if (count > 0) {
            countEl.textContent = count > 99 ? '۹۹+' : faDigits(count);
            countEl.classList.add('nb-on');
        } else {
            countEl.textContent = '';
            countEl.classList.remove('nb-on');
        }
    }

    function pollBadge() {
        ajax(badgeUrl).then(function (res) {
            if (!res.ok) { return; }
            res.json().then(function (d) { setBadge(d.count); }).catch(function () {});
        }).catch(function () {});
    }

    /* ---------- لیست ---------- */
    function itemHtml(n) {
        var icon = ICONS[n.type] || ICONS.system;
        var time = [n.date_fa, n.time_fa].filter(Boolean).join(' — ');
        var url = n.url ? ' data-url="' + esc(n.url) + '"' : '';

        return '<button type="button" class="nb-item ' + (n.read ? '' : 'nb-unread') + '" data-id="' + esc(n.id) + '"' + url + '>' +
            '  <span class="nb-ico" data-type="' + esc(n.type) + '">' + icon + '</span>' +
            '  <span class="nb-body">' +
            '    <span class="nb-title"><span class="nb-dot"></span>' + esc(n.title) + '</span>' +
            '    <span class="nb-text">' + esc(n.body) + '</span>' +
            '    <span class="nb-time">' + esc(time) + '</span>' +
            '  </span>' +
            '</button>';
    }

    function renderList(rows, unreadCount) {
        if (!rows.length) {
            listEl.innerHTML =
                '<div class="nb-empty">' +
                '  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>' +
                '  اعلان جدیدی ندارید.' +
                '</div>';
        } else {
            listEl.innerHTML = rows.map(itemHtml).join('');
        }

        if (totalEl) { totalEl.textContent = faDigits(unreadCount) + ' خوانده‌نشده'; }
        if (markAllBtn) { markAllBtn.disabled = unreadCount === 0; }

        listEl.querySelectorAll('.nb-item').forEach(function (item) {
            item.addEventListener('click', function () {
                handleItemClick(item);
            });
        });
    }

    function loadList() {
        if (loading) { return; }
        loading = true;
        listEl.innerHTML = '<div class="nb-loading"><div class="nb-spin"></div>در حال بارگذاری…</div>';

        ajax(dataUrl).then(function (res) {
            loading = false;
            if (!res.ok) {
                listEl.innerHTML = '<div class="nb-empty">خطا در دریافت اعلان‌ها.</div>';
                return;
            }
            res.json().then(function (d) {
                renderList(d.data || [], d.count || 0);
                setBadge(d.count || 0);
            }).catch(function () {});
        }).catch(function () {
            loading = false;
            listEl.innerHTML = '<div class="nb-empty">خطا در دریافت اعلان‌ها.</div>';
        });
    }

    /* ---------- کلیک روی آیتم ---------- */
    function handleItemClick(item) {
        var id = item.getAttribute('data-id');
        var url = item.getAttribute('data-url');

        ajax(readUrl, { method: 'POST', body: { id: id } }).catch(function () {});

        if (url) {
            window.location.href = window.App.url(url);
        } else {
            item.classList.remove('nb-unread');
            pollBadge();
        }
    }

    /* ---------- باز/بسته ---------- */
    function open() {
        isOpen = true;
        panel.classList.add('nb-open');
        btn.setAttribute('aria-expanded', 'true');
        loadList();
    }

    function close() {
        isOpen = false;
        panel.classList.remove('nb-open');
        btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (isOpen) { close(); } else { open(); }
    });

    panel.addEventListener('click', function (e) { e.stopPropagation(); });

    document.addEventListener('click', function () { if (isOpen) { close(); } });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) { close(); }
    });

    /* ---------- علامت‌گذاری همه ---------- */
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            markAllBtn.disabled = true;
            ajax(readUrl, { method: 'POST', body: {} }).then(function (res) {
                if (!res.ok) { return; }
                res.json().then(function () {
                    pollBadge();
                    loadList();
                }).catch(function () {});
            }).catch(function () { markAllBtn.disabled = false; });
        });
    }

    /* ---------- راه‌اندازی ---------- */
    pollBadge();
    setInterval(pollBadge, POLL_MS);

    /* ---------- Realtime پوشر (فاز ۱۳) — بیدارباش زنگ ----------
       با رویداد notif.new روی کانال شخصی کاربر، بج بلافاصله تازه
       می‌شود؛ پولینگ دوره‌ای فقط پشتیبان است (در حالت بدون پوشر همان
       رفتار قبلی). */
    if (window.RT && RT.active() && RT.cfg.channel) {
        RT.bindUser('notif.new', function () {
            pollBadge();
            if (isOpen) { loadList(); }
        });
    }
})();
