/**
 * کافی‌نت آنلاین — اسکریپت صفحه «تیکت‌های پشتیبانی» (ادمین)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    const PAGE = App.pageData();
    let currentPage = 1;
    let currentStatus = String(PAGE.status || '');
    let currentPriority = String(PAGE.priority || '');
    let searchQ = '';
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');

    const statusLabels = { open: 'باز', answered: 'پاسخ داده‌شده', customer_reply: 'پاسخ مشتری', closed: 'بسته‌شده' };
    const prioLabels = { low: 'کم', normal: 'معمولی', high: 'زیاد' };

    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = '<tr><td colspan="8" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        const params = new URLSearchParams({ page, status: currentStatus, priority: currentPriority, q: searchQ });
        const res = await App.ajax('/admin/tickets/data?' + params.toString());
        const data = await res.json();
        render(data);
    }

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="8" class="text-center py-10 text-stone-400 text-xs">تیکتی یافت نشد.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(r => `
            <tr>
                <td>
                    <a href="${App.url('/admin/tickets/' + r.id)}" class="block group">
                        <p class="font-bold text-stone-800 group-hover:text-amber-600 transition-colors">${esc(r.subject)}</p>
                        <p class="text-[10px] font-mono text-stone-400 mt-0.5" dir="ltr">${esc(r.ticket_number)}</p>
                    </a>
                </td>
                <td>
                    <p class="font-semibold text-stone-700 text-xs">${esc(r.customer)}</p>
                    ${r.customer_mobile ? `<p class="text-[10px] font-mono text-stone-400" dir="ltr">${esc(r.customer_mobile)}</p>` : ''}
                </td>
                <td>
                    ${r.order_number
                        ? `<span class="badge bg-stone-50 text-stone-500 border border-stone-200 font-mono text-[10px]" dir="ltr">${esc(r.order_number)}</span>`
                        : '<span class="text-[11px] text-stone-300">عمومی</span>'}
                </td>
                <td><span class="tk-badge tk-status-${r.status}">${statusLabels[r.status] || r.status}</span></td>
                <td><span class="tk-badge tk-prio-${r.priority}">${prioLabels[r.priority] || r.priority}</span></td>
                <td>${r.assigned_name
                    ? `<span class="text-xs font-semibold text-stone-600">${esc(r.assigned_name)}</span>`
                    : '<span class="text-[11px] text-stone-300">تعیین‌نشده</span>'}</td>
                <td class="text-stone-500 text-xs">
                    ${r.messages_count ? `<span class="badge bg-amber-50 text-amber-700 border border-amber-200 mb-1">${App.digits(r.messages_count)} پیام</span>` : ''}
                    <div>${r.last_message_at || r.created_at}</div>
                </td>
                <td class="text-center">
                    <a class="btn-ghost !py-2 !px-4 !text-xs ui-press" href="${App.url('/admin/tickets/' + r.id)}">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-9-9"/><path d="M21 3v9h-9"/></svg>
                        گفتگو
                    </a>
                    <button type="button" class="act-trash btn-ghost !py-2 !px-4 !text-xs ui-press !text-rose-600 hover:!bg-rose-50" data-trash="${r.id}" data-trash-label="${r.ticket_number || ('#' + r.id)}" title="حذف تیکت (به حذف‌شده‌ها)">حذف</button>
                </td>
            </tr>
        `).join('');

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${App.digits(data.total)} تیکت</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${App.digits(data.total)} تیکت — صفحه ${App.digits(data.current_page)} از ${App.digits(data.last_page)}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    /* ---------- فیلترها ---------- */
    document.getElementById('status-filter').addEventListener('change', function () {
        currentStatus = this.value;
        load(1);
    });

    document.getElementById('priority-filter').addEventListener('change', function () {
        currentPriority = this.value;
        load(1);
    });

    let searchTimer = null;
    document.getElementById('search-input').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { searchQ = this.value.trim(); load(1); }, 400);
    });

    /* چیپ‌های آماری → فیلتر سریع */
    document.querySelectorAll('[data-stat-chip]').forEach(chip => {
        chip.addEventListener('click', () => {
            const key = chip.dataset.statChip;
            if (key === 'high-open') {
                // اولویت زیاد + بدون بسته
                currentPriority = 'high';
                document.getElementById('priority-filter').value = 'high';
                currentStatus = '';
                document.getElementById('status-filter').value = '';
            } else {
                currentStatus = key;
                document.getElementById('status-filter').value = key;
            }
            load(1);
        });
    });

    /* ---------- بارگذاری اولیه ---------- */
    document.getElementById('status-filter').value = currentStatus;
    document.getElementById('priority-filter').value = currentPriority;

    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load(1);
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();


/* v28 — حذف نرم/دائم این بخش (trash.js) */
document.addEventListener('DOMContentLoaded', function () {
    if (window.AdminTrash) {
        window.AdminTrash.mount({ section: 'tickets' });
    }
});
