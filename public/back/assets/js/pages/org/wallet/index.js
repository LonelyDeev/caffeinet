/**
 * کافی‌نت آنلاین — اسکریپت صفحه «کیف پول»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    let currentPage = 1, currentType = '';
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');

    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = skeletonRows(5);

        const res = await App.ajax('/organization/wallet/data?page=' + page + '&type=' + currentType);
        const data = await res.json();
        render(data);
    }

    /* اسکلتون بارگذاری */
    function skeletonRows(cols) {
        let html = '';
        for (let i = 0; i < 4; i++) {
            html += `<tr><td colspan="${cols}" class="!py-4">
                <div class="space-y-2.5">
                    <div class="ui-skeleton h-3 w-full"></div>
                    <div class="ui-skeleton h-3 w-40"></div>
                    <div class="ui-skeleton h-3 w-32"></div>
                </div>
            </td></tr>`;
        }
        return html;
    }

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="5" class="!py-10"><div class="ui-empty">' +
                '<span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg></span>' +
                '<p class="text-sm font-semibold text-stone-500">تراکنشی ثبت نشده است.</p>' +
                '<p class="text-xs text-stone-400 mt-1">گردش کیف پول پس از اولین واریز پاداش اینجا نمایش داده می‌شود.</p>' +
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
                <td class="font-extrabold tabular-nums ${t.type === 'credit' ? 'text-emerald-600' : 'text-rose-500'}">
                    ${t.type === 'credit' ? '+' : '−'} ${App.money(t.amount, false)} <span class="text-[10px] font-normal text-stone-400">تومان</span>
                </td>
                <td class="tabular-nums text-stone-600">${App.money(t.balance_after, false)}</td>
                <td class="max-w-64 truncate text-stone-500" title="${(t.description || '').replace(/"/g, '&quot;')}">
                    ${t.description !== '—' ? t.description : ''}
                    <span class="badge bg-stone-100 text-stone-500 border border-stone-200 ms-1">${t.ref}</span>
                </td>
                <td class="text-stone-400 text-xs">${t.date}</td>
            </tr>
        `).join('');

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${data.total.toLocaleString('fa-IR')} تراکنش</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${data.total.toLocaleString('fa-IR')} تراکنش — صفحه ${data.current_page.toLocaleString('fa-IR')} از ${data.last_page.toLocaleString('fa-IR')}</span>
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

    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load(1);
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
