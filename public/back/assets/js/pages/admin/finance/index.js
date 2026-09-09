/**
 * کافی‌نت آنلاین — اسکریپت صفحه «گزارش مالی» (فاز ۸)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * فاز ۹: نمودار روند ۶ ماه + سهم نقش‌ها + خروجی CSV تراکنش‌ها
 */
(function () {
    let currentPage = 1, currentHolder = '', currentType = '', currentQ = '', currentFrom = '', currentTo = '';
    let searchTimer = null;

    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');

    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = skeleton();

        const params = new URLSearchParams({ page, holder: currentHolder, type: currentType, q: currentQ, from: currentFrom, to: currentTo });
        const res = await App.ajax('/admin/finance/data?' + params.toString());
        const data = await res.json();
        render(data);
    }

    function skeleton() {
        let html = '';
        for (let i = 0; i < 4; i++) {
            html += `<tr><td colspan="7" class="!py-4"><div class="space-y-2.5">
                <div class="ui-skeleton h-3 w-full"></div><div class="ui-skeleton h-3 w-40"></div></div></td></tr>`;
        }
        return html;
    }

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="7" class="!py-10"><div class="ui-empty">' +
                '<span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg></span>' +
                '<p class="text-sm font-semibold text-stone-500">تراکنشی یافت نشد.</p></div></td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(t => `
            <tr>
                <td>
                    ${t.type === 'credit'
                        ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">واریز</span>'
                        : '<span class="badge bg-rose-50 text-rose-600 border border-rose-200">برداشت</span>'}
                </td>
                <td><span class="text-xs text-stone-600">${esc(t.holder_label)}</span></td>
                <td class="font-extrabold tabular-nums ${t.type === 'credit' ? 'text-emerald-600' : 'text-rose-500'}">${App.money(t.amount, false)}</td>
                <td class="tabular-nums text-xs text-stone-500">${App.money(t.balance_after, false)}</td>
                <td class="text-xs text-stone-500">${esc(t.ref)}</td>
                <td class="text-xs text-stone-600 max-w-64 truncate" title="${esc(t.description)}">${esc(t.description)}</td>
                <td class="text-xs text-stone-400">${esc(t.date)}</td>
            </tr>
        `).join('');

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${App.digits(data.total)} تراکنش</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${App.digits(data.total)} تراکنش — صفحه ${App.digits(data.current_page)} از ${App.digits(data.last_page)}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    /* ---------- فیلترها ---------- */
    document.getElementById('holder-filter').addEventListener('change', function () { currentHolder = this.value; load(1); });
    document.getElementById('type-filter').addEventListener('change', function () { currentType = this.value; load(1); });
    document.getElementById('q-filter').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentQ = this.value.trim(); load(1); }, 400);
    });
    document.getElementById('from-filter').addEventListener('change', function () { currentFrom = document.getElementById('from-filter-g')?.value || ''; load(1); });
    document.getElementById('to-filter').addEventListener('change', function () { currentTo = document.getElementById('to-filter-g')?.value || ''; load(1); });

    /* ---------- فاز ۹: نمودارها ---------- */
    function renderCharts() {
        if (typeof window.PanelCharts === 'undefined' || !window.PanelCharts.isSupported()) { return; }

        const data = App.pageData() || {};
        const months = data.months || [];
        const shares = data.shares || {};

        if (document.getElementById('fin-months')) {
            PanelCharts.bars('fin-months', {
                labels: months.map(m => m.label_short),
                datasets: [
                    { label: 'پلتفرم', data: months.map(m => m.platform), key: 'amber' },
                    { label: 'سازمان', data: months.map(m => m.organization), key: 'teal' },
                    { label: 'کافی‌نت', data: months.map(m => m.coffeenet), key: 'orange' },
                    { label: 'اپراتور', data: months.map(m => m.operator), key: 'rose' }
                ],
                stacked: true,
                emptyMessage: 'هنوز تسویه‌ای ثبت نشده است'
            });
        }

        if (document.getElementById('fin-shares')) {
            PanelCharts.donut('fin-shares', {
                labels: ['پلتفرم', 'سازمان', 'کافی‌نت', 'اپراتور'],
                values: [shares.platform || 0, shares.organization || 0, shares.coffeenet || 0, shares.operator || 0],
                keys: ['amber', 'teal', 'orange', 'rose'],
                emptyMessage: 'هنوز تسویه‌ای ثبت نشده است'
            });
        }
    }

    /* ---------- فاز ۹: خروجی CSV با فیلترهای فعلی ---------- */
    function syncExportLink() {
        const link = document.getElementById('export-transactions');
        if (!link) { return; }

        const base = App.pageData('exportUrl') || '/admin/finance/export';
        const params = new URLSearchParams({ holder: currentHolder, type: currentType, q: currentQ, from: currentFrom, to: currentTo });
        link.setAttribute('href', App.url(base + '?' + params.toString()));
    }

    /* ---------- ابزارها ---------- */
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    /* ---------- بارگذاری اولیه ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load(1);
        renderCharts();
        syncExportLink();
    }

    // فیلترها به لینک خروجی هم اعمال شوند
    ['holder', 'type', 'q', 'from', 'to'].forEach(k => {
        const el = document.getElementById(k + '-filter');
        if (el) { el.addEventListener('change', syncExportLink); }
    });

    if (typeof window.App !== 'undefined' && typeof window.PanelCharts !== 'undefined') boot();
    else { window.addEventListener('app:ready', () => setTimeout(boot, 60), { once: true }); setTimeout(boot, 2500); }
})();
