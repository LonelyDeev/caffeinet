/**
 * کافی‌نت آنلاین — اسکریپت صفحه «کیف پول کافی‌نت» (فاز ۸)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    const PAGE = App.pageData();
    let currentPage = 1, currentType = '';
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');

    const baseUrl = (PAGE && PAGE.base ? PAGE.base : '/coffeenet/wallet/data');

    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = skeletonRows();

        const res = await App.ajax(baseUrl + '?page=' + page + '&type=' + currentType);
        const data = await res.json();
        render(data);
    }

    function skeletonRows() {
        let html = '';
        for (let i = 0; i < 4; i++) {
            html += `<tr><td colspan="6" class="!py-4"><div class="space-y-2.5">
                <div class="ui-skeleton h-3 w-full"></div><div class="ui-skeleton h-3 w-40"></div></div></td></tr>`;
        }
        return html;
    }

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="6" class="!py-10"><div class="ui-empty">' +
                '<span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg></span>' +
                '<p class="text-sm font-semibold text-stone-500">تراکنشی ثبت نشده است.</p>' +
                '<p class="text-xs text-stone-400 mt-1">با تحویل اولین سفارش پرداخت‌شده، سهم کمیسیون کافی‌نت به این کیف واریز می‌شود.</p>' +
                '</div></td></tr>';
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
                <td class="font-extrabold tabular-nums ${t.type === 'credit' ? 'text-emerald-600' : 'text-rose-500'}">${App.money(t.amount, false)}</td>
                <td class="tabular-nums text-xs text-stone-500">${App.money(t.balance_after, false)}</td>
                <td><span class="text-xs text-stone-500">${esc(t.ref)}</span></td>
                <td class="text-xs text-stone-600 max-w-72 truncate" title="${esc(t.description)}">${esc(t.description)}</td>
                <td class="text-xs text-stone-400">${esc(t.date)}</td>
            </tr>
        `).join('');

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

    document.getElementById('type-filter').addEventListener('change', function () {
        currentType = this.value;
        load(1);
    });

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    /* ---------- بارگذاری اولیه ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load(1);
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
