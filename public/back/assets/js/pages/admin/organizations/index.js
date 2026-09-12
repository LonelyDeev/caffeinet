/**
 * کافی‌نت آنلاین — اسکریپت صفحه «سازمان‌ها»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    const PAGE = App.pageData();
    let searchTimer = null, currentPage = 1, currentStatus = String(PAGE.status || ''), statusTargetId = null;
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const modal = document.getElementById('modal');
    const form = document.getElementById('modal-form');
    const statusModal = document.getElementById('status-modal');

    document.getElementById('status-filter').value = currentStatus;

    /* ---------- بارگذاری داده ---------- */
    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = '<tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        const q = document.getElementById('search-input').value.trim();
        const res = await App.ajax('/admin/organizations/data?page=' + page + '&q=' + encodeURIComponent(q) + '&status=' + currentStatus);
        const data = await res.json();
        render(data);
    }

    const statusColors = {
        amber: 'bg-amber-50 text-amber-700 border border-amber-200',
        emerald: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rose: 'bg-rose-50 text-rose-600 border border-rose-200',
        stone: 'bg-stone-100 text-stone-500 border border-stone-200',
    };

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">سازمانی یافت نشد.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(r => `
            <tr>
                <td>
                    <a href="${App.url('/admin/organizations/' + r.id)}" class="flex items-center gap-3 min-w-0" title="جزئیات سازمان «${r.name}»" aria-label="مشاهدهٔ جزئیات سازمان ${r.name}">
                        <span class="grid place-items-center size-9 rounded-xl bg-gradient-to-br from-teal-400/70 to-teal-600/70 text-white font-bold text-xs shrink-0">${(r.name || '؟').slice(0, 1)}</span>
                        <span class="min-w-0">
                            <span class="block font-bold text-stone-800">${r.name}</span>
                            <span class="block text-[11px] text-stone-400">${r.type} · ${r.coffeenets_count} کافی‌نت</span>
                        </span>
                    </a>
                </td>
                <td>
                    <p class="font-semibold text-stone-700">${r.owner || '—'}</p>
                    <p class="text-[11px] text-stone-400" dir="ltr">${r.owner_email || ''}</p>
                </td>
                <td class="text-stone-500">${r.city ? r.city + '، ' : ''}${r.province || '—'}</td>
                <td class="font-bold tabular-nums text-stone-700">${App.money(r.wallet_balance, false)} <span class="text-[10px] font-normal text-stone-400">تومان</span></td>
                <td><span class="badge ${statusColors[r.status.color]}">${r.status.label}</span></td>
                <td class="text-center">
                    <div class="adm-row-actions">
                        <button class="act-edit ui-row-btn" data-id="${r.id}" title="ویرایش" aria-label="ویرایش">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v16"/><path d="m21.12 8.88-8.24 8.24a2 2 0 0 1-1.42.58H8.5v-3a2 2 0 0 1 .58-1.42l8.24-8.24a2 2 0 0 1 2.82 0l1.98 1.98a2 2 0 0 1 0 2.82Z"/></svg>
                        </button>
                        <button class="act-status ui-row-btn" data-id="${r.id}" data-status="${r.status.value}" title="تغییر وضعیت" aria-label="تغییر وضعیت">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9"/><path d="M21 3v9h-9"/></svg>
                        </button>
                        <button class="act-trash ui-row-btn" data-trash="${r.id}" data-trash-label="${r.name || ''}" data-tone="danger" title="حذف سازمان (به حذف‌شده‌ها)" aria-label="حذف سازمان">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        rows.querySelectorAll('.act-edit').forEach(b => b.addEventListener('click', () => {
            const row = data.data.find(r => r.id === +b.dataset.id);
            openEdit(row);
        }));
        rows.querySelectorAll('.act-status').forEach(b => b.addEventListener('click', () => askStatus(b.dataset.id, b.dataset.status)));

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${data.total} سازمان</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${data.total} سازمان — صفحه ${data.current_page.toLocaleString('fa-IR')} از ${data.last_page.toLocaleString('fa-IR')}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    /* ---------- مودال ---------- */
    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        form.reset();
        document.getElementById('f-id').value = '';
        document.getElementById('f-status').closest('div').classList.remove('hidden');
        document.getElementById('owner-basic-fields').classList.remove('hidden');
        document.getElementById('f-city').disabled = true;
        document.getElementById('f-city').innerHTML = '<option value="">ابتدا استان را انتخاب کنید</option>';
        document.getElementById('modal-title').textContent = 'ثبت سازمان جدید';
        document.getElementById('pass-hint').textContent = '(حداقل ۸ کاراکتر)';
        form.querySelectorAll('.err').forEach(e => e.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(e => e.classList.remove('field-error'));
    }

    async function openEdit(row) {
        if (!row) return;
        document.getElementById('f-id').value = row.id;
        document.getElementById('f-name').value = row.name || '';
        document.getElementById('f-type').value = row.type === 'حقوقی' ? 'legal' : 'individual';
        document.getElementById('f-status').closest('div').classList.add('hidden');
        // درخواست بازخوردی: اطلاعات مدیر سازمان در ویرایش هم قابل تغییر است
        document.getElementById('owner-basic-fields').classList.remove('hidden');
        document.getElementById('f-owner-name').value = row.owner_name || '';
        document.getElementById('f-owner-family').value = row.owner_family || '';
        document.getElementById('f-owner-email').value = row.owner_email || '';
        document.getElementById('f-owner-mobile').value = row.owner_mobile || '';
        document.getElementById('f-owner-email').required = true;
        document.getElementById('f-address').value = row.address || '';
        document.getElementById('f-national-id').value = row.national_id || '';
        document.getElementById('f-phone').value = row.phone || '';
        document.getElementById('f-owner-password').value = '';
        document.getElementById('modal-title').textContent = 'ویرایش سازمان «' + row.name + '»';
        document.getElementById('pass-hint').textContent = '(خالی = بدون تغییر رمز مدیر)';

        // بازسازی انتخاب استان/شهر بر اساس نام‌های دیتا
        const provSelect = document.getElementById('f-province');
        const citySelect = document.getElementById('f-city');
        let matched = false;
        for (const opt of provSelect.options) {
            if (opt.text === row.province) {
                provSelect.value = opt.value;
                if (opt.value) await loadCities(opt.value, citySelect, row.city);
                matched = true;
                break;
            }
        }
        if (!matched) {
            provSelect.value = '';
            citySelect.disabled = true;
            citySelect.innerHTML = '<option value="">ابتدا استان را انتخاب کنید</option>';
        }

        openModal();
    }

    document.getElementById('btn-new').addEventListener('click', openModal);
    form.querySelector('.modal-close').addEventListener('click', closeModal);
    modal.querySelector('[data-close]').addEventListener('click', closeModal);

    /* ---------- سلکت آبشاری ---------- */
    async function loadCities(provinceId, citySelect, selectedName) {
        citySelect.disabled = true;
        citySelect.innerHTML = '<option value="">در حال بارگذاری…</option>';
        try {
            const res = await App.ajax('/admin/geo/cities?province_id=' + provinceId);
            const data = await res.json();
            citySelect.innerHTML = '<option value="">— انتخاب شهرستان —</option>' + data.cities.map(c =>
                `<option value="${c.id}">${c.name}</option>`).join('');
            if (selectedName) {
                for (const opt of citySelect.options) {
                    if (opt.text === selectedName) { citySelect.value = opt.value; break; }
                }
            }
            citySelect.disabled = false;
        } catch {
            citySelect.innerHTML = '<option value="">خطا در بارگذاری شهرستان‌ها</option>';
        }
    }

    document.getElementById('f-province').addEventListener('change', function () {
        const citySelect = document.getElementById('f-city');
        if (!this.value) {
            citySelect.disabled = true;
            citySelect.innerHTML = '<option value="">ابتدا استان را انتخاب کنید</option>';
            return;
        }
        loadCities(this.value, citySelect);
    });

    /* ---------- ارسال فرم ---------- */
    function showErr(name, msg) {
        const el = form.querySelector(`.err[data-for="${name}"]`);
        if (el) { el.textContent = msg; el.classList.remove('hidden'); }
        const field = document.getElementById('f-' + name) || document.getElementById('f-' + name.replace('_', '_'));
        if (field) field.classList.add('field-error');
    }

    function fieldId(name) {
        const map = { owner_name: 'f-owner-name', owner_family: 'f-owner-family', owner_email: 'f-owner-email', owner_mobile: 'f-owner-mobile', owner_password: 'f-owner-password', name: 'f-name', national_id: 'f-national-id', phone: 'f-phone', city_id: 'f-city' };
        return document.getElementById(map[name] || ('f-' + name));
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        form.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(el => el.classList.remove('field-error'));

        const id = document.getElementById('f-id').value;

        const payload = {
            name: document.getElementById('f-name').value.trim(),
            type: document.getElementById('f-type').value,
            national_id: document.getElementById('f-national-id').value.trim() || null,
            phone: document.getElementById('f-phone').value.trim() || null,
            province_id: document.getElementById('f-province').value || null,
            city_id: document.getElementById('f-city').value || null,
            address: document.getElementById('f-address').value.trim() || null,
        };

        if (id) {
            payload.owner_password = document.getElementById('f-owner-password').value || null;
            payload.owner_name = document.getElementById('f-owner-name').value.trim() || null;
            payload.owner_family = document.getElementById('f-owner-family').value.trim() || null;
            payload.owner_email = document.getElementById('f-owner-email').value.trim() || null;
            payload.owner_mobile = document.getElementById('f-owner-mobile').value.trim() || null;
        } else {
            payload.status = document.getElementById('f-status').value;
            payload.owner_name = document.getElementById('f-owner-name').value.trim();
            payload.owner_family = document.getElementById('f-owner-family').value.trim() || null;
            payload.owner_email = document.getElementById('f-owner-email').value.trim();
            payload.owner_mobile = document.getElementById('f-owner-mobile').value.trim() || null;
            payload.owner_password = document.getElementById('f-owner-password').value || null;
        }

        const btn = document.getElementById('modal-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const res = await App.ajax(id ? `/admin/organizations/${id}` : '/admin/organizations', {
                method: id ? 'PUT' : 'POST',
                body: payload,
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                closeModal();
                App.toast(data.message || 'ذخیره شد.', 'success');
                load(currentPage);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = form.querySelector(`.err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                    const fld = fieldId(k);
                    if (fld) fld.classList.add('field-error');
                });
                App.toast(Object.values(data.errors)[0][0], 'error');
            } else {
                App.toast(data.message || 'خطا در ذخیره‌سازی.', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.textContent = 'ذخیره سازمان';
        }
    });

    /* ---------- تغییر وضعیت ---------- */
    function askStatus(id, status) {
        statusTargetId = id;
        document.getElementById('s-status').value = status;
        document.getElementById('s-note').value = '';
        statusModal.classList.remove('hidden');
        statusModal.classList.add('flex');
    }

    function closeStatus() {
        statusModal.classList.add('hidden');
        statusModal.classList.remove('flex');
        statusTargetId = null;
    }

    statusModal.querySelectorAll('[data-close-status]').forEach(el => el.addEventListener('click', closeStatus));

    document.getElementById('status-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!statusTargetId) return;
        const btn = document.getElementById('status-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> اعمال...';

        try {
            const res = await App.ajax(`/admin/organizations/${statusTargetId}/status`, {
                method: 'PATCH',
                body: {
                    status: document.getElementById('s-status').value,
                    note: document.getElementById('s-note').value.trim() || null,
                },
            });
            const data = await res.json().catch(() => ({}));
            closeStatus();
            if (res.ok) { App.toast(data.message, 'success'); load(currentPage); }
            else App.toast(data.message || 'خطا', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'اعمال';
        }
    });

    /* ---------- فیلترها ---------- */
    document.getElementById('search-input').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 350);
    });

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


/* v28 — حذف نرم/دائم این بخش (trash.js) */
document.addEventListener('DOMContentLoaded', function () {
    if (window.AdminTrash) {
        window.AdminTrash.mount({ section: 'organizations' });
    }
});
