/**
 * کافی‌نت آنلاین — اسکریپت صفحه «برداشت‌های کافی‌نت» (فاز ۸)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    const PAGE = App.pageData();
    const BASE = (PAGE && PAGE.base) || '/coffeenet/withdrawals';

    let currentPage = 1, currentStatus = '';
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const form = document.getElementById('withdraw-form');

    const statusBadges = {
        amber: 'bg-amber-50 text-amber-700 border border-amber-200',
        sky: 'bg-sky-50 text-sky-700 border border-sky-200',
        emerald: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rose: 'bg-rose-50 text-rose-600 border border-rose-200',
        stone: 'bg-stone-100 text-stone-500 border border-stone-200',
    };

    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = skeletonRows(4);

        const res = await App.ajax(BASE + '/data?page=' + page + '&status=' + currentStatus);
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
            rows.innerHTML = '<tr><td colspan="4" class="!py-10"><div class="ui-empty">' +
                '<span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg></span>' +
                '<p class="text-sm font-semibold text-stone-500">برداشتی ثبت نشده است.</p>' +
                '<p class="text-xs text-stone-400 mt-1">درآمد کمیسیون سفارش‌ها ابتدا به کیف پول کافی‌نت واریز می‌شود؛ سپس از همین صفحه قابل برداشت است.</p>' +
                '</div></td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(w => `
            <tr>
                <td class="font-extrabold tabular-nums text-amber-600">${App.money(w.amount, false)} <span class="text-[10px] font-normal text-stone-400">تومان</span></td>
                <td>
                    <span class="badge ${statusBadges[w.status_color]}">${w.status_label}</span>
                    ${w.reviewer ? `<p class="text-[10px] text-stone-400 mt-1">${w.reviewer}${w.reviewed_at ? ' · ' + w.reviewed_at : ''}</p>` : ''}
                </td>
                <td class="text-stone-500 text-xs">${w.requested_at}</td>
                <td class="max-w-56 truncate text-stone-400 text-xs" title="${(w.note || '').replace(/"/g, '&quot;')}">${w.note || '—'}</td>
            </tr>
        `).join('');

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${App.digits(data.total)} درخواست</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${App.digits(data.total)} درخواست — صفحه ${App.digits(data.current_page)} از ${App.digits(data.last_page)}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    /* ---------- فرم درخواست ---------- */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errEl = form.querySelector('.err[data-for="amount"]');
        errEl.classList.add('hidden');
        document.getElementById('w-amount').classList.remove('field-error');

        const amount = document.getElementById('w-amount').value;
        const note = document.getElementById('w-note').value.trim();

        if (!amount || +amount < 1000) {
            errEl.textContent = 'حداقل مبلغ برداشت ۱٬۰۰۰ تومان است.';
            errEl.classList.remove('hidden');
            document.getElementById('w-amount').classList.add('field-error');
            return;
        }

        const btn = document.getElementById('w-submit');
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال ثبت...';

        try {
            const res = await App.ajax(BASE, {
                method: 'POST',
                body: { amount: +amount, note: note || null },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'درخواست ثبت شد.', 'success');
                form.reset();
                setTimeout(() => window.location.reload(), 1400);
            } else if (res.status === 422 && data.errors) {
                errEl.textContent = data.errors.amount?.[0] || data.message;
                errEl.classList.remove('hidden');
                document.getElementById('w-amount').classList.add('field-error');
            } else {
                App.toast(data.message || 'خطا در ثبت درخواست.', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    document.getElementById('status-filter').addEventListener('change', function () {
        currentStatus = this.value;
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
