/**
 * کافی‌نت آنلاین — اسکریپت صفحه «مدیران سیستم»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 *
 * درخواست بازخوردی ۶-۴: سطح دسترسی (مدیر کل / مدیر دستیار)
 * + ماتریس بخش‌ها (Laravel Policy — AdminAccessPolicy)
 */
const PAGE = App.pageData();

(function () {
    let searchTimer = null, currentPage = 1;
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const modal = document.getElementById('modal');
    const form = document.getElementById('modal-form');
    const matrix = document.getElementById('ap-matrix');
    const sectionLabels = PAGE.sectionLabels || {};

    /* ---------- بارگذاری داده ---------- */
    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        const q = document.getElementById('search-input').value.trim();
        const res = await App.ajax('/admin/admins/data?page=' + page + '&q=' + encodeURIComponent(q));
        const data = await res.json();

        render(data);
    }

    function badge(active) {
        return active
            ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">فعال</span>'
            : '<span class="badge bg-stone-100 text-stone-500 border border-stone-200">غیرفعال</span>';
    }

    function roleBadge(r) {
        return r.is_super
            ? '<span class="badge bg-amber-50 text-amber-700 border border-amber-200">مدیر کل</span>'
            : '<span class="badge bg-sky-50 text-sky-700 border border-sky-200">مدیر دستیار</span>';
    }

    function sectionsCell(r) {
        if (r.is_super || r.sections_count === -1) {
            return '<span class="ap-section-chip" style="background:rgba(217,119,6,0.09);color:#b45309;border-color:rgba(217,119,6,0.25)">همهٔ بخش‌ها</span>';
        }

        if (!r.sections || !r.sections.length) {
            return '<span class="text-stone-400 text-xs">—</span>';
        }

        const chips = r.sections.map(s =>
            `<span class="ap-section-chip" title="${s}">${sectionLabels[s] || s}</span>`
        ).join('');

        return `<div class="ap-section-list">${chips}</div>`;
    }

    function render(data) {
        currentPage = data.current_page;

        if (!data.data.length) {
            rows.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-stone-400 text-xs">موردی یافت نشد.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = data.data.map(r => `
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <span class="grid place-items-center size-9 rounded-xl ${r.is_super ? 'bg-gradient-to-br from-amber-400/70 to-amber-600/70' : 'bg-gradient-to-br from-sky-400/70 to-sky-600/70'} text-white font-bold text-xs shrink-0">${(r.name || '?').slice(0, 1)}</span>
                        <div>
                            <p class="font-bold text-stone-800">${r.full_name} ${r.is_self ? '<span class="text-[10px] text-stone-400 font-medium">(شما)</span>' : ''}</p>
                            <p class="text-[11px] text-stone-400">عضویت: ${r.created_at}</p>
                        </div>
                    </div>
                </td>
                <td dir="ltr" class="text-left font-medium text-stone-600">${r.email || '—'}</td>
                <td>${roleBadge(r)}</td>
                <td>${sectionsCell(r)}</td>
                <td class="text-stone-500">${r.last_login_at}</td>
                <td>${badge(r.is_active)}</td>
                <td class="text-center">
                    <div class="adm-row-actions">
                        <button class="act-edit ui-row-btn" data-id="${r.id}" title="ویرایش و دسترسی‌ها" aria-label="ویرایش">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v16"/><path d="m21.12 8.88-8.24 8.24a2 2 0 0 1-1.42.58H8.5v-3a2 2 0 0 1 .58-1.42l8.24-8.24a2 2 0 0 1 2.82 0l1.98 1.98a2 2 0 0 1 0 2.82Z"/></svg>
                        </button>
                        ${r.is_self ? '' : `
                        <button class="act-toggle ui-row-btn" data-id="${r.id}" data-active="${r.is_active}" title="${r.is_active ? 'غیرفعال' : 'فعال'}" aria-label="تغییر وضعیت">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${r.is_active ? '<path d="M18.36 6.64A9 9 0 1 1 5.64 6.64"/><circle cx="12" cy="12" r="10"/><path d="M12 2v10"/>' : '<path d="m21.12 8.88-8.24 8.24a2 2 0 0 1-1.42.58H8.5v-3a2 2 0 0 1 .58-1.42l8.24-8.24a2 2 0 0 1 2.82 0l1.98 1.98a2 2 0 0 1 0 2.82Z"/>'}</svg>
                        </button>`}
                    </div>
                </td>
            </tr>
        `).join('');

        rows.querySelectorAll('.act-edit').forEach(b => b.addEventListener('click', () => openEdit(b.dataset.id, data.data)));
        rows.querySelectorAll('.act-toggle').forEach(b => b.addEventListener('click', () => askToggle(b.dataset.id, b.dataset.active === '1')));

        renderPagination(data);
    }

    function renderPagination(data) {
        if (data.last_page <= 1) {
            pagination.innerHTML = `<span>${data.total} مدیر</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${data.total} مدیر — صفحه ${data.current_page} از ${data.last_page}</span>
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
        document.getElementById('modal-title').textContent = 'افزودن مدیر جدید';
        document.getElementById('pass-hint').textContent = '(حداقل ۸ کاراکتر)';
        form.querySelectorAll('.err').forEach(e => e.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(e => e.classList.remove('field-error'));
        // پیش‌فرض: مدیر دستیار + هیچ بخشی
        setRole('admin');
        syncMatrixVisibility();
    }

    function setRole(role) {
        const radio = form.querySelector(`input[name="f-role"][value="${role}"]`);
        if (radio) radio.checked = true;
    }

    function currentRole() {
        const radio = form.querySelector('input[name="f-role"]:checked');
        return radio ? radio.value : 'admin';
    }

    function syncMatrixVisibility() {
        matrix.classList.toggle('is-hidden', currentRole() !== 'admin');
    }

    function openEdit(id, list) {
        const row = list.find(r => r.id === +id);
        if (!row) return;
        document.getElementById('f-id').value = row.id;
        document.getElementById('f-name').value = row.name || '';
        document.getElementById('f-family').value = row.family || '';
        document.getElementById('f-email').value = row.email || '';
        document.getElementById('f-mobile').value = row.mobile || '';
        document.getElementById('f-password').value = '';
        document.getElementById('modal-title').textContent = 'ویرایش مدیر و دسترسی‌ها';
        document.getElementById('pass-hint').textContent = '(خالی = بدون تغییر)';

        setRole(row.is_super ? 'super_admin' : 'admin');

        // تیک بخش‌های مجاز (فقط برای دستیار)
        matrix.querySelectorAll('.ap-perm:not(:disabled)').forEach(cb => {
            cb.checked = row.is_super || (row.sections || []).includes(cb.dataset.section);
        });

        syncMatrixVisibility();
        openModal();
    }

    document.getElementById('btn-new').addEventListener('click', openModal);
    form.querySelector('.modal-close').addEventListener('click', closeModal);
    modal.querySelector('[data-close]').addEventListener('click', closeModal);

    /* تغییر نقش → نمایش/پنهان ماتریس */
    form.querySelectorAll('input[name="f-role"]').forEach(radio => {
        radio.addEventListener('change', syncMatrixVisibility);
    });

    /* همه / هیچ‌کدام */
    document.getElementById('ap-all')?.addEventListener('click', () => {
        matrix.querySelectorAll('.ap-perm:not(:disabled)').forEach(cb => cb.checked = true);
    });
    document.getElementById('ap-none')?.addEventListener('click', () => {
        matrix.querySelectorAll('.ap-perm:not(:disabled)').forEach(cb => cb.checked = false);
    });

    function showErr(name, msg) {
        const el = form.querySelector(`.err[data-for="${name}"]`);
        if (el) { el.textContent = msg; el.classList.remove('hidden'); }
        document.getElementById('f-' + name)?.classList.add('field-error');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        form.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(el => el.classList.remove('field-error'));

        const id = document.getElementById('f-id').value;
        const role = currentRole();
        const permissions = role === 'admin'
            ? [...matrix.querySelectorAll('.ap-perm:not(:disabled)')]
                .filter(cb => cb.checked)
                .map(cb => cb.value)
            : [];

        const payload = {
            name: document.getElementById('f-name').value.trim(),
            family: document.getElementById('f-family').value.trim(),
            email: document.getElementById('f-email').value.trim(),
            mobile: document.getElementById('f-mobile').value.trim() || null,
            password: document.getElementById('f-password').value || null,
            role,
            permissions,
        };

        const btn = document.getElementById('modal-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const res = await App.ajax(id ? `/admin/admins/${id}` : '/admin/admins', {
                method: id ? 'PUT' : 'POST',
                body: payload,
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                closeModal();
                App.toast(data.message || 'ذخیره شد.', 'success');
                load(currentPage);
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => showErr(k, v[0]));
                App.toast(Object.values(data.errors)[0][0], 'error');
            } else {
                App.toast(data.message || 'خطا در ذخیره‌سازی.', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.textContent = 'ذخیره';
        }
    });

    /* ---------- تغییر وضعیت (PanelUI.confirm) ---------- */
    function askToggle(id, isActive) {
        const doToggle = async () => {
            const res = await App.ajax(`/admin/admins/${id}/toggle`, { method: 'PATCH' });
            const data = await res.json().catch(() => ({}));
            if (res.ok) { App.toast(data.message, 'success'); load(currentPage); }
            else App.toast(data.message || 'خطا', 'error');
        };

        if (window.PanelUI) {
            window.PanelUI.confirm(
                {
                    title: 'تغییر وضعیت مدیر',
                    desc: isActive
                        ? 'این مدیر غیرفعال شود و دیگر نتواند وارد پنل شود؟'
                        : 'این مدیر مجدداً فعال شود؟',
                    okText: 'بله، انجام بده',
                    danger: isActive,
                    icon: 'question',
                },
                doToggle
            );
        } else {
            doToggle();
        }
    }

    /* ---------- جستجو ---------- */
    document.getElementById('search-input').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 350);
    });

    /* ---------- بارگذاری اولیه ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load(1);
    }

    if (typeof window.App !== 'undefined') {
        boot();
    } else {
        window.addEventListener('app:ready', boot, { once: true });
        setTimeout(boot, 2500);
    }
})();
