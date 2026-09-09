/**
 * کافی‌نت آنلاین — اسکریپت صفحه «تسویه‌های کمیسیون» (فاز ۸)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    const PAGE = App.pageData();
    let currentPage = 1, currentRole = String(PAGE.role || ''), currentQ = '', currentFrom = '', currentTo = '';
    let searchTimer = null;

    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const orderModal = document.getElementById('order-modal');
    const orderModalBody = document.getElementById('om-body');

    document.getElementById('role-filter').value = currentRole;

    const roleBadges = {
        platform: 'bg-amber-50 text-amber-700 border border-amber-200',
        organization: 'bg-teal-50 text-teal-700 border border-teal-200',
        coffeenet: 'bg-orange-50 text-orange-700 border border-orange-200',
        operator: 'bg-rose-50 text-rose-600 border border-rose-200',
    };

    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = skeleton();

        const params = new URLSearchParams({ page, role: currentRole, q: currentQ, from: currentFrom, to: currentTo });
        const res = await App.ajax('/admin/settlements/data?' + params.toString());
        const data = await res.json();
        render(data);
    }

    function skeleton() {
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
                '<p class="text-sm font-semibold text-stone-500">تسویه‌ای یافت نشد.</p>' +
                '<p class="text-xs text-stone-400 mt-1">با تحویل اولین سفارش پرداخت‌شده، سهم‌ها اینجا ثبت می‌شوند.</p></div></td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(r => `
            <tr class="cursor-pointer act-order" data-order="${r.order_id}">
                <td>
                    <p class="font-bold text-stone-800 num">${esc(r.order_number)}</p>
                </td>
                <td class="text-xs text-stone-500">${esc(r.service)}</td>
                <td><span class="badge ${roleBadges[r.role] || ''}">${esc(r.role_label)}</span></td>
                <td class="text-xs text-stone-600">${esc(r.holder)}</td>
                <td class="font-extrabold tabular-nums text-amber-600">${App.money(r.amount, false)} <span class="text-[10px] font-normal text-stone-400">تومان</span></td>
                <td class="text-xs text-stone-500">${esc(r.date)}</td>
            </tr>
        `).join('');

        rows.querySelectorAll('.act-order').forEach(tr => tr.addEventListener('click', () => openOrder(tr.dataset.order)));

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${App.digits(data.total)} پرداخت</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${App.digits(data.total)} پرداخت — صفحه ${App.digits(data.current_page)} از ${App.digits(data.last_page)}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    /* ---------- مودال جزئیات سفارش ---------- */

    async function openOrder(orderId) {
        orderModalBody.innerHTML = '<div class="ui-skeleton h-4 w-full"></div><div class="ui-skeleton h-4 w-3/4"></div>';
        orderModal.classList.remove('hidden');
        orderModal.classList.add('flex');

        const res = await App.ajax('/admin/settlements/order/' + orderId);
        const payload = await res.json();
        const d = payload.data || {};
        renderOrderModal(d);
    }

    function renderOrderModal(d) {
        const o = d.order || {};

        if (!d.settled) {
            orderModalBody.innerHTML = `
                <div class="ui-empty">
                    <span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg></span>
                    <p class="text-sm font-semibold text-stone-500">${esc(payload.message || 'تسویه نشده')}</p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs rounded-2xl bg-stone-50 border border-stone-100 p-4">
                    <div><span class="text-stone-400">سفارش</span><p class="font-bold text-stone-700 mt-1 num">${esc(o.order_number || '—')}</p></div>
                    <div><span class="text-stone-400">مبلغ مشمول</span><p class="font-bold text-stone-700 mt-1">${App.money(o.commissionable || 0)}</p></div>
                </div>`;
            return;
        }

        const payoutRows = (d.payouts || []).map(p => `
            <div class="flex items-center justify-between gap-3 rounded-xl border border-stone-100 px-4 py-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="badge ${roleBadges[p.role] || ''} shrink-0">${esc(p.role_label)}</span>
                    <span class="text-xs text-stone-600 truncate">${esc(p.holder)}</span>
                </div>
                <strong class="tabular-nums text-sm text-stone-800 shrink-0">${App.money(p.amount, false)}</strong>
            </div>
        `).join('');

        const snap = d.snapshot || {};
        const salary = snap.salary ? `<div class="flex justify-between"><span class="text-stone-400">مدل حقوق اپراتور</span><span class="text-stone-600">${esc(snap.salary)}</span></div>` : '';

        orderModalBody.innerHTML = `
            <div class="rounded-2xl bg-gradient-to-l from-amber-50 to-white border border-amber-100 p-4 space-y-1.5 text-xs">
                <div class="flex justify-between"><span class="text-stone-400">سفارش</span><strong class="text-stone-800 num">${esc(o.order_number || '—')}</strong></div>
                <div class="flex justify-between"><span class="text-stone-400">خدمت</span><span class="text-stone-600">${esc(o.service || '—')}</span></div>
                ${o.coffeenet ? `<div class="flex justify-between"><span class="text-stone-400">کافی‌نت</span><span class="text-stone-600">${esc(o.coffeenet)}</span></div>` : ''}
                ${o.operator ? `<div class="flex justify-between"><span class="text-stone-400">اپراتور</span><span class="text-stone-600">${esc(o.operator)}</span></div>` : ''}
                <div class="flex justify-between"><span class="text-stone-400">مبلغ کل سفارش</span><span class="text-stone-600">${App.money(o.order_total || 0)}</span></div>
                <div class="flex justify-between"><span class="text-stone-400">مبلغ مشمول کمیسیون</span><strong class="text-amber-600">${App.money(o.commissionable || 0)}</strong></div>
                ${d.settled_at ? `<div class="flex justify-between"><span class="text-stone-400">زمان تسویه</span><span class="text-stone-600 num" dir="ltr">${esc(d.settled_at)}</span></div>` : ''}
            </div>

            <div class="space-y-2">${payoutRows}</div>

            ${snap.rule ? `<p class="text-[11px] text-stone-400 leading-5 rounded-xl bg-stone-50 border border-stone-100 px-3 py-2">${esc(snap.rule)}</p>` : ''}
            ${salary}
        `;
    }

    orderModal.querySelectorAll('[data-close-modal]').forEach(el => el.addEventListener('click', () => {
        orderModal.classList.add('hidden');
        orderModal.classList.remove('flex');
    }));

    /* ---------- فیلترها ---------- */

    document.getElementById('role-filter').addEventListener('change', function () {
        currentRole = this.value;
        load(1);
    });

    document.getElementById('q-filter').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentQ = this.value.trim(); load(1); }, 400);
    });

    document.getElementById('from-filter').addEventListener('change', function () {
        currentFrom = document.getElementById('from-filter-g')?.value || ''; load(1);
    });
    document.getElementById('to-filter').addEventListener('change', function () {
        currentTo = document.getElementById('to-filter-g')?.value || ''; load(1);
    });

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
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
