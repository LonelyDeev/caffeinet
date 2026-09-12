/**
 * کافی‌نت آنلاین — اسکریپت صفحه «کارکنان و اپراتورها» (ادمین)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    let searchTimer = null, currentPage = 1;
    let currentPosition = '', currentActive = '', currentCoffeenet = '';
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');

    /* ---------- بارگذاری داده ---------- */
    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = '<tr><td colspan="8" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        const q = document.getElementById('search-input').value.trim();
        const res = await App.ajax('/admin/operators/data?page=' + page
            + '&q=' + encodeURIComponent(q)
            + '&position=' + currentPosition
            + '&active=' + currentActive
            + '&coffeenet_id=' + currentCoffeenet);
        const data = await res.json();
        render(data);
    }

    const positionBadges = {
        manager: 'bg-amber-50 text-amber-700 border border-amber-200',
        operator: 'bg-sky-50 text-sky-700 border border-sky-200',
    };
    const positionAvatars = {
        manager: 'from-amber-400/70 to-amber-600/70',
        operator: 'from-teal-400/70 to-teal-600/70',
    };

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="8" class="text-center py-10 text-stone-400 text-xs">کارمندی یافت نشد.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(r => `
            <tr class="ops-row" data-href="${App.url('/admin/operators/' + r.id)}" title="پروفایل کامل ${r.staff}">
                <td>
                    <div class="flex items-center gap-3">
                        <span class="grid place-items-center size-9 rounded-xl bg-gradient-to-br ${positionAvatars[r.position] || positionAvatars.operator} text-white font-bold text-xs shrink-0">${(r.staff || '؟').slice(0, 1)}</span>
                        <div>
                            <p class="font-bold text-stone-800 group-hover:text-amber-700">${r.staff} <span class="ops-open-hint" aria-hidden="true">↖</span></p>
                            <p class="text-[11px] text-stone-400" dir="ltr">${r.mobile || '—'}</p>
                        </div>
                    </div>
                </td>
                <td>
                    ${r.coffeenet_id
                        ? `<a class="ops-net-link" href="${App.url('/admin/coffeenets/' + r.coffeenet_id)}" title="جزئیات کافی‌نت">${r.coffeenet || '—'}</a>`
                        : '<span class="text-stone-400">—</span>'}
                </td>
                <td><span class="badge ${positionBadges[r.position] || positionBadges.operator}">${r.position_label}</span></td>
                <td>
                    ${r.approval_status === 'pending'
                        ? '<span class="badge bg-amber-50 text-amber-700 border border-amber-300 animate-pulse">⏳ در انتظار تایید</span>'
                        : r.approval_status === 'rejected'
                            ? '<span class="badge bg-rose-50 text-rose-600 border border-rose-200">رد شده</span>'
                            : r.is_active
                                ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">فعال</span>'
                                : '<span class="badge bg-stone-100 text-stone-500 border border-stone-200">غیرفعال</span>'}
                </td>
                <td class="text-center font-bold tabular-nums ${r.orders_done > 0 ? 'text-emerald-600' : 'text-stone-400'}">${r.orders_done.toLocaleString('fa-IR')}</td>
                <td class="text-center font-bold tabular-nums ${r.orders_active > 0 ? 'text-sky-600' : 'text-stone-400'}">${r.orders_active.toLocaleString('fa-IR')}</td>
                <td class="text-center font-bold tabular-nums ${r.chat_messages > 0 ? 'text-teal-600' : 'text-stone-400'}">${r.chat_messages.toLocaleString('fa-IR')}</td>
                <td class="text-stone-500 text-xs">${r.joined_at || '—'}</td>
                <td class="text-center whitespace-nowrap">
                    ${r.approval_status === 'pending' ? `
                        <button type="button" class="act-approve btn-primary !py-1.5 !px-3 !text-[11px]" data-id="${r.id}" title="تایید و فعال‌سازی کارمند">✓ تایید</button>
                        <button type="button" class="act-reject btn-ghost !py-1.5 !px-3 !text-[11px] !text-rose-600" data-id="${r.id}" title="رد درخواست افزودن">رد</button>
                    ` : ''}
                    <button type="button" class="act-edit btn-ghost !py-1.5 !px-3 !text-[11px]" data-id="${r.id}" title="ویرایش کامل (ورود/کافی‌نت/دسترسی‌ها)">ویرایش</button>
                    <button type="button" class="act-trash btn-ghost !py-1.5 !px-3 !text-[11px] ui-press !text-rose-600 hover:!bg-rose-50" data-trash="${r.user_id}" data-trash-label="${r.staff || ''}" title="حذف کارمند (به حذف‌شده‌ها)">حذف</button>
                </td>
            </tr>
        `).join('');

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${data.total} کارمند</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${data.total} کارمند — صفحه ${data.current_page.toLocaleString('fa-IR')} از ${data.last_page.toLocaleString('fa-IR')}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    /* ---------- مودال ویرایش کامل کارمند (درخواست بازخوردی) ---------- */
    const editModal = document.getElementById('edit-modal');
    const editForm = document.getElementById('edit-form');
    const editSave = document.getElementById('edit-save');
    const editError = document.getElementById('edit-error');
    const editSubtitle = document.getElementById('edit-subtitle');
    let editTarget = null;

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

    function fillEditModal(r) {
        editTarget = r;
        editSubtitle.textContent = r.staff + ' — ' + (r.coffeenet || '');

        document.getElementById('f-assignment-id').value = r.id;
        document.getElementById('f-name').value = r.name || '';
        document.getElementById('f-family').value = r.family || '';
        document.getElementById('f-email').value = r.email || '';
        document.getElementById('f-mobile').value = r.mobile || '';
        document.getElementById('f-password').value = '';
        document.getElementById('f-user-active').checked = !!r.user_is_active;
        document.getElementById('f-assignment-active').checked = !!r.is_active;

        const netSelect = document.getElementById('f-coffeenet');
        netSelect.value = String(r.coffeenet_id || '');

        document.getElementById('f-position').value = r.position || 'operator';

        // دسترسی‌ها
        document.querySelectorAll('.perm-check').forEach(cb => {
            cb.checked = (r.permissions || []).includes(cb.value);
        });
        syncPermsBox();

        // حقوق — مقادیر فعلی از سرور (show endpoint) خوانده می‌شود
        document.getElementById('f-salary-type').value = 'percent';
        document.getElementById('f-salary-rate').value = '';
        document.getElementById('f-overtime-rate').value = '';
        loadSalary(r.id);

        editForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        editError.classList.add('hidden');
    }

    async function loadSalary(assignmentId) {
        try {
            const res = await App.ajax('/admin/operators/' + assignmentId + '/salary');
            const data = await res.json();
            if (res.ok && data.data) {
                document.getElementById('f-salary-type').value = data.data.type || 'percent';
                document.getElementById('f-salary-rate').value = data.data.rate ?? '';
                document.getElementById('f-overtime-rate').value = data.data.overtime_rate ?? '';
            }
        } catch { /* بی‌صدا */ }
    }

    function syncPermsBox() {
        const isOperator = document.getElementById('f-position').value === 'operator';
        document.getElementById('perms-box').style.display = isOperator ? '' : 'none';
    }

    document.getElementById('f-position')?.addEventListener('change', syncPermsBox);

    rows.addEventListener('click', function (e) {
        const approveBtn = e.target.closest('.act-approve');
        if (approveBtn) {
            e.stopPropagation();
            const id = parseInt(approveBtn.dataset.id, 10);
            approveRow(id);
            return;
        }

        const rejectBtn = e.target.closest('.act-reject');
        if (rejectBtn) {
            e.stopPropagation();
            const id = parseInt(rejectBtn.dataset.id, 10);
            rejectRow(id);
            return;
        }

        const btn = e.target.closest('.act-edit');
        if (btn) {
            e.stopPropagation();
            const id = parseInt(btn.dataset.id, 10);
            const row = currentRows.find(r => r.id === id);
            if (row) {
                fillEditModal(row);
                openEditModal();
            }
        }
    });

    /* ---------- تایید/رد کارمند در انتظار ---------- */
    async function approveRow(id) {
        if (!confirm('این کارمند تایید و بلافاصله فعال شود؟')) { return; }
        try {
            const res = await App.ajax('/admin/operators/' + id + '/approve', { method: 'PATCH' });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                App.toast(data.message || 'کارمند تایید شد.', 'success');
                load(currentPage);
            } else {
                App.toast(data.message || 'خطا در تایید.', 'error');
            }
        } catch { App.toast('ارتباط با سرور برقرار نشد.', 'error'); }
    }

    async function rejectRow(id) {
        const reason = prompt('دلیل رد (اختیاری):', '');
        if (reason === null) { return; }
        try {
            const res = await App.ajax('/admin/operators/' + id + '/reject', {
                method: 'PATCH',
                body: { reason: reason.trim() || null },
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                App.toast(data.message || 'درخواست رد شد.', 'success');
                load(currentPage);
            } else {
                App.toast(data.message || 'خطا در رد درخواست.', 'error');
            }
        } catch { App.toast('ارتباط با سرور برقرار نشد.', 'error'); }
    }

    let currentRows = [];
    const _render = render;
    render = function (data) {
        currentRows = data.data;
        _render(data);
    };

    editForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!editTarget) return;

        editForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        editError.classList.add('hidden');

        const payload = {
            name: document.getElementById('f-name').value.trim(),
            family: document.getElementById('f-family').value.trim() || null,
            email: document.getElementById('f-email').value.trim(),
            mobile: document.getElementById('f-mobile').value.trim() || null,
            password: document.getElementById('f-password').value || null,
            user_is_active: document.getElementById('f-user-active').checked,
            coffeenet_id: parseInt(document.getElementById('f-coffeenet').value, 10) || 0,
            position: document.getElementById('f-position').value,
            is_active: document.getElementById('f-assignment-active').checked,
            permissions: [...document.querySelectorAll('.perm-check:checked')].map(cb => cb.value),
            salary_type: document.getElementById('f-salary-type').value,
            salary_rate: parseFloat(document.getElementById('f-salary-rate').value) || 0,
            overtime_rate: parseFloat(document.getElementById('f-overtime-rate').value) || null,
        };

        editSave.disabled = true;
        editSave.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const res = await App.ajax('/admin/operators/' + editTarget.id, {
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

    /* ---------- رفتن به پروفایل با کلیک روی ردیف (درخواست بازخوردی ۶-۱) ---------- */
    rows.addEventListener('click', function (e) {
        if (e.target.closest('a') || e.target.closest('.act-edit')) return; // لینک/دکمهٔ ویرایش خودشان مقصد دارند

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

    document.getElementById('position-filter').addEventListener('change', function () {
        currentPosition = this.value;
        load(1);
    });

    document.getElementById('active-filter').addEventListener('change', function () {
        currentActive = this.value;
        load(1);
    });

    document.getElementById('coffeenet-filter').addEventListener('change', function () {
        currentCoffeenet = this.value;
        load(1);
    });

    /* ---------- مودال افزودن اپراتور (مدیر کل — فاز ۱۳) ---------- */
    const addModal = document.getElementById('add-modal');
    const addForm = document.getElementById('add-form');
    const addSave = document.getElementById('add-save');
    const addError = document.getElementById('add-error');

    function openAddModal() {
        addForm.reset();
        addForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        addError.classList.add('hidden');
        // پیش‌فرض‌ها: دسترسی‌های استاندارد
        document.getElementById('a-salary-rate').value = 50;
        syncAddPermsBox();
        addModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('a-name')?.focus(), 100);
    }

    function closeAddModal() {
        addModal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.getElementById('btn-add-operator')?.addEventListener('click', openAddModal);

    addModal?.querySelectorAll('[data-close-modal]').forEach(el => {
        el.addEventListener('click', closeAddModal);
    });

    function syncAddPermsBox() {
        const isOperator = document.getElementById('a-position').value === 'operator';
        document.getElementById('a-perms-box').style.display = isOperator ? '' : 'none';
    }

    document.getElementById('a-position')?.addEventListener('change', syncAddPermsBox);

    addForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        addForm.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        addError.classList.add('hidden');

        const coffeenetId = parseInt(document.getElementById('a-coffeenet').value, 10) || 0;
        if (!coffeenetId) {
            const err = addForm.querySelector('.err[data-for="coffeenet_id"]');
            err.textContent = 'تعیین کافی‌نت برای اپراتور الزامی است.';
            err.classList.remove('hidden');
            return;
        }

        const payload = {
            name: document.getElementById('a-name').value.trim(),
            family: document.getElementById('a-family').value.trim() || null,
            email: document.getElementById('a-email').value.trim(),
            mobile: document.getElementById('a-mobile').value.trim() || null,
            password: document.getElementById('a-password').value,
            coffeenet_id: coffeenetId,
            position: document.getElementById('a-position').value,
            permissions: [...document.querySelectorAll('.a-perm-check:checked')].map(cb => cb.value),
            salary_type: document.getElementById('a-salary-type').value,
            salary_rate: parseFloat(document.getElementById('a-salary-rate').value) || 50,
            overtime_rate: parseFloat(document.getElementById('a-overtime-rate').value) || null,
        };

        addSave.disabled = true;
        addSave.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ایجاد...';

        try {
            const res = await App.ajax('/admin/operators', { method: 'POST', body: payload });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'اپراتور ایجاد شد.', 'success');
                closeAddModal();
                load(1);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = addForm.querySelector(`.err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                });
                App.toast(data.message || Object.values(data.errors)[0][0], 'error');
            } else {
                addError.textContent = data.message || 'خطا در ایجاد اپراتور.';
                addError.classList.remove('hidden');
                App.toast(data.message || 'خطا در ایجاد اپراتور.', 'error');
            }
        } catch {
            addError.textContent = 'ارتباط با سرور برقرار نشد.';
            addError.classList.remove('hidden');
        } finally {
            addSave.disabled = false;
            addSave.innerHTML = 'ایجاد اپراتور';
        }
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
        window.AdminTrash.mount({ section: 'operators' });
    }
});
