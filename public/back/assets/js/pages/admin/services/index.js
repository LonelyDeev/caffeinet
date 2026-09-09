/**
 * کافی‌نت آنلاین — اسکریپت صفحه «خدمات و فرم‌ساز»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    let searchTimer = null, currentPage = 1, deleteId = null, expandedFieldUid = null;
    const rows = document.getElementById('rows');
    const pagination = document.getElementById('pagination');
    const versionsModal = document.getElementById('versions-modal');
    const deleteModal = document.getElementById('delete-modal');

    /* ---------- flash toast از فرم‌ساز ---------- */
    try {
        const flash = sessionStorage.getItem('flash-toast');
        if (flash) {
            sessionStorage.removeItem('flash-toast');
            const [msg, type] = flash.split('||');
            setTimeout(() => App.toast(msg, type || 'success'), 350);
        }
    } catch (e) {}

    /* ---------- بارگذاری داده ---------- */
    async function load(page) {
        page = page || currentPage;
        rows.innerHTML = '<tr><td colspan="8" class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</td></tr>';

        const q = document.getElementById('search-input').value.trim();
        const categoryId = document.getElementById('filter-category').value;
        const status = document.getElementById('filter-status').value;
        const featured = document.getElementById('filter-featured').checked ? '1' : '';

        const params = new URLSearchParams({ page, q, category_id: categoryId, status, featured });
        const res = await App.ajax('/admin/services/data?' + params.toString());
        const payload = await res.json();

        render(payload.data || [], payload.meta || {});
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function statusBadge(active) {
        return active
            ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">فعال</span>'
            : '<span class="badge bg-stone-100 text-stone-500 border border-stone-200">غیرفعال</span>';
    }

    function render(items, meta) {
        currentPage = meta.current_page || 1;

        if (!items.length) {
            rows.innerHTML = `<tr><td colspan="8" class="!py-4">
                <div class="ui-empty">
                    <span class="ui-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                    </span>
                    <p class="text-xs font-bold text-stone-500">خدمتی یافت نشد</p>
                    <p class="text-[11px] text-stone-400 mt-1">با دکمه «خدمت جدید» اولین خدمت کاتالوگ را بسازید.</p>
                </div>
            </td></tr>`;
            pagination.innerHTML = '';
            return;
        }

        rows.innerHTML = items.map(s => `
        <tr class="group">
            <td>
                <div class="flex items-center gap-2.5">
                    ${s.is_featured ? '<svg class="size-4 text-amber-400 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" title="ویژه"><path d="M12 2l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.02l-5.88 3.09 1.12-6.55-4.76-4.64 6.58-.96z"/></svg>' : ''}
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-stone-800 truncate max-w-44 sm:max-w-56">${esc(s.name)}</p>
                        <p class="text-[10px] text-stone-400 font-mono truncate max-w-44 sm:max-w-56" dir="ltr">${esc(s.slug)}</p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            ${s.requires_upload ? '<span class="badge bg-sky-50 text-sky-600 border border-sky-100 !text-[9px] !px-1.5 !py-0">📎 آپلود</span>' : ''}
                            ${s.requires_verification ? '<span class="badge bg-violet-50 text-violet-600 border border-violet-100 !text-[9px] !px-1.5 !py-0">🛡️ تأیید</span>' : ''}
                        </div>
                    </div>
                </div>
            </td>
            <td class="hidden md:table-cell">
                <p class="text-xs text-stone-600 font-semibold">${esc(s.category || '—')}</p>
                ${s.category_parent ? `<p class="text-[10px] text-stone-400">${esc(s.category_parent)}</p>` : ''}
            </td>
            <td>
                <p class="text-xs font-extrabold text-stone-800 whitespace-nowrap">${(Number(s.base_price) || 0).toLocaleString('fa-IR')} <span class="font-normal text-stone-400">تومان</span></p>
                ${s.estimated_time ? `<p class="text-[10px] text-stone-400 whitespace-nowrap">${Number(s.estimated_time).toLocaleString('fa-IR')} دقیقه</p>` : ''}
            </td>
            <td class="hidden lg:table-cell">
                ${Number(s.commissionable) > 0
                    ? `<p class="text-xs font-extrabold text-amber-700 whitespace-nowrap">${Number(s.commissionable).toLocaleString('fa-IR')}</p><p class="text-[10px] text-stone-400">از ${Number(s.costs_count).toLocaleString('fa-IR')} ردیف هزینه</p>`
                    : '<p class="text-[11px] text-stone-300">—</p>'}
            </td>
            <td class="hidden lg:table-cell">
                <div class="flex items-center gap-1.5 text-[11px] text-stone-500">
                    <span class="badge bg-stone-50 border border-stone-100">🧩 ${Number(s.fields_count).toLocaleString('fa-IR')} فیلد</span>
                    <span class="badge bg-stone-50 border border-stone-100">💰 ${Number(s.costs_count).toLocaleString('fa-IR')} هزینه</span>
                </div>
            </td>
            <td class="hidden sm:table-cell">
                <span class="badge bg-amber-50 text-amber-700 border border-amber-100 font-mono">v${Number(s.version).toLocaleString('fa-IR', { useGrouping: false })}</span>
                <p class="text-[10px] text-stone-400 mt-1 whitespace-nowrap">${esc(s.updated_at)}</p>
            </td>
            <td>${statusBadge(s.is_active)}</td>
            <td>
                <div class="adm-row-actions">
                    <a href="/admin/services/${s.id}/edit" class="ui-row-btn" title="ویرایش در فرم‌ساز" aria-label="ویرایش ${esc(s.name)}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v16"/><path d="m21.12 8.88-8.24 8.24a2 2 0 0 1-1.42.58H8.5v-3a2 2 0 0 1 .58-1.42l8.24-8.24a2 2 0 0 1 2.82 0l1.98 1.98a2 2 0 0 1 0 2.82Z"/></svg>
                    </a>
                    <button type="button" data-versions="${s.id}" data-service-name="${esc(s.name)}" class="ui-row-btn" title="تاریخچه نسخه‌ها" aria-label="نسخه‌های ${esc(s.name)}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    </button>
                    <button type="button" data-toggle="${s.id}" data-toggle-name="${esc(s.name)}" class="ui-row-btn" title="${s.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی'}" aria-label="${s.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی'} ${esc(s.name)}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9"/><path d="M21 3v9h-9"/></svg>
                    </button>
                    <button type="button" data-delete="${s.id}" data-delete-name="${esc(s.name)}" class="ui-row-btn" data-tone="danger" title="حذف" aria-label="حذف ${esc(s.name)}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </td>
        </tr>`).join('');

        bindRowActions();
        renderPagination(meta);
    }

    function renderPagination(meta) {
        const itemsTotal = Number(meta.total || 0);
        if (!meta.last_page || meta.last_page <= 1) {
            pagination.innerHTML = `<span>${itemsTotal.toLocaleString('fa-IR')} خدمت</span>`;
            return;
        }
        pagination.innerHTML = `
            <span>${Number(meta.total).toLocaleString('fa-IR')} خدمت — صفحه ${Number(meta.current_page).toLocaleString('fa-IR')} از ${Number(meta.last_page).toLocaleString('fa-IR')}</span>
            <div class="flex gap-2">
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${meta.current_page - 1}" ${meta.current_page <= 1 ? 'disabled' : ''}>قبلی</button>
                <button class="pg-btn btn-ghost !py-1.5 !px-3 !text-xs" data-page="${meta.current_page + 1}" ${meta.current_page >= meta.last_page ? 'disabled' : ''}>بعدی</button>
            </div>`;
        pagination.querySelectorAll('.pg-btn').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
    }

    function bindRowActions() {
        rows.querySelectorAll('[data-toggle]').forEach(b => b.addEventListener('click', () => doToggle(+b.dataset.toggle, b.dataset.toggleName)));
        rows.querySelectorAll('[data-delete]').forEach(b => b.addEventListener('click', () => askDelete(+b.dataset.delete, b.dataset.deleteName)));
        rows.querySelectorAll('[data-versions]').forEach(b => b.addEventListener('click', () => openVersions(+b.dataset.versions, b.dataset.serviceName)));
    }

    /* ---------- فعال/غیرفعال ---------- */
    async function doToggle(id, name) {
        try {
            const res = await App.ajax(`/admin/services/${id}/toggle`, { method: 'PATCH' });
            const data = await res.json();
            if (res.ok) { App.toast(data.message, 'success'); load(); }
            else App.toast(data.message || 'خطا', 'error');
        } catch { App.toast('خطای ارتباط با سرور.', 'error'); }
    }

    /* ---------- حذف ---------- */
    function askDelete(id, name) {
        deleteId = id;
        document.getElementById('del-name').textContent = name;
        deleteModal.classList.remove('hidden');
        deleteModal.classList.add('flex');
    }

    function closeDelete() {
        deleteModal.classList.add('hidden');
        deleteModal.classList.remove('flex');
        deleteId = null;
    }

    deleteModal.querySelectorAll('[data-close-del]').forEach(el => el.addEventListener('click', closeDelete));

    document.getElementById('delete-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!deleteId) return;
        const btn = document.getElementById('delete-confirm');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> حذف...';

        try {
            const res = await App.ajax(`/admin/services/${deleteId}`, { method: 'DELETE' });
            const data = await res.json().catch(() => ({}));
            closeDelete();
            if (res.ok) { App.toast(data.message, 'success'); load(); }
            else App.toast(data.message || 'حذف ممکن نیست.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'حذف کن';
        }
    });

    /* ---------- نسخه‌ها ---------- */
    async function openVersions(id, name) {
        document.getElementById('versions-service-name').textContent = name;
        const list = document.getElementById('versions-list');
        list.innerHTML = '<p class="text-center py-8 text-stone-400 text-xs">در حال بارگذاری...</p>';
        versionsModal.classList.remove('hidden');
        versionsModal.classList.add('flex');
        document.body.style.overflow = 'hidden';

        const res = await App.ajax(`/admin/services/${id}/versions`);
        const data = await res.json();
        const versions = data.versions || [];

        if (!versions.length) {
            list.innerHTML = '<p class="text-center py-8 text-stone-400 text-xs">نسخه‌ای ثبت نشده است.</p>';
            return;
        }

        list.innerHTML = versions.map((v, i) => `
        <div class="rounded-2xl border ${i === 0 ? 'border-amber-200 bg-amber-50/50' : 'border-stone-100 bg-white'} px-4 py-3 flex flex-wrap items-center gap-3 transition-all">
            <span class="grid place-items-center size-9 rounded-xl ${i === 0 ? 'bg-amber-100 text-amber-700' : 'bg-stone-50 text-stone-400'} font-mono text-[11px] font-bold shrink-0">v${Number(v.version).toLocaleString('fa-IR', { useGrouping: false })}</span>
            ${i === 0 ? '<span class="badge bg-amber-100 text-amber-800 border border-amber-200 !text-[10px]">نسخه جاری</span>' : ''}
            <div class="flex-1 min-w-40">
                <p class="text-xs font-bold text-stone-700">${esc(v.created_by)} <span class="font-normal text-stone-400 text-[10px]">${esc(v.created_at)}</span></p>
                <p class="text-[10px] text-stone-400 mt-0.5">
                    قیمت پایه ${(Number(v.base_price) || 0).toLocaleString('fa-IR')} تومان
                    · ${Number(v.costs_count).toLocaleString('fa-IR')} هزینه
                    · ${Number(v.fields_count).toLocaleString('fa-IR')} فیلد فرم
                </p>
            </div>
            ${Number(v.commissionable) > 0 ? `<span class="badge bg-amber-50 text-amber-700 border border-amber-100 whitespace-nowrap">کمیسیون: ${Number(v.commissionable).toLocaleString('fa-IR')}</span>` : ''}
        </div>`).join('');
    }

    function closeVersions() {
        versionsModal.classList.add('hidden');
        versionsModal.classList.remove('flex');
        document.body.style.overflow = '';
    }
    versionsModal.querySelectorAll('[data-close-versions]').forEach(el => el.addEventListener('click', closeVersions));

    /* ---------- فیلترها ---------- */
    document.getElementById('search-input').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 300);
    });
    document.getElementById('filter-category').addEventListener('change', () => load(1));
    document.getElementById('filter-status').addEventListener('change', () => load(1));
    document.getElementById('filter-featured').addEventListener('change', () => load(1));

    /* ---------- boot ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load(1);
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
