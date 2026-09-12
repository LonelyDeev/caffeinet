/**
 * کافی‌نت آنلاین — اسکریپت صفحه «کافی‌نت‌ها»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
/* داده‌های سرور (از #page-data) */
const PAGE = App.pageData();
window.__referralReward = Number(PAGE.referral_reward) || 0;

(function () {
    let searchTimer = null, currentPage = 1, currentStatus = String(PAGE.status || ''), currentOrg = '';
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const modal = document.getElementById('modal');
    const form = document.getElementById('modal-form');

    document.getElementById('status-filter').value = currentStatus;

    /* ---------- بارگذاری داده ---------- */
    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = '<tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        const q = document.getElementById('search-input').value.trim();
        const res = await App.ajax('/admin/coffeenets/data?page=' + page + '&q=' + encodeURIComponent(q) + '&status=' + currentStatus + '&organization_id=' + currentOrg);
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
            rows.innerHTML = '<tr><td colspan="6" class="text-center py-10 text-stone-400 text-xs">کافی‌نتی یافت نشد.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(r => `
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <a href="${App.url('/admin/coffeenets/' + r.id)}" class="flex items-center gap-3 shrink-0" title="جزئیات کامل کافی‌نت">
                            <span class="grid place-items-center size-9 rounded-xl bg-gradient-to-br from-rose-400/70 to-rose-600/70 text-white font-bold text-xs shrink-0">${(r.name || '؟').slice(0, 1)}</span>
                        </a>
                        <div>
                            <p class="font-bold"><a href="${App.url('/admin/coffeenets/' + r.id)}" class="text-amber-700 underline transition-colors">${r.name}</a></p>
                            <p class="text-xs text-stone-400">${r.phone || '—'} · ثبت: ${r.created_at}</p>
                        </div>
                    </div>
                </td>
                <td>
                    ${r.is_independent
                        ? '<span class="badge bg-stone-100 text-stone-500 border border-stone-200">مستقل</span>'
                        : `<span class="badge bg-teal-50 text-teal-700 border border-teal-200">${r.organization}</span>${r.introduction_reward_paid ? ' <span class="text-[10px] text-emerald-600" title="پاداش معرفی پرداخت شده">🏅</span>' : ''}`}
                </td>
                <td class="text-stone-600 font-semibold">
                    ${r.manager || '—'}
                    ${!r.manager ? ' <span class="badge bg-rose-50 text-rose-600 border border-rose-200" title="کاربر مدیر (اطلاعات ورود) تعریف نشده — اعلان‌ها گیرنده ندارند">بدون حساب مدیر</span>' : ''}
                </td>
                <td class="text-stone-500">${r.city ? r.city + '، ' : ''}${r.province || '—'}</td>
                <td><span class="badge ${statusColors[r.status.color]}">${r.status.label}</span></td>
                <td class="text-center">
                    <div class="adm-row-actions">
                        ${r.status.value !== 'approved' ? `
                        <button class="act-approve ui-row-btn" data-id="${r.id}" title="تأیید" aria-label="تأیید کافی‌نت">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </button>` : ''}
                        <button class="act-edit ui-row-btn" data-id="${r.id}" title="ویرایش" aria-label="ویرایش">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v16"/><path d="m21.12 8.88-8.24 8.24a2 2 0 0 1-1.42.58H8.5v-3a2 2 0 0 1 .58-1.42l8.24-8.24a2 2 0 0 1 2.82 0l1.98 1.98a2 2 0 0 1 0 2.82Z"/></svg>
                        </button>
                        <button class="act-trash ui-row-btn" data-trash="${r.id}" data-trash-label="${r.name || ''}" data-tone="danger" title="حذف (به حذف‌شده‌ها)" aria-label="حذف کافی‌net">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        ${r.status.value !== 'suspended' ? `
                        <button class="act-suspend ui-row-btn" data-id="${r.id}" title="تعلیق" aria-label="تعلیق کافی‌نت">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                        </button>` : ''}
                        ${r.status.value !== 'rejected' ? `
                        <button class="act-reject ui-row-btn" data-tone="danger" data-id="${r.id}" title="رد" aria-label="رد کافی‌نت">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('');

        rows.querySelectorAll('.act-approve').forEach(b => b.addEventListener('click', () => askStatus(b.dataset.id, 'approved')));
        rows.querySelectorAll('.act-suspend').forEach(b => b.addEventListener('click', () => askStatus(b.dataset.id, 'suspended')));
        rows.querySelectorAll('.act-reject').forEach(b => b.addEventListener('click', () => askStatus(b.dataset.id, 'rejected')));
        rows.querySelectorAll('.act-edit').forEach(b => b.addEventListener('click', () => {
            openEdit(data.data.find(r => r.id === +b.dataset.id));
        }));

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${data.total} کافی‌نت</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${data.total} کافی‌نت — صفحه ${data.current_page.toLocaleString('fa-IR')} از ${data.last_page.toLocaleString('fa-IR')}</span>
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
        document.getElementById('approve-wrap').classList.remove('hidden');
        document.getElementById('manager-basic-fields').classList.remove('hidden');
        document.getElementById('f-city').disabled = true;
        document.getElementById('f-city').innerHTML = '<option value="">ابتدا استان را انتخاب کنید</option>';
        document.getElementById('modal-title').textContent = 'ثبت کافی‌نت جدید';
        document.getElementById('pass-hint').textContent = '(حداقل ۸ کاراکتر)';
        document.getElementById('no-manager-hint').classList.add('hidden');
        document.getElementById('f-manager-password').required = true;
        form.querySelectorAll('.err').forEach(e => e.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(e => e.classList.remove('field-error'));
    }

    async function openEdit(row) {
        if (!row) return;
        document.getElementById('f-id').value = row.id;
        document.getElementById('f-name').value = row.name || '';
        document.getElementById('f-phone').value = row.phone || '';
        document.getElementById('f-address').value = row.address || '';
        document.getElementById('f-org').value = row.organization_id || '';
        document.getElementById('approve-wrap').classList.add('hidden');
        // درخواست بازخوردی: اطلاعات مدیر کافی‌نت در ویرایش هم قابل تغییر است
        document.getElementById('manager-basic-fields').classList.remove('hidden');
        document.getElementById('f-manager-name').value = row.manager_name || '';
        document.getElementById('f-manager-family').value = row.manager_family || '';
        document.getElementById('f-manager-email').value = row.manager_email || '';
        document.getElementById('f-manager-mobile').value = row.manager_mobile || '';
        document.getElementById('f-manager-email').required = true;
        document.getElementById('f-manager-password').value = '';
        document.getElementById('modal-title').textContent = 'ویرایش کافی‌نت «' + row.name + '»';

        // v28 — کافی‌نت بدون کاربر مدیر (معرفی‌شده توسط سازمان): راهنما + الزامی‌شدن رمز
        const hasManager = !!(row.manager_email || row.manager_user_id);
        document.getElementById('no-manager-hint').classList.toggle('hidden', hasManager);
        document.getElementById('f-manager-name').required = true;
        document.getElementById('f-manager-password').required = !hasManager;
        document.getElementById('pass-hint').textContent = hasManager
            ? '(خالی = بدون تغییر رمز مدیر)'
            : '(الزامی — حساب مدیر ساخته می‌شود)';
        document.getElementById('f-manager-password').closest('div').classList.remove('hidden');

        // بازسازی استان/شهر
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
    function fieldId(name) {
        const map = {
            manager_name: 'f-manager-name', manager_family: 'f-manager-family',
            manager_email: 'f-manager-email', manager_mobile: 'f-manager-mobile',
            manager_password: 'f-manager-password', name: 'f-name', phone: 'f-phone', city_id: 'f-city',
        };
        return document.getElementById(map[name] || ('f-' + name));
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        form.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(el => el.classList.remove('field-error'));

        const id = document.getElementById('f-id').value;

        const payload = {
            name: document.getElementById('f-name').value.trim(),
            phone: document.getElementById('f-phone').value.trim() || null,
            province_id: document.getElementById('f-province').value || null,
            city_id: document.getElementById('f-city').value || null,
            address: document.getElementById('f-address').value.trim() || null,
            organization_id: document.getElementById('f-org').value || null,
        };

        if (id) {
            payload.manager_password = document.getElementById('f-manager-password').value || null;
            payload.manager_name = document.getElementById('f-manager-name').value.trim() || null;
            payload.manager_family = document.getElementById('f-manager-family').value.trim() || null;
            payload.manager_email = document.getElementById('f-manager-email').value.trim() || null;
            payload.manager_mobile = document.getElementById('f-manager-mobile').value.trim() || null;
        } else {
            payload.approve = document.getElementById('f-approve').checked;
            payload.manager_name = document.getElementById('f-manager-name').value.trim();
            payload.manager_family = document.getElementById('f-manager-family').value.trim() || null;
            payload.manager_email = document.getElementById('f-manager-email').value.trim();
            payload.manager_mobile = document.getElementById('f-manager-mobile').value.trim() || null;
            payload.manager_password = document.getElementById('f-manager-password').value || null;
        }

        const btn = document.getElementById('modal-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const res = await App.ajax(id ? `/admin/coffeenets/${id}` : '/admin/coffeenets', {
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
            btn.textContent = 'ذخیره کافی‌نت';
        }
    });

    /* ---------- تغییر وضعیت ---------- */
    const statusTexts = {
        approved: 'این کافی‌نت تأیید و فعال شود؟',
        suspended: 'این کافی‌نت به‌طور موقت تعلیق شود؟',
        rejected: 'این کافی‌نت رد شود؟',
    };

    function askStatus(id, status) {
        const doStatus = async () => {
            try {
                const res = await App.ajax(`/admin/coffeenets/${id}/status`, {
                    method: 'PATCH',
                    body: { status },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) { App.toast(data.message, 'success'); load(currentPage); }
                else App.toast(data.message || 'خطا', 'error');
            } catch {
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            }
        };

        let desc = statusTexts[status] || 'آیا مطمئن هستید؟';

        if (status === 'approved') {
            // نمایش پیش‌آگهی پاداش معرفی (اگر تنظیم شده باشد)
            const reward = window.__referralReward || 0;
            if (reward > 0) {
                desc += ' — 🏅 با تأیید، پاداش معرفی ' + App.money(reward) + ' به کیف پول سازمان معرف واریز می‌شود.';
            }
        }

        if (window.PanelUI) {
            window.PanelUI.confirm(
                {
                    title: 'تغییر وضعیت کافی‌نت',
                    desc,
                    okText: 'بله، انجام بده',
                    danger: status === 'rejected',
                    icon: 'question',
                },
                doStatus
            );
        } else {
            doStatus();
        }
    }

    /* ---------- فیلترها ---------- */
    document.getElementById('search-input').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 350);
    });

    document.getElementById('status-filter').addEventListener('change', function () {
        currentStatus = this.value;
        load(1);
    });

    document.getElementById('org-filter').addEventListener('change', function () {
        currentOrg = this.value;
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
        window.AdminTrash.mount({ section: 'coffeenets' });
    }
});
