/**
 * کافی‌نت آنلاین — اسکریپت صفحه «درآمد و کیف پول اپراتور» (فاز ۸)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    let currentPage = 1;
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');

    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = skeleton();

        const res = await App.ajax('/operator/earnings/data?page=' + page);
        const data = await res.json();
        render(data);
    }

    function skeleton() {
        let html = '';
        for (let i = 0; i < 4; i++) {
            html += `<tr><td colspan="5" class="!py-4"><div class="space-y-2.5">
                <div class="ui-skeleton h-3 w-full"></div><div class="ui-skeleton h-3 w-40"></div></div></td></tr>`;
        }
        return html;
    }

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="5" class="!py-10"><div class="ui-empty">' +
                '<span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v10"/><path d="m8 6.5 4-4 4 4"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg></span>' +
                '<p class="text-sm font-semibold text-stone-500">هنوز تسویه‌ای ثبت نشده است.</p>' +
                '<p class="text-xs text-stone-400 mt-1">با تحویل اولین سفارشِ پرداخت‌شده، سهم شما به کیف پول واریز و اینجا نمایش داده می‌شود.</p>' +
                '</div></td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(p => `
            <tr>
                <td class="font-bold text-stone-800 num">${esc(p.order_number)}</td>
                <td class="text-xs text-stone-500">${esc(p.service)}</td>
                <td class="text-xs text-stone-500">${esc(p.coffeenet)}</td>
                <td class="font-extrabold tabular-nums text-emerald-600">${App.money(p.amount, false)} <span class="text-[10px] font-normal text-stone-400">تومان</span></td>
                <td class="text-xs text-stone-400">${esc(p.date)}</td>
            </tr>
        `).join('');

        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${App.digits(data.total)} تسویه</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${App.digits(data.total)} تسویه — صفحه ${App.digits(data.current_page)} از ${App.digits(data.last_page)}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    /* ---------- لاگ تراکنش‌های کیف پول (درخواست بازخوردی) ---------- */
    let txPage = 1;
    let txType = '';
    const txRows = document.getElementById('tx-rows');
    const txPagination = document.getElementById('tx-pagination');
    const txFilterBtns = document.querySelectorAll('.tx-filter-btn');

    async function loadTx(page) {
        page = page || txPage;
        txRows.innerHTML = txSkeleton();

        try {
            const res = await App.ajax('/operator/earnings/transactions?page=' + page + '&type=' + txType);
            const data = await res.json();
            renderTx(data);
        } catch {
            txRows.innerHTML = '<tr><td colspan="6" class="!py-10 text-center text-rose-400 text-xs">خطا در دریافت تراکنش‌ها.</td></tr>';
        }
    }

    function txSkeleton() {
        let html = '';
        for (let i = 0; i < 4; i++) {
            html += `<tr><td colspan="6" class="!py-4"><div class="space-y-2.5">
                <div class="ui-skeleton h-3 w-full"></div><div class="ui-skeleton h-3 w-40"></div></div></td></tr>`;
        }
        return html;
    }

    function renderTx(data) {
        txPage = data.current_page;

        if (!data.data.length) {
            txRows.innerHTML = '<tr><td colspan="6" class="!py-10"><div class="ui-empty">' +
                '<span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/></svg></span>' +
                '<p class="text-sm font-semibold text-stone-500">تراکنشی ثبت نشده است.</p>' +
                '<p class="text-xs text-stone-400 mt-1">واریز سهم سفارش‌ها و برداشت‌های شما با جزئیات در این جدول نمایش داده می‌شود.</p>' +
                '</div></td></tr>';
            txPagination.innerHTML = '';
            return;
        }

        txRows.innerHTML = data.data.map(t => `
            <tr>
                <td>
                    ${t.type === 'credit'
                        ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">واریز</span>'
                        : '<span class="badge bg-rose-50 text-rose-600 border border-rose-200">برداشت</span>'}
                </td>
                <td class="font-extrabold tabular-nums ${t.type === 'credit' ? 'text-emerald-600' : 'text-rose-500'}">
                    ${t.type === 'credit' ? '+' : '−'} ${App.money(t.amount, false)} <span class="text-[10px] font-normal text-stone-400">تومان</span>
                </td>
                <td class="text-xs text-stone-600 tabular-nums">${App.money(t.balance_after, false)}</td>
                <td class="text-xs text-stone-500">${esc(t.ref)}</td>
                <td class="text-xs text-stone-400 max-w-64 truncate" title="${esc(t.description)}">${esc(t.description)}</td>
                <td class="text-xs text-stone-400 whitespace-nowrap">${esc(t.date)}</td>
            </tr>
        `).join('');

        if (data.last_page <= 1) {
            txPagination.innerHTML = `<span>${App.digits(data.total)} تراکنش</span>`;
            return;
        }
        txPagination.innerHTML = `
            <span>${App.digits(data.total)} تراکنش — صفحه ${App.digits(data.current_page)} از ${App.digits(data.last_page)}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        txPagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => loadTx(+b.dataset.page)));
    }

    txFilterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            txType = btn.dataset.type || '';
            txFilterBtns.forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
            loadTx(1);
        });
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
        loadTx(1);
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
