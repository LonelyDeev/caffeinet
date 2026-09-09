/**
 * کافی‌نت آنلاین — اسکریپت صفحه «سفارش‌ها» پنل اپراتور (فاز ۷)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 *
 * جدول AJAX با فیلتر وضعیت + جستجو + صفحه‌بندی سروری + دکمهٔ گفتگو.
 * محدودهٔ دید (همهٔ کافی‌نت / فقط خودش) در سرور اعمال می‌شود.
 */
(function () {
    const PAGE = App.pageData();
    const BASE = PAGE.base || '/operator/orders';
    const CAN_ALL = !!PAGE.canAll;
    const COLSPAN = CAN_ALL ? 9 : 8;

    // وضعیت‌هایی که گفتگو فعال/قابل‌مشاهده است (paid = پرداخت‌شده و منتظر شروع کار)
    const CHATABLE = ['accepted', 'paid', 'in_progress', 'needs_info', 'delivered', 'completed'];

    let currentPage = 1;

    const els = {
        tbody: document.getElementById('orders-tbody'),
        pagination: document.getElementById('orders-pagination'),
        summary: document.getElementById('orders-summary'),
        search: document.getElementById('orders-search'),
        status: document.getElementById('orders-status'),
    };

    /* ================== بارگذاری لیست ================== */
    /* ردیف‌های اسکلتون شیمر هنگام بارگذاری */
    const SKELETON_ROWS = 5;
    const skeletonHtml = () => Array.from({ length: SKELETON_ROWS }).map(() => `
        <tr>
            <td><span class="ui-skeleton block h-3 rounded-full" style="width:110px"></span></td>
            <td><span class="ui-skeleton block h-3 rounded-full" style="width:90px"></span></td>
            <td><span class="ui-skeleton block h-3 rounded-full" style="width:80px"></span></td>
            ${CAN_ALL ? '<td><span class="ui-skeleton block h-3 rounded-full" style="width:70px"></span></td>' : ''}
            <td><span class="ui-skeleton block h-3 rounded-full" style="width:60px"></span></td>
            <td><span class="ui-skeleton block h-4 rounded-full" style="width:72px"></span></td>
            <td><span class="ui-skeleton block h-4 rounded-full" style="width:60px"></span></td>
            <td><span class="ui-skeleton block h-3 rounded-full" style="width:70px"></span></td>
            <td><span class="ui-skeleton block h-6 rounded-lg mx-auto" style="width:36px"></span></td>
        </tr>`).join('');

    async function load(page = 1, silent) {
        currentPage = page;
        if (!silent) {
            els.tbody.innerHTML = skeletonHtml();
        }

        const params = new URLSearchParams();
        if (els.search.value.trim()) params.set('q', els.search.value.trim());
        if (els.status.value) params.set('status', els.status.value);
        params.set('page', page);

        try {
            const res = await App.ajax(`${BASE}/data?${params}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            render(data);
        } catch {
            els.tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="!py-10 text-center text-rose-400 text-xs">خطا در دریافت لیست.</td></tr>`;
        }
    }

    const STATUS_COLORS = {
        amber: 'bg-amber-50 text-amber-700 border border-amber-200',
        sky: 'bg-sky-50 text-sky-700 border border-sky-200',
        blue: 'bg-teal-50 text-teal-700 border border-teal-200',
        orange: 'bg-amber-50 text-amber-700 border border-amber-200',
        teal: 'bg-teal-50 text-teal-700 border border-teal-200',
        emerald: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rose: 'bg-rose-50 text-rose-600 border border-rose-200',
    };

    function render(data) {
        if (!data.data.length) {
            els.tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="!py-10">
                <div class="ui-empty mx-auto max-w-md">
                    <span class="ui-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 13v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M2 9h20"/><path d="M12 2 2 9l10 7 10-7-10-7Z"/></svg>
                    </span>
                    <p class="text-sm font-bold text-stone-600">سفارشی یافت نشد</p>
                    <p class="text-xs text-stone-400 mt-1 leading-6">${CAN_ALL ? 'سفارش‌های این کافی‌نت' : 'سفارش‌های سپرده‌شده به شما'} با فیلتر جاری اینجا نمایش داده می‌شوند.</p>
                </div>
            </td></tr>`;
            els.summary.textContent = '—';
            els.pagination.innerHTML = '';
            return;
        }

        els.tbody.innerHTML = data.data.map(row => `
            <tr class="group">
                <td><span class="text-[11px] font-mono text-stone-500" dir="ltr">${escapeHtml(row.order_number)}</span></td>
                <td class="text-xs font-bold text-stone-700">${escapeHtml(row.service_name)}</td>
                <td class="text-xs text-stone-600">${escapeHtml(row.customer_name)}</td>
                ${CAN_ALL ? `<td class="text-xs text-stone-500">${escapeHtml(row.operator_name || 'تعیین نشده')}</td>` : ''}
                <td class="text-xs font-extrabold text-amber-700 tabular-nums">${App.money(row.total, false)}</td>
                <td><span class="badge ${STATUS_COLORS[row.status.color] || STATUS_COLORS.amber}">${escapeHtml(row.status.label)}</span></td>
                <td>
                    ${row.is_paid
                        ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200" title="پرداخت انجام شده">✓ پرداخت</span>'
                        : '<span class="badge bg-amber-50 text-amber-700 border border-amber-200 animate-pulse" title="در انتظار پرداخت مشتری">⏳ در انتظار پرداخت</span>'}
                </td>
                <td class="text-[11px] text-stone-400">${escapeHtml(row.accepted_at_fa || '—')}</td>
                <td>
                    ${CHATABLE.includes(row.status.value)
                        ? `<a href="${BASE}/${row.id}/chat" class="ui-row-btn" title="گفتگو با مشتری" aria-label="گفتگوی سفارش ${escapeHtml(row.order_number)}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                        </a>`
                        : '<span class="text-[10px] text-stone-300">—</span>'}
                </td>
            </tr>`).join('');

        renderPagination(data);
        els.summary.textContent = `نمایش ${fa(data.from || 0)} تا ${fa(data.to || 0)} از ${fa(data.total)} سفارش`;
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
        els.status.addEventListener('change', () => load(1));

        els.pagination.addEventListener('click', (e) => {
            const btn = e.target.closest('.pg-btn');
            if (btn && !btn.disabled) load(+btn.dataset.page);
        });
    }

    /* ---------- helpers ---------- */
    function escapeHtml(str) {
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
        bindEvents();
        load(1);
    }

    window.addEventListener('app:ready', boot, { once: true });
    setTimeout(boot, 2500);
})();
