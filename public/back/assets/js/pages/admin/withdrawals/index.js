/**
 * کافی‌نت آنلاین — اسکریپت صفحه «برداشت‌ها»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    const PAGE = App.pageData();
    let currentPage = 1, currentStatus = String(PAGE.status || ''), reviewTarget = null;
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const reviewModal = document.getElementById('review-modal');

    document.getElementById('status-filter').value = currentStatus;

    const statusBadges = {
        pending: 'bg-amber-50 text-amber-700 border border-amber-200',
        approved: 'bg-sky-50 text-sky-700 border border-sky-200',
        paid: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rejected: 'bg-rose-50 text-rose-600 border border-rose-200',
    };
    const statusLabels = {
        pending: 'در انتظار بررسی', approved: 'تأییدشده', paid: 'پرداخت‌شده', rejected: 'ردشده',
    };

    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = '<tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        const res = await App.ajax('/admin/withdrawals/data?page=' + page + '&status=' + currentStatus);
        const data = await res.json();
        render(data);
    }

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">درخواستی یافت نشد.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(r => `
            <tr>
                <td>
                    <p class="font-bold text-stone-800">${r.holder}</p>
                    <p class="text-[11px] text-stone-400">${r.holder_type} · ${r.requester}</p>
                </td>
                <td class="font-extrabold tabular-nums text-amber-600">${App.money(r.amount, false)} <span class="text-[10px] font-normal text-stone-400">تومان</span></td>
                <td class="tabular-nums text-stone-600">${App.money(r.wallet_balance, false)} <span class="text-[10px] font-normal text-stone-400">تومان</span></td>
                <td>
                    <span class="badge ${statusBadges[r.status] || ''}">${statusLabels[r.status] || r.status}</span>
                    ${r.reviewer ? `<p class="text-[10px] text-stone-400 mt-1">${r.reviewer} · ${r.reviewed_at || ''}</p>` : ''}
                </td>
                <td class="text-stone-500">${r.requested_at}</td>
                <td class="text-center">
                    ${r.status === 'pending' ? `
                    <button class="act-review btn-ghost !py-2 !px-4 !text-xs" data-id="${r.id}">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-9-9"/><path d="M21 3v9h-9"/></svg>
                        بررسی
                    </button>` : '<span class="text-[11px] text-stone-300">تعیین‌تکلیف شده</span>'}
                </td>
            </tr>
        `).join('');

        rows.querySelectorAll('.act-review').forEach(b => b.addEventListener('click', () => openReview(b.dataset.id, data.data)));

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${data.total} درخواست</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${data.total} درخواست — صفحه ${data.current_page.toLocaleString('fa-IR')} از ${data.last_page.toLocaleString('fa-IR')}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    /* ---------- مودال بررسی ---------- */
    function openReview(id, list) {
        const row = list.find(r => r.id === +id);
        if (!row) return;
        reviewTarget = row;
        document.getElementById('w-id').value = row.id;
        document.getElementById('w-holder').textContent = row.holder;
        document.getElementById('w-amount').textContent = App.money(row.amount);
        document.getElementById('w-note').value = '';
        reviewModal.classList.remove('hidden');
        reviewModal.classList.add('flex');
    }

    function closeReview() {
        reviewModal.classList.add('hidden');
        reviewModal.classList.remove('flex');
        reviewTarget = null;
    }

    reviewModal.querySelectorAll('[data-close-review]').forEach(el => el.addEventListener('click', closeReview));

    async function submitReview(action) {
        if (!reviewTarget) return;
        const btn = action === 'pay' ? document.getElementById('btn-pay') : document.getElementById('btn-reject');
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>';

        try {
            const res = await App.ajax(`/admin/withdrawals/${reviewTarget.id}/review`, {
                method: 'PATCH',
                body: {
                    action,
                    note: document.getElementById('w-note').value.trim() || null,
                },
            });
            const data = await res.json().catch(() => ({}));
            closeReview();
            if (res.ok) { App.toast(data.message, 'success'); load(currentPage); }
            else App.toast(data.message || 'خطا', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }

    document.getElementById('btn-pay').addEventListener('click', () => submitReview('pay'));
    document.getElementById('btn-reject').addEventListener('click', () => submitReview('reject'));

    /* ---------- فیلتر ---------- */
    document.getElementById('status-filter').addEventListener('change', function () {
        currentStatus = this.value;
        load(1);
    });

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
