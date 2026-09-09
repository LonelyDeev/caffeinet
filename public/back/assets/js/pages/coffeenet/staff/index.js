/**
 * کافی‌نت آنلاین — اسکریپت صفحه «کارمندان»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    const PAGE = App.pageData();
    const CATALOG = PAGE.catalog;
    const DEFAULTS = PAGE.defaults;
    const BASE = PAGE.base;

    let editingId = null;      // id رکورد StaffAssignment
    let editingSelf = false;   // کارمند = خودِ مدیر
    let currentPage = 1;

    const els = {
        tbody: document.getElementById('staff-tbody'),
        pagination: document.getElementById('staff-pagination'),
        summary: document.getElementById('rows-summary'),
        search: document.getElementById('staff-search'),
        position: document.getElementById('position-filter'),
        status: document.getElementById('status-filter'),
        modal: document.getElementById('staff-modal'),
        form: document.getElementById('staff-form'),
        modalTitle: document.getElementById('modal-title'),
        modalSubtitle: document.getElementById('modal-subtitle'),
        modalError: document.getElementById('modal-error'),
        email: document.getElementById('f-email'),
        emailHint: document.getElementById('email-hint'),
        password: document.getElementById('f-password'),
        passwordReq: document.getElementById('password-req'),
        permBox: document.getElementById('permissions-box'),
        salaryType: document.getElementById('f-salary-type'),
        rateLabel: document.getElementById('rate-label'),
        rateHint: document.getElementById('rate-hint'),
        save: document.getElementById('btn-save'),
    };

    /* ---------- بارگذاری لیست ---------- */
    async function load(page = 1) {
        currentPage = page;
        els.tbody.innerHTML = skeletonRows(8);

        const params = new URLSearchParams();
        if (els.search.value.trim()) params.set('q', els.search.value.trim());
        if (els.position.value) params.set('position', els.position.value);
        if (els.status.value !== '') params.set('status', els.status.value);
        params.set('page', page);

        try {
            const res = await App.ajax(`${BASE}/data?${params}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            currentPage = data.current_page;
            renderRows(data);
            renderPagination(data);
        } catch {
            els.tbody.innerHTML = '<tr><td colspan="8" class="!py-10 text-center text-rose-400 text-xs">خطا در دریافت لیست.</td></tr>';
        }
    }

    /* اسکلتون بارگذاری */
    function skeletonRows(cols) {
        let rows = '';
        for (let i = 0; i < 4; i++) {
            rows += `<tr><td colspan="${cols}" class="!py-4">
                <div class="space-y-2.5">
                    <div class="ui-skeleton h-3 w-full"></div>
                    <div class="ui-skeleton h-3 w-40"></div>
                    <div class="ui-skeleton h-3 w-32"></div>
                </div>
            </td></tr>`;
        }
        return rows;
    }

    function salaryText(row) {
        if (!row.salary) return '<span class="text-[11px] text-stone-400">تعیین نشده</span>';
        const rate = row.salary.type === 'percent'
            ? App.money(row.salary.rate, false) + '٪'
            : App.money(row.salary.rate);
        let extra = '';
        if (row.salary.overtime_rate !== null && row.salary.overtime_rate > 0) {
            extra = `<span class="block text-[10px] text-stone-400">اضافه‌کار: ${App.money(row.salary.overtime_rate)}</span>`;
        }
        return `<span class="text-[11px] font-semibold text-stone-600">${row.salary.type_label} — ${rate}</span>${extra}`;
    }

    function renderRows(data) {
        if (!data.data.length) {
            els.tbody.innerHTML = `<tr><td colspan="8" class="!py-10">
                <div class="ui-empty">
                    <span class="ui-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <p class="text-sm font-semibold text-stone-500">کارمندی یافت نشد</p>
                    <p class="text-xs text-stone-400 mt-1">اولین کارمند خود را با دکمه «افزودن کارمند» ثبت کنید.</p>
                </div>
            </td></tr>`;
            return;
        }

        els.tbody.innerHTML = data.data.map(row => {
            const selfBadge = row.is_self ? '<span class="badge bg-amber-50 text-amber-700 border border-amber-200 ms-1">شما</span>' : '';
            return `
            <tr class="group">
                <td>
                    <div class="flex items-center gap-2.5">
                        <span class="grid place-items-center size-9 rounded-xl ${row.is_self ? 'bg-amber-200 text-amber-800' : 'bg-stone-100 text-stone-500'} text-xs font-bold shrink-0">${escapeHtml(String(row.name || '؟').charAt(0))}</span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-stone-700 truncate">${escapeHtml(row.full_name)}${selfBadge}</p>
                            <p class="text-[10px] text-stone-400 truncate" dir="ltr">${escapeHtml(row.email)}</p>
                            ${row.user_active === false ? '<span class="text-[10px] text-rose-500">حساب کاربری غیرفعال است</span>' : ''}
                        </div>
                    </div>
                </td>
                <td class="text-[11px] text-stone-500" dir="ltr">${escapeHtml(row.mobile || '—')}</td>
                <td>
                    <span class="badge ${row.position.value === 'manager' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-stone-50 text-stone-600 border border-stone-200'}">${row.position.label}</span>
                </td>
                <td>${salaryText(row)}</td>
                <td>
                    ${row.position.value === 'manager'
                        ? '<span class="text-[11px] text-stone-400">کامل</span>'
                        : `<span class="badge bg-stone-50 text-stone-600 border border-stone-200">${fa(row.permissions_count)} مورد</span>`}
                </td>
                <td>
                    ${row.approval_status === 'pending'
                        ? '<span class="badge bg-amber-50 text-amber-700 border border-amber-300">⏳ در انتظار تایید مدیر کل</span>'
                        : `<span class="badge ${row.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200'}">${row.is_active ? 'فعال' : 'غیرفعال'}</span>`}
                </td>
                <td class="text-[11px] text-stone-400 whitespace-nowrap">${escapeHtml(row.last_login_at)}</td>
                <td>
                    <div class="flex items-center justify-center gap-1.5">
                        <button type="button" class="act-edit ui-row-btn" data-row='${JSON.stringify(row).replace(/'/g, '&#39;')}' title="ویرایش" aria-label="ویرایش کارمند">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
                        </button>
                        ${row.is_self ? '' : `
                        <button type="button" class="act-toggle ui-row-btn ${row.is_active ? '' : 'no-row-activate'}" ${row.is_active ? 'data-tone="danger"' : ''} data-id="${row.id}" data-active="${row.is_active ? 1 : 0}" data-name="${escapeHtml(row.full_name)}" title="${row.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی'}" aria-label="${row.is_active ? 'غیرفعال‌سازی کارمند' : 'فعال‌سازی کارمند'}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${row.is_active ? 'M18.36 6.64A9 9 0 1 1 5.64 6.64' : 'm21 12-9-9-9 9'}"/><path d="M12 2v10"/></svg>
                        </button>`}
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            els.pagination.innerHTML = `<span class="text-[11px] text-stone-400">${fa(data.total)} کارمند</span>`;
            return;
        }
        els.pagination.innerHTML = `
            <span class="text-[11px] text-stone-400">${fa(data.total)} کارمند — صفحه ${fa(data.current_page)} از ${fa(data.last_page)}</span>
            <div class="flex items-center gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page - 1}" ${data.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${data.current_page + 1}" ${data.current_page >= data.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
    }

    /* ---------- مودال ---------- */
    function openModal(mode, row = null) {
        editingId = row ? row.id : null;
        editingSelf = row ? !!row.is_self : false;
        els.form.reset();
        els.modalError.classList.add('hidden');
        document.querySelectorAll('.err').forEach(e => e.classList.add('hidden'));

        if (mode === 'add') {
            els.modalTitle.textContent = 'افزودن کارمند جدید';
            els.modalSubtitle.textContent = 'حساب کاربری، دسترسی‌ها و مدل حقوق در یک گام';
            els.email.disabled = false;
            els.emailHint.classList.remove('hidden');
            els.passwordReq.classList.remove('hidden');
            els.save.innerHTML = 'ذخیره کارمند';
            setPermissions(DEFAULTS);
            checkPosition('operator');
            applySalaryUi('percent');
        } else {
            els.modalTitle.textContent = 'ویرایش کارمند';
            els.modalSubtitle.textContent = row.full_name;
            els.email.value = row.email;
            els.email.disabled = true;
            els.emailHint.classList.add('hidden');
            els.passwordReq.classList.add('hidden');
            els.save.innerHTML = 'ذخیره تغییرات';
            document.getElementById('f-name').value = row.name || '';
            document.getElementById('f-family').value = row.family || '';
            document.getElementById('f-mobile').value = row.mobile || '';
            els.password.value = '';
            checkPosition(row.position.value);
            setPermissions(row.permissions || []);
            if (row.salary) {
                els.salaryType.value = row.salary.type;
                applySalaryUi(row.salary.type);
                document.getElementById('f-salary-rate').value = row.salary.rate;
                document.getElementById('f-overtime').value = row.salary.overtime_rate !== null ? row.salary.overtime_rate : '';
            } else {
                els.salaryType.value = 'percent';
                applySalaryUi('percent');
            }

            if (editingSelf) {
                document.querySelector('input[name="position"][value="manager"]').checked = true;
                checkPosition('manager');
            }
        }

        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('f-name').focus(), 100);
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        document.body.style.overflow = '';
        editingId = null;
        editingSelf = false;
    }

    function setPermissions(list) {
        document.querySelectorAll('.perm-check').forEach(cb => {
            cb.checked = list.includes(cb.value);
        });
    }

    function checkPosition(value) {
        document.querySelector(`input[name="position"][value="${value}"]`).checked = true;
        // دسترسی‌ها فقط برای اپراتور؛ مدیر به‌طور کامل دسترسی دارد
        els.permBox.classList.toggle('opacity-40', value === 'manager');
        els.permBox.classList.toggle('pointer-events-none', value === 'manager');
    }

    function applySalaryUi(type) {
        if (type === 'percent') {
            els.rateLabel.textContent = 'درصد';
            els.rateHint.textContent = 'سهم کارمند از مبلغ سفارش (۰ تا ۱۰۰)';
            document.getElementById('f-salary-rate').max = '100';
        } else if (type === 'fixed_per_order') {
            els.rateLabel.textContent = 'مبلغ هر سفارش';
            els.rateHint.textContent = 'تومان — مبلغ ثابت به‌ازای هر سفارش موفق';
            document.getElementById('f-salary-rate').max = '1000000000';
        } else {
            els.rateLabel.textContent = 'حقوق ماهیانه';
            els.rateHint.textContent = 'تومان — مبلغ ثابت ماهانه (گزارشی)';
            document.getElementById('f-salary-rate').max = '1000000000';
        }
    }

    /* ---------- ذخیره ---------- */
    async function save(e) {
        e.preventDefault();
        els.modalError.classList.add('hidden');
        document.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));

        const payload = {
            name: document.getElementById('f-name').value.trim(),
            family: document.getElementById('f-family').value.trim() || null,
            email: els.email.value.trim(),
            mobile: document.getElementById('f-mobile').value.trim() || null,
            password: els.password.value || null,
            position: document.querySelector('input[name="position"]:checked').value,
            salary_type: els.salaryType.value,
            salary_rate: document.getElementById('f-salary-rate').value,
            overtime_rate: document.getElementById('f-overtime').value || null,
        };

        if (payload.position === 'operator') {
            payload.permissions = [...document.querySelectorAll('.perm-check:checked')].map(cb => cb.value);
        }

        /* اعتبارسنجی سمت کلاینت */
        const errors = {};
        if (!payload.name) errors.name = ['نام کارمند الزامی است.'];
        if (!editingId && !payload.email) errors.email = ['ایمیل کارمند الزامی است.'];
        if (!editingId && !payload.password) errors.password = ['برای کارمند جدید تعیین رمز عبور الزامی است.'];
        if (payload.salary_rate === '' || isNaN(+payload.salary_rate)) errors.salary_rate = ['مقدار حقوق الزامی است.'];
        if (Object.keys(errors).length) {
            showErrors(errors);
            return;
        }

        els.save.disabled = true;
        const original = els.save.innerHTML;
        els.save.innerHTML = '<span class="size-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span> در حال ذخیره…';

        try {
            const res = await App.ajax(editingId ? `${BASE}/${editingId}` : `${BASE}`, {
                method: editingId ? 'PUT' : 'POST',
                body: payload,
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'ذخیره شد.', 'success');
                closeModal();
                load(currentPage);
            } else if (res.status === 422 && data.errors) {
                showErrors(data.errors);
                els.modalError.textContent = data.message || Object.values(data.errors)[0][0];
                els.modalError.classList.remove('hidden');
            } else {
                els.modalError.textContent = data.message || 'خطا در ذخیره‌سازی.';
                els.modalError.classList.remove('hidden');
            }
        } catch {
            els.modalError.textContent = 'ارتباط با سرور برقرار نشد.';
            els.modalError.classList.remove('hidden');
        } finally {
            els.save.disabled = false;
            els.save.innerHTML = original;
        }

        function showErrors(errs) {
            Object.entries(errs).forEach(([k, v]) => {
                const el = document.querySelector(`.err[data-for="${k}"]`);
                if (el) { el.textContent = Array.isArray(v) ? v[0] : v; el.classList.remove('hidden'); }
            });
            const box = document.getElementById('modal-error');
            box.textContent = Object.values(errs)[0][0] || 'خطا در اعتبارسنجی.';
            box.classList.remove('hidden');
        }
    }

    /* ---------- فعال/غیرفعال ---------- */
    async function toggleStaff(btn) {
        const name = btn.dataset.name;
        const deactivating = btn.dataset.active === '1';

        const run = async () => {
            btn.disabled = true;
            try {
                const res = await App.ajax(`${BASE}/${btn.dataset.id}/toggle`, { method: 'PATCH' });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    App.toast(data.message || 'انجام شد.', 'success');
                    load(currentPage);
                } else {
                    btn.disabled = false;
                    App.toast(data.message || 'خطا در تغییر وضعیت.', 'error');
                }
            } catch {
                btn.disabled = false;
                App.toast('ارتباط با سرور برقرار نشد.', 'error');
            }
        };

        if (window.PanelUI) {
            window.PanelUI.confirm(
                {
                    title: `${deactivating ? 'غیرفعال‌سازی' : 'فعال‌سازی'} کارمند`,
                    desc: `آیا مطمئن هستید که «${name}» ${deactivating ? 'غیرفعال' : 'فعال'} شود؟`,
                    okText: deactivating ? 'غیرفعال شود' : 'فعال شود',
                    danger: deactivating,
                    icon: 'question'
                },
                run
            );
        } else {
            run();
        }
    }

    /* ---------- رویدادها ---------- */
    let searchTimer = null;
    function bindEvents() {
        els.search.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => load(1), 350);
        });
        els.position.addEventListener('change', () => load(1));
        els.status.addEventListener('change', () => load(1));

        document.getElementById('btn-add').addEventListener('click', () => openModal('add'));
        els.form.addEventListener('submit', save);

        els.modal.querySelectorAll('[data-close-modal]').forEach(el => {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !els.modal.classList.contains('hidden')) closeModal();
        });

        document.querySelectorAll('input[name="position"]').forEach(radio => {
            radio.addEventListener('change', () => checkPosition(radio.value));
        });

        els.salaryType.addEventListener('change', () => applySalaryUi(els.salaryType.value));

        document.getElementById('perm-all').addEventListener('click', () => setPermissions(Object.keys(CATALOG)));
        document.getElementById('perm-none').addEventListener('click', () => setPermissions([]));

        /* عملیات ردیف‌ها (event delegation) */
        els.tbody.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.act-edit');
            if (editBtn) {
                openModal('edit', JSON.parse(editBtn.dataset.row));
                return;
            }
            const toggleBtn = e.target.closest('.act-toggle');
            if (toggleBtn) toggleStaff(toggleBtn);
        });

        els.pagination.addEventListener('click', (e) => {
            const btn = e.target.closest('.pg-btn');
            if (btn && !btn.disabled) load(+btn.dataset.page);
        });
    }

    /* ---------- helpers ---------- */
    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[c]);
    }
    function fa(n) { return String(n ?? 0).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

    /* ---------- boot (پس از آماده‌شدن App) ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        bindEvents();
        load(1);
    }

    window.addEventListener('app:ready', boot, { once: true });
    setTimeout(boot, 2500);
})();
