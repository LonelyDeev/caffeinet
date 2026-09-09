/**
 * کافی‌نت آنلاین — اسکریپت صفحه «گفتگوها» پنل اپراتور (فاز ۷)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 *
 * فهرست گفتگوها با فیلتر + جستجو + صفحه‌بندی + پولینگ بی‌صدا ۶ ثانیه.
 */
(function () {
    const PAGE = App.pageData();
    const BASE = PAGE.base || '/operator/chat';
    const SHOW_PATTERN = PAGE.showPattern || '/operator/orders/{id}/chat';
    const POLL_MS = 6000;

    const STATUS_COLORS = {
        amber: 'bg-amber-50 text-amber-700 border border-amber-200',
        sky: 'bg-sky-50 text-sky-700 border border-sky-200',
        blue: 'bg-teal-50 text-teal-700 border border-teal-200',
        orange: 'bg-amber-50 text-amber-700 border border-amber-200',
        teal: 'bg-teal-50 text-teal-700 border border-teal-200',
        emerald: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rose: 'bg-rose-50 text-rose-600 border border-rose-200',
    };

    const ACTIVE_CLASS = 'pg-active';

    let currentPage = 1;
    let currentFilter = 'active';
    let polling = null;

    const els = {
        list: document.getElementById('chat-list'),
        pagination: document.getElementById('chat-pagination'),
        summary: document.getElementById('chat-summary'),
        search: document.getElementById('chat-search'),
        filters: document.getElementById('chat-filters'),
    };

    /* ================== بارگذاری ================== */

    async function load(page = 1, silent) {
        currentPage = page;

        if (!silent) {
            els.list.innerHTML = `<div class="px-6 py-12 text-center text-stone-400 text-xs">در حال بارگذاری…</div>`;
        }

        const params = new URLSearchParams();
        if (els.search.value.trim()) params.set('q', els.search.value.trim());
        if (currentFilter) params.set('filter', currentFilter);
        params.set('page', page);

        try {
            const res = await App.ajax(`${BASE}/data?${params}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            render(data);
        } catch {
            if (!silent) {
                els.list.innerHTML = `<div class="px-6 py-12 text-center text-rose-400 text-xs">خطا در دریافت فهرست گفتگوها.</div>`;
            }
        }
    }

    /* ================== رندر ================== */

    function render(data) {
        // رویداد بج سایدبار
        window.dispatchEvent(new CustomEvent('chat:unseen', { detail: data.unseen_total || 0 }));

        if (!data.data.length) {
            els.list.innerHTML = `
                <div class="px-6 py-8">
                    <div class="ui-empty mx-auto max-w-md">
                        <span class="ui-empty-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                        </span>
                        <p class="text-sm font-bold text-stone-600">گفتگویی یافت نشد</p>
                        <p class="text-xs text-stone-400 mt-1 leading-6">گفتگوی سفارش‌های پذیرفته‌شدهٔ کافی‌نت اینجا نمایش داده می‌شوند.</p>
                    </div>
                </div>`;
            els.summary.textContent = '—';
            els.pagination.innerHTML = '';
            return;
        }

        els.list.innerHTML = data.data.map(row => rowHtml(row)).join('');

        renderPagination(data);
        els.summary.textContent = `نمایش ${fa(data.from || 0)} تا ${fa(data.to || 0)} از ${fa(data.total)} گفتگو`;
    }

    function rowHtml(row) {
        const initial = (row.customer_name || 'م').trim().charAt(0);

        return `
        <a href="${SHOW_PATTERN.replace('{id}', row.id)}" class="chat-row flex items-center gap-3 px-4 sm:px-5 py-4">
            <span class="grid place-items-center size-11 rounded-2xl bg-gradient-to-br from-amber-400/80 to-amber-700/80 text-amber-50 font-bold text-sm shrink-0" aria-hidden="true">${esc(initial)}</span>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <strong class="text-xs font-bold text-stone-700 truncate">${esc(row.customer_name)}</strong>
                    <span class="text-[10px] font-mono text-stone-400 truncate" dir="ltr">${esc(row.order_number)}</span>
                </div>
                <p class="text-[11px] text-stone-500 mt-1 truncate ${row.last_type === 'system' ? 'text-stone-400' : ''}">
                    ${row.unseen > 0 ? '<strong class="text-stone-700">' : ''}${esc(row.last_preview || '—')}${row.unseen > 0 ? '</strong>' : ''}
                </p>
            </div>

            <div class="flex flex-col items-end gap-1.5 shrink-0">
                <span class="text-[10px] text-stone-400 font-mono" dir="ltr">${esc(row.last_time_fa || '—')}</span>
                <div class="flex items-center gap-1.5">
                    ${row.unseen > 0 ? `<span class="cchat-badge on" aria-label="${fa(row.unseen)} پیام خوانده‌نشده">${fa(row.unseen)}</span>` : ''}
                    <span class="badge ${STATUS_COLORS[row.status.color] || STATUS_COLORS.amber}">${esc(row.status.label)}</span>
                </div>
            </div>
        </a>`;
    }

    function renderPagination(data) {
        const pages = data.last_page || 1;
        if (pages <= 1) { els.pagination.innerHTML = ''; return; }

        let html = `<button type="button" class="pg-btn" data-page="${Math.max(1, data.current_page - 1)}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>`;

        for (let p = 1; p <= pages; p++) {
            if (pages > 7 && Math.abs(p - data.current_page) > 2 && p !== 1 && p !== pages) {
                if (p === 2 || p === pages - 1) html += '<span class="text-stone-300 text-xs px-1">…</span>';
                continue;
            }
            html += `<button type="button" class="pg-btn ${p === data.current_page ? 'pg-active' : ''}" data-page="${p}">${fa(p)}</button>`;
        }

        html += `<button type="button" class="pg-btn" data-page="${Math.min(pages, data.current_page + 1)}" ${data.current_page >= pages ? 'disabled' : ''}>بعدی</button>`;
        els.pagination.innerHTML = html;
    }

    /* ================== رویدادها ================== */

    function bindEvents() {
        let searchDebounce;
        els.search.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => load(1), 400);
        });

        els.filters.addEventListener('click', (e) => {
            const btn = e.target.closest('.chat-filter-btn');
            if (!btn) return;

            currentFilter = btn.dataset.filter;
            els.filters.querySelectorAll('.chat-filter-btn').forEach((b) => {
                const active = b === btn;
                b.classList.toggle('rounded-lg', active);
                if (active) b.classList.add(...ACTIVE_CLASS.split(' '));
                else b.classList.remove(...ACTIVE_CLASS.split(' '));
                b.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            load(1);
        });

        els.pagination.addEventListener('click', (e) => {
            const btn = e.target.closest('.pg-btn');
            if (btn && !btn.disabled) load(+btn.dataset.page);
        });

        // پولینگ بی‌صدا
        polling = setInterval(() => {
            if (!document.hidden) load(currentPage, true);
        }, POLL_MS);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) load(currentPage, true);
        });
    }

    /* ---------- helpers ---------- */
    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[c]);
    }
    function fa(n) { return String(n ?? 0).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

    /* ---------- boot ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;

        // حالت فعال اولیهٔ دکمه فیلتر
        const first = els.filters.querySelector('[data-filter="active"]');
        if (first) {
            first.classList.add('rounded-lg', ...ACTIVE_CLASS.split(' '));
        }

        bindEvents();
        load(1);
    }

    /* رویداد app:ready گاهی قبل از ثبت این شنونده fire می‌شود — گارد بلافاصله boot می‌کند. */
    if (typeof window.App !== 'undefined') {
        boot();
    } else {
        window.addEventListener('app:ready', boot, { once: true });
        document.addEventListener('DOMContentLoaded', boot, { once: true });
        setTimeout(boot, 2500);
    }
})();
