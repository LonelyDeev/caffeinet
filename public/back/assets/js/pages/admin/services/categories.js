/**
 * کافی‌نت آنلاین — اسکریپت صفحه «دسته‌بندی خدمات»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 */
(function () {
    const ICONS = ['🚗','⚖️','🧾','🎓','🪪','🔎','🏥','🏠','✈️','💼','📱','💳','🖨️','🌐','📊','🔧','📜','🛡️','📮','🗂️','🔤','🧮','🪙','🎁'];
    let tree = [], expanded = new Set(), search = '', deleteId = null, editNameMap = [];

    const treeEl = document.getElementById('tree');
    const modal = document.getElementById('modal');
    const form = document.getElementById('modal-form');
    const deleteModal = document.getElementById('delete-modal');

    /* ---------- بارگذاری درخت ---------- */
    async function load() {
        treeEl.innerHTML = '<p class="text-center py-10 text-stone-400 text-xs">در حال بارگذاری...</p>';
        const res = await App.ajax('/admin/service-categories/data');
        const data = await res.json();
        tree = data.categories || [];
        buildParentOptions();
        render();
    }

    function buildParentOptions() {
        editNameMap = tree.map(c => ({ id: c.id, name: c.name }));
    }

    function matches(node) {
        if (!search) return true;
        if ((node.name || '').includes(search)) return true;
        return (node.children || []).some(c => matches(c));
    }

    function childMatches(node) {
        return (node.children || []).filter(c => matches(c));
    }

    function render() {
        const visible = tree.filter(matches);

        if (!visible.length) {
            treeEl.innerHTML = `<div class="ui-empty">
                <span class="ui-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                </span>
                <p class="text-xs font-bold text-stone-500">دسته‌بندی‌ای یافت نشد</p>
                <p class="text-[11px] text-stone-400 mt-1">اولین دسته را بسازید تا کاتالوگ شکل بگیرد.</p>
            </div>`;
            return;
        }

        treeEl.innerHTML = visible.map(node => renderNode(node, 0)).join('');

        /* اتصال رویدادها */
        treeEl.querySelectorAll('[data-toggle-tree]').forEach(b => b.addEventListener('click', () => {
            const id = +b.dataset.toggleTree;
            expanded.has(id) ? expanded.delete(id) : expanded.add(id);
            render();
        }));
        treeEl.querySelectorAll('[data-add-child]').forEach(b => b.addEventListener('click', () => openModal(null, +b.dataset.addChild, b.dataset.childOf)));
        treeEl.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => {
            const node = findNode(tree, +b.dataset.edit) || {};
            openModal(node, null);
        }));
        treeEl.querySelectorAll('[data-toggle]').forEach(b => b.addEventListener('click', () => doToggle(+b.dataset.toggle)));
        treeEl.querySelectorAll('[data-delete]').forEach(b => b.addEventListener('click', () => askDelete(+b.dataset.delete, b.dataset.deleteName)));
    }

    function findNode(nodes, id) {
        for (const n of nodes) {
            if (n.id === id) return n;
            const found = findNode(n.children || [], id);
            if (found) return found;
        }
        return null;
    }

    function renderNode(node, depth) {
        const hasChildren = (node.children || []).length > 0;
        const isOpen = expanded.has(node.id);
        const children = hasChildren ? childMatches(node) : [];

        const childrenHtml = (hasChildren && isOpen)
            ? `<div class="relative me-9 sm:me-11 mt-1.5 space-y-1.5 before:absolute before:right-4 sm:before:right-5 before:-top-1 before:bottom-1 before:w-px before:bg-stone-200">
                ${children.length ? children.map(c => renderNode(c, depth + 1)).join('')
                    : '<p class="text-[11px] text-stone-400 ps-5 py-2">زیردسته‌ای ندارد</p>'}
               </div>`
            : '';

        return `
        <div class="animate-fade-up">
            <div class="flex items-center gap-2 sm:gap-3 rounded-2xl border px-3 sm:px-4 py-3 transition-all duration-200
                        ${node.is_active ? 'border-stone-200 bg-white hover:border-amber-200 hover:shadow-sm' : 'border-stone-100 bg-stone-50/60 opacity-70'}">
                ${hasChildren ? `
                <button type="button" data-toggle-tree="${node.id}" class="grid place-items-center size-7 rounded-lg text-stone-400 hover:bg-stone-100 hover:text-stone-600 transition-colors shrink-0" aria-label="${isOpen ? 'بستن' : 'باز کردن'} زیردسته‌ها">
                    <svg class="size-4 transition-transform duration-200 ${isOpen ? '-rotate-90' : ''}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>` : '<span class="size-7 shrink-0"></span>'}

                <span class="grid place-items-center size-10 rounded-xl ${node.is_active ? 'bg-amber-50 border border-amber-100' : 'bg-stone-100 border border-stone-200'} text-lg shrink-0">${node.icon || '📁'}</span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-stone-800 truncate">${esc(node.name)}</p>
                    <p class="text-[11px] text-stone-400 truncate">
                        ${node.services_count ? node.services_count.toLocaleString('fa-IR') + ' خدمت' : 'بدون خدمت'}
                        ${node.description ? ' · ' + esc(node.description) : ''}
                    </p>
                </div>

                ${node.is_active
                    ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200 hidden sm:inline-flex">فعال</span>'
                    : '<span class="badge bg-stone-100 text-stone-500 border border-stone-200">غیرفعال</span>'}

                <div class="flex items-center gap-1.5 shrink-0">
                    ${depth === 0 ? `
                    <button type="button" data-add-child="${node.id}" data-child-of="${esc(node.name)}" class="ui-row-btn" title="افزودن زیردسته" aria-label="افزودن زیردسته به ${esc(node.name)}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/></svg>
                    </button>` : ''}
                    <button type="button" data-edit="${node.id}" class="ui-row-btn" title="ویرایش" aria-label="ویرایش ${esc(node.name)}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v16"/><path d="m21.12 8.88-8.24 8.24a2 2 0 0 1-1.42.58H8.5v-3a2 2 0 0 1 .58-1.42l8.24-8.24a2 2 0 0 1 2.82 0l1.98 1.98a2 2 0 0 1 0 2.82Z"/></svg>
                    </button>
                    <button type="button" data-toggle="${node.id}" class="ui-row-btn" title="${node.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی'}" aria-label="${node.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی'} ${esc(node.name)}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9"/><path d="M21 3v9h-9"/></svg>
                    </button>
                    <button type="button" data-delete="${node.id}" data-delete-name="${esc(node.name)}" class="ui-row-btn" data-tone="danger" title="حذف" aria-label="حذف ${esc(node.name)}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>
            ${childrenHtml}
        </div>`;
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    /* ---------- انتخابگر آیکون ---------- */
    const iconPicker = document.getElementById('icon-picker');
    iconPicker.innerHTML = ICONS.map(ic => `
        <button type="button" class="icon-btn grid place-items-center h-10 rounded-xl border border-stone-200 bg-white text-lg hover:border-amber-300 hover:bg-amber-50 transition-colors" data-icon="${ic}" role="radio" aria-checked="false" aria-label="آیکون ${ic}">${ic}</button>
    `).join('');

    function setSelectedIcon(icon) {
        document.getElementById('f-icon').value = icon || '';
        iconPicker.querySelectorAll('.icon-btn').forEach(b => {
            const active = b.dataset.icon === icon;
            b.classList.toggle('border-amber-400', active);
            b.classList.toggle('bg-amber-50', active);
            b.classList.toggle('shadow-inner', active);
            b.setAttribute('aria-checked', active ? 'true' : 'false');
        });
    }
    iconPicker.querySelectorAll('.icon-btn').forEach(b => b.addEventListener('click', () => setSelectedIcon(b.dataset.icon)));

    /* ---------- مودال ---------- */
    function openModal(node = null, parentId = null, parentName = null) {
        form.reset();
        setSelectedIcon(node?.icon || (parentId ? '📄' : '📁'));
        document.getElementById('f-id').value = node?.id || '';
        document.getElementById('f-name').value = node?.name || (parentName ? '' : '');
        document.getElementById('f-desc').value = node?.description || '';
        document.getElementById('f-sort').value = node?.sort ?? 0;
        document.getElementById('f-active').checked = node ? node.is_active : true;

        const parentSelect = document.getElementById('f-parent');
        parentSelect.innerHTML = '<option value="">— دسته اصلی (بدون والد) —</option>'
            + editNameMap.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');

        if (node) {
            parentSelect.value = node.parent_id ?? '';
            document.getElementById('modal-title').textContent = 'ویرایش دسته «' + node.name + '»';
            if (node.parent_id) {
                const parent = findNode(tree, node.parent_id);
                parentSelect.value = parent?.id ?? '';
            }
        } else if (parentId) {
            parentSelect.value = parentId;
            document.getElementById('modal-title').textContent = 'زیردسته جدید برای «' + parentName + '»';
        } else {
            document.getElementById('modal-title').textContent = 'ثبت دسته اصلی جدید';
        }

        form.querySelectorAll('.err').forEach(e => e.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(e => e.classList.remove('field-error'));
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('f-name').focus(), 80);
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    document.getElementById('btn-new').addEventListener('click', () => openModal());
    form.querySelector('.modal-close').addEventListener('click', closeModal);
    modal.querySelector('[data-close]').addEventListener('click', closeModal);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        form.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(el => el.classList.remove('field-error'));

        const id = document.getElementById('f-id').value;
        const payload = {
            name: document.getElementById('f-name').value.trim(),
            parent_id: document.getElementById('f-parent').value || null,
            icon: document.getElementById('f-icon').value || null,
            description: document.getElementById('f-desc').value.trim() || null,
            sort: parseInt(document.getElementById('f-sort').value || '0', 10),
            is_active: document.getElementById('f-active').checked,
        };

        const btn = document.getElementById('modal-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const res = await App.ajax(id ? `/admin/service-categories/${id}` : '/admin/service-categories', {
                method: id ? 'PUT' : 'POST',
                body: payload,
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                closeModal();
                App.toast(data.message || 'ذخیره شد.', 'success');
                load();
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = form.querySelector(`.err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                    const fld = document.getElementById('f-' + k);
                    if (fld) fld.classList.add('field-error');
                });
                App.toast(Object.values(data.errors)[0][0], 'error');
            } else {
                App.toast(data.message || 'خطا در ذخیره‌سازی.', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.textContent = 'ذخیره دسته‌بندی';
        }
    });

    /* ---------- فعال/غیرفعال ---------- */
    async function doToggle(id) {
        try {
            const res = await App.ajax(`/admin/service-categories/${id}/toggle`, { method: 'PATCH' });
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
            const res = await App.ajax(`/admin/service-categories/${deleteId}`, { method: 'DELETE' });
            const data = await res.json().catch(() => ({}));
            closeDelete();
            if (res.ok) { App.toast(data.message, 'success'); load(); }
            else App.toast(data.message || 'حذف ممکن نیست.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'حذف کن';
        }
    });

    /* ---------- جستجو ---------- */
    let searchTimer = null;
    document.getElementById('search-input').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { search = this.value.trim(); render(); }, 250);
    });

    /* ---------- boot ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load();
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
