/**
 * کافی‌نت آنلاین — اسکریپت صفحه «مشتریان» (ادمین — درخواست بازخوردی)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    let searchTimer = null, currentPage = 1;
    let currentStatus = '', currentProfile = '', currentProvince = '', currentSort = 'newest';
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const PAGE = App.pageData();

    let currentRows = [];

    /* تبدیل ارقام فارسی → انگلیسی (برای تاریخ تولد) */
    function toEnDigits(v) {
        return String(v || '').replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
            .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
    }

    /* ---------- بارگذاری داده ---------- */
    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        const q = document.getElementById('search-input').value.trim();
        const res = await App.ajax('/admin/customers/data?page=' + page
            + '&q=' + encodeURIComponent(q)
            + '&status=' + currentStatus
            + '&profile=' + currentProfile
            + '&province_id=' + currentProvince
            + '&sort=' + currentSort);
        const data = await res.json();
        render(data);
    }

    function render(data) {
        currentPage = data.current_page;
        currentRows = data.data;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-stone-400 text-xs">مشتری‌ای یافت نشد.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(r => `
            <tr class="ops-row" data-href="${App.url('/admin/customers/' + r.id)}" title="پروفایل کامل ${r.full_name}">
                <td>
                    <div class="flex items-center gap-3">
                        <span class="grid place-items-center size-9 rounded-xl bg-gradient-to-br ${r.is_active ? 'from-amber-400/70 to-amber-600/70' : 'from-stone-300 to-stone-400'} text-white font-bold text-xs shrink-0">${(r.name || '؟').slice(0, 1)}</span>
                        <div>
                            <p class="font-bold text-stone-800 group-hover:text-amber-700">${r.full_name} <span class="ops-open-hint" aria-hidden="true">↖</span></p>
                            <p class="text-[11px] text-stone-400" dir="ltr">${r.mobile || '—'}</p>
                        </div>
                    </div>
                </td>
                <td class="text-stone-600 text-xs">${r.province ? (r.province + ' / ' + (r.city || '—')) : '—'}</td>
                <td>
                    ${r.is_active
                        ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">فعال</span>'
                        : '<span class="badge bg-rose-50 text-rose-600 border border-rose-200">مسدود</span>'}
                </td>
                <td>
                    ${r.profile_completed
                        ? '<span class="badge bg-teal-50 text-teal-700 border border-teal-200">کامل</span>'
                        : '<span class="badge bg-amber-50 text-amber-700 border border-amber-200">ناقص</span>'}
                </td>
                <td class="text-center font-bold tabular-nums ${r.orders > 0 ? 'text-sky-600' : 'text-stone-400'}">${r.orders.toLocaleString('fa-IR')}</td>
                <td class="text-center font-bold tabular-nums ${r.spent > 0 ? 'text-emerald-600' : 'text-stone-400'}">${App.money(r.spent)}</td>
                <td class="text-center font-bold tabular-nums ${r.wallet > 0 ? 'text-amber-600' : 'text-stone-400'}">${App.money(r.wallet)}</td>
                <td class="text-stone-500 text-xs whitespace-nowrap">${r.last_login_fa || '—'}</td>
                <td class="text-center whitespace-nowrap">
                    <button type="button" class="act-edit btn-ghost !py-1.5 !px-3 !text-[11px] ui-press" data-id="${r.id}" title="ویرایش اطلاعات">ویرایش</button>
                    ${r.is_active
                        ? `<button type="button" class="act-ban btn-ghost !py-1.5 !px-3 !text-[11px] ui-press !text-rose-600 hover:!bg-rose-50" data-id="${r.id}" title="مسدودسازی (خروج اجباری)">مسدود</button>`
                        : `<button type="button" class="act-unban btn-ghost !py-1.5 !px-3 !text-[11px] ui-press !text-emerald-600 hover:!bg-emerald-50" data-id="${r.id}" title="رفع مسدودی">رفع بن</button>`}
                </td>
            </tr>
        `).join('');

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${data.total.toLocaleString('fa-IR')} مشتری</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${data.total.toLocaleString('fa-IR')} مشتری — صفحه ${data.current_page.toLocaleString('fa-IR')} از ${data.last_page.toLocaleString('fa-IR')}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    /* ---------- مودال ویرایش کامل مشتری ---------- */
    const editModal = document.getElementById('edit-modal');
    const editForm = document.getElementById('edit-form');
    const editSave = document.getElementById('edit-save');
    const editError = document.getElementById('edit-error');
    const editSubtitle = document.getElementById('edit-subtitle');
    let editTarget = null;
    let citiesCache = {};

    function openEditModal() {
        editModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        editModal.classList.add('hidden');
        document.body.style.overflow = '';
        editTarget = null;
    }

    editModal?.querySelectorAll('[data-close-modal]').forEach(el => {
        el.addEventListener('click', closeEditModal);
    });

    async function loadCities(provinceId, selectedId) {
        const select = document.getElementById('f-city');
        select.innerHTML = '<option value="">در حال بارگذاری…</option>';

        if (!provinceId) {
            select.innerHTML = '<option value="">ابتدا استان را انتخاب کنید…</option>';
            return;
        }

        if (!citiesCache[provinceId]) {
            try {
                const res = await App.ajax((PAGE.geoCitiesUrl || '/admin/geo/cities') + '?province_id=' + provinceId);
                const data = await res.json();
                citiesCache[provinceId] = data.cities || [];
            } catch {
                citiesCache[provinceId] = [];
            }
        }

        select.innerHTML = '<option value="">انتخاب شهر…</option>' + citiesCache[provinceId].map(c =>
            `<option value="${c.id}">${c.name}</option>`).join('');
        if (selectedId) select.value = String(selectedId);
    }

    function fillEditModal(r) {
        editTarget = r;
        editSubtitle.textContent = r.full_name + ' — ' + (r.mobile || '');

        document.getElementById('f-id').value = r.id;
        document.getElementById('f-name').value = r.name || '';
        document.getElementById('f-family').value = r.family || '';
        document.getElementById('f-mobile').value = r.mobile || '';
        document.getElementById('f-email').value = r.email || '';
        document.getElementById('f-gender').value = r.gender || '';
        document.getElementById('f-birthdate').value = r.birthdate_fa || '';
        document.getElementById('f-province').value = r.province_id ? String(r.province_id) : '';
        document.getElementById('f-password').value = '';
        document.getElementById('f-profile-completed').checked = !!r.profile_completed;
        document.getElementById('f-is-active').checked = !!r.is_active;

        loadCities(r.province_id, r.city_id);

        editForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        editError.classList.add('hidden');
    }

    document.getElementById('f-province')?.addEventListener('change', function () {
        loadCities(this.value ? parseInt(this.value, 10) : 0, null);
    });

    editForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!editTarget) return;

        editForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        editError.classList.add('hidden');

        const payload = {
            name: document.getElementById('f-name').value.trim(),
            family: document.getElementById('f-family').value.trim() || null,
            mobile: document.getElementById('f-mobile').value.trim(),
            email: document.getElementById('f-email').value.trim() || null,
            gender: document.getElementById('f-gender').value || null,
            birthdate: toEnDigits(document.getElementById('f-birthdate').value.trim()),
            province_id: parseInt(document.getElementById('f-province').value, 10) || null,
            city_id: parseInt(document.getElementById('f-city').value, 10) || null,
            profile_completed: document.getElementById('f-profile-completed').checked,
            is_active: document.getElementById('f-is-active').checked,
            password: document.getElementById('f-password').value || null,
        };

        editSave.disabled = true;
        editSave.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const res = await App.ajax('/admin/customers/' + editTarget.id, {
                method: 'PUT',
                body: payload,
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'ذخیره شد.', 'success');
                closeEditModal();
                load(currentPage);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = editForm.querySelector(`.err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                });
                App.toast(data.message || Object.values(data.errors)[0][0], 'error');
            } else {
                editError.textContent = data.message || 'خطا در ذخیره‌سازی.';
                editError.classList.remove('hidden');
                App.toast(data.message || 'خطا در ذخیره‌سازی.', 'error');
            }
        } catch {
            editError.textContent = 'ارتباط با سرور برقرار نشد.';
            editError.classList.remove('hidden');
        } finally {
            editSave.disabled = false;
            editSave.innerHTML = 'ذخیرهٔ تغییرات';
        }
    });

    /* ---------- مودال مسدودسازی ---------- */
    const banModal = document.getElementById('ban-modal');
    const banForm = document.getElementById('ban-form');
    const banSave = document.getElementById('ban-save');
    const banError = document.getElementById('ban-error');
    const banSubtitle = document.getElementById('ban-subtitle');
    let banTarget = null;

    function openBanModal() {
        banModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeBanModal() {
        banModal.classList.add('hidden');
        document.body.style.overflow = '';
        banTarget = null;
        document.getElementById('ban-reason').value = '';
        banError.classList.add('hidden');
    }

    banModal?.querySelectorAll('[data-close-modal]').forEach(el => {
        el.addEventListener('click', closeBanModal);
    });

    banForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!banTarget) return;

        const reason = document.getElementById('ban-reason').value.trim();
        if (reason.length < 3) {
            banError.classList.remove('hidden');
            return;
        }
        banError.classList.add('hidden');

        banSave.disabled = true;
        banSave.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال مسدودسازی...';

        try {
            const res = await App.ajax('/admin/customers/' + banTarget.id + '/ban', {
                method: 'PATCH',
                body: { reason },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'مسدود شد.', 'success');
                closeBanModal();
                load(currentPage);
            } else {
                App.toast(data.message || 'خطا در مسدودسازی.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            banSave.disabled = false;
            banSave.innerHTML = 'مسدودسازی حساب';
        }
    });

    /* ---------- رفع مسدودی ---------- */
    async function unban(r) {
        if (!confirm('رفع مسدودی «' + r.full_name + '»؟ دوباره می‌تواند وارد اپ شود.')) return;

        try {
            const res = await App.ajax('/admin/customers/' + r.id + '/unban', { method: 'PATCH' });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                App.toast(data.message || 'فعال شد.', 'success');
                load(currentPage);
            } else {
                App.toast(data.message || 'خطا در رفع مسدودی.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        }
    }

    /* ---------- عملیات ردیف ---------- */
    rows.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.act-edit');
        if (editBtn) {
            e.stopPropagation();
            const id = parseInt(editBtn.dataset.id, 10);
            const row = currentRows.find(r => r.id === id);
            if (row) {
                fillEditModal(row);
                openEditModal();
            }
            return;
        }

        const banBtn = e.target.closest('.act-ban');
        if (banBtn) {
            e.stopPropagation();
            const id = parseInt(banBtn.dataset.id, 10);
            const row = currentRows.find(r => r.id === id);
            if (row) {
                banTarget = row;
                banSubtitle.textContent = row.full_name + ' — ' + (row.mobile || '');
                openBanModal();
            }
            return;
        }

        const unbanBtn = e.target.closest('.act-unban');
        if (unbanBtn) {
            e.stopPropagation();
            const id = parseInt(unbanBtn.dataset.id, 10);
            const row = currentRows.find(r => r.id === id);
            if (row) unban(row);
            return;
        }

        // کلیک روی ردیف → پروفایل
        if (e.target.closest('a') || e.target.closest('button')) return;
        const row = e.target.closest('tr.ops-row');
        if (row && row.dataset.href) {
            window.location.assign(row.dataset.href);
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

    document.getElementById('profile-filter').addEventListener('change', function () {
        currentProfile = this.value;
        load(1);
    });

    document.getElementById('province-filter').addEventListener('change', function () {
        currentProvince = this.value;
        load(1);
    });

    document.getElementById('sort-filter').addEventListener('change', function () {
        currentSort = this.value;
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
