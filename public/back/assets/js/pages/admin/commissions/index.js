/**
 * کافی‌نت آنلاین — اسکریپت صفحه «قواعد کمیسیون» (فاز ۸)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    const PAGE = App.pageData();
    const rows = document.getElementById('rows');
    const rulesCount = document.getElementById('rules-count');
    const globalForm = document.getElementById('global-form');
    const ruleModal = document.getElementById('rule-modal');
    const ruleForm = document.getElementById('rule-form');

    let editMode = false; // ویرایش قاعدهٔ اختصاصی؟

    /* ---------- قاعدهٔ سراسری ---------- */

    function fillGlobal() {
        const g = PAGE.global;
        if (!g) return;
        setVal('g-platform-type', g.platform_type || 'percent');
        setVal('g-platform-value', g.platform_value ?? '');
        setVal('g-organization-type', g.organization_type || '');
        setVal('g-organization-value', g.organization_value ?? '');
        setVal('g-coffeenet-type', g.coffeenet_type || 'percent');
        setVal('g-coffeenet-value', g.coffeenet_value ?? '');
        updateSum();
    }

    function setVal(id, v) { const el = document.getElementById(id); if (el) el.value = String(v); }
    function getVal(id) { return document.getElementById(id).value.trim(); }

    function updateSum() {
        const box = document.getElementById('g-sum');
        const note = document.getElementById('g-sum-note');
        const p = getVal('g-platform-type') === 'percent' ? parseFloat(getVal('g-platform-value')) || 0 : 0;
        const o = getVal('g-organization-type') === 'percent' ? parseFloat(getVal('g-organization-value')) || 0 : 0;
        const c = getVal('g-coffeenet-type') === 'percent' ? parseFloat(getVal('g-coffeenet-value')) || 0 : 0;
        const sum = p + o + c;

        box.textContent = App.digits(sum) + '٪';
        if (sum === 100) {
            box.className = 'tabular-nums text-emerald-600';
            note.textContent = 'تقسیم کامل ✓';
            note.className = 'text-[11px] text-emerald-500';
        } else if (sum < 100) {
            box.className = 'tabular-nums text-amber-600';
            note.textContent = 'باقیمانده (' + App.digits(100 - sum) + '٪) نزد پلتفرم می‌ماند';
            note.className = 'text-[11px] text-amber-500';
        } else {
            box.className = 'tabular-nums text-rose-500';
            note.textContent = 'بیشتر از ۱۰۰٪ — ذخیره رد می‌شود';
            note.className = 'text-[11px] text-rose-500';
        }
    }

    ['g-platform-type', 'g-platform-value', 'g-organization-type', 'g-organization-value', 'g-coffeenet-type', 'g-coffeenet-value']
        .forEach(id => document.getElementById(id).addEventListener('input', updateSum));

    globalForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('g-save');
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>';

        const body = {
            platform_type: getVal('g-platform-type'),
            platform_value: getVal('g-platform-value'),
            coffeenet_type: getVal('g-coffeenet-type'),
            coffeenet_value: getVal('g-coffeenet-value'),
            organization_type: getVal('g-organization-type') || null,
            organization_value: getVal('g-organization-value') || null,
        };

        try {
            const res = await App.ajax('/admin/commissions/global', { method: 'PUT', body });
            const data = await res.json().catch(() => ({}));
            if (res.ok) App.toast(data.message || 'ذخیره شد.', 'success');
            else App.toast(firstError(data) || 'ذخیره ناموفق بود.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    /* ---------- لیست قواعد اختصاصی ---------- */

    async function loadRules() {
        rows.innerHTML = skeleton();

        const res = await App.ajax('/admin/commissions/data');
        const data = await res.json();
        renderRules(data.data || []);
    }

    function skeleton() {
        let html = '';
        for (let i = 0; i < 3; i++) {
            html += `<tr><td colspan="6" class="!py-4"><div class="space-y-2.5">
                <div class="ui-skeleton h-3 w-full"></div><div class="ui-skeleton h-3 w-40"></div></div></td></tr>`;
        }
        return html;
    }

    function renderRules(list) {
        rulesCount.textContent = App.digits(list.length);

        if (!list.length) {
            rows.innerHTML = '<tr><td colspan="6" class="!py-10"><div class="ui-empty">' +
                '<span class="ui-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>' +
                '<p class="text-sm font-semibold text-stone-500">قاعدهٔ اختصاصی ثبت نشده است.</p>' +
                '<p class="text-xs text-stone-400 mt-1">همهٔ خدمت‌ها با قاعدهٔ سراسری تسویه می‌شوند.</p></div></td></tr>';
            return;
        }

        rows.innerHTML = list.map(r => `
            <tr>
                <td>
                    <p class="font-bold text-stone-800">${esc(r.service_name)}</p>
                    <p class="text-[11px] text-stone-400">اولویت نسبت به قاعدهٔ سراسری</p>
                </td>
                <td class="text-xs text-stone-600">${esc(r.platform)}</td>
                <td class="text-xs text-stone-600">${esc(r.organization || '—')}</td>
                <td class="text-xs text-stone-600">${esc(r.coffeenet)}</td>
                <td>
                    ${r.is_active
                        ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">فعال</span>'
                        : '<span class="badge bg-stone-100 text-stone-500 border border-stone-200">غیرفعال</span>'}
                </td>
                <td class="text-center">
                    <div class="flex items-center justify-center gap-1.5">
                        <button class="act-edit btn-ghost !py-2 !px-3 !text-xs" data-id="${r.id}" title="ویرایش">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                        </button>
                        <button class="act-toggle btn-ghost !py-2 !px-3 !text-xs ${r.is_active ? 'text-amber-600' : 'text-emerald-600'}" data-id="${r.id}" title="${r.is_active ? 'غیرفعال‌کردن' : 'فعال‌سازی'}">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${r.is_active ? '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>' : '<path d="M12 12v10"/><path d="M5.6 5.6a9 9 0 1 0 12.77.04"/>'}</svg>
                        </button>
                        <button class="act-del btn-ghost !py-2 !px-3 !text-xs !text-rose-500" data-id="${r.id}" title="حذف">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        bindRowActions(list);
    }

    function bindRowActions(list) {
        rows.querySelectorAll('.act-edit').forEach(b => b.addEventListener('click', () => {
            const rule = list.find(r => r.id === +b.dataset.id);
            if (rule) openModal(rule);
        }));
        rows.querySelectorAll('.act-toggle').forEach(b => b.addEventListener('click', async () => {
            const res = await App.ajax('/admin/commissions/' + b.dataset.id + '/toggle', { method: 'PATCH' });
            const data = await res.json().catch(() => ({}));
            App.toast(data.message || 'انجام شد.', res.ok ? 'success' : 'error');
            loadRules();
        }));
        rows.querySelectorAll('.act-del').forEach(b => b.addEventListener('click', async () => {
            if (!window.confirm('این قاعدهٔ کمیسیون حذف شود؟ (سفارش‌های قبلی تغییر نمی‌کنند)')) return;
            const res = await App.ajax('/admin/commissions/' + b.dataset.id, { method: 'DELETE' });
            const data = await res.json().catch(() => ({}));
            App.toast(data.message || 'حذف شد.', res.ok ? 'success' : 'error');
            loadRules();
        }));
    }

    /* ---------- مودال قاعدهٔ اختصاصی ---------- */

    document.getElementById('btn-new-rule').addEventListener('click', () => openModal(null));

    function openModal(rule) {
        editMode = !!rule;
        document.getElementById('rule-title').textContent = editMode ? 'ویرایش قاعدهٔ «' + (rule?.service_name || '') + '»' : 'قاعدهٔ کمیسیون اختصاصی';
        document.getElementById('r-id').value = rule?.id || '';
        document.getElementById('r-service').value = rule?.service_id || '';
        document.getElementById('r-service').disabled = editMode;
        document.getElementById('r-platform-type').value = rule?.platform_raw?.type || 'percent';
        document.getElementById('r-platform-value').value = rule?.platform_raw?.value ?? '';
        document.getElementById('r-organization-type').value = rule?.organization_raw?.type || '';
        document.getElementById('r-organization-value').value = rule?.organization_raw?.type ? (rule?.organization_raw?.value ?? '') : '';
        document.getElementById('r-coffeenet-type').value = rule?.coffeenet_raw?.type || 'percent';
        document.getElementById('r-coffeenet-value').value = rule?.coffeenet_raw?.value ?? '';

        ruleModal.classList.remove('hidden');
        ruleModal.classList.add('flex');
    }

    function closeModal() {
        ruleModal.classList.add('hidden');
        ruleModal.classList.remove('flex');
    }

    ruleModal.querySelectorAll('[data-close-modal]').forEach(el => el.addEventListener('click', closeModal));

    ruleForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('r-save');
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>';

        const id = document.getElementById('r-id').value;
        const body = {
            service_id: editMode ? null : document.getElementById('r-service').value,
            platform_type: document.getElementById('r-platform-type').value,
            platform_value: document.getElementById('r-platform-value').value.trim(),
            organization_type: document.getElementById('r-organization-type').value || null,
            organization_value: document.getElementById('r-organization-value').value.trim() || null,
            coffeenet_type: document.getElementById('r-coffeenet-type').value,
            coffeenet_value: document.getElementById('r-coffeenet-value').value.trim(),
        };

        try {
            const res = await App.ajax(editMode ? '/admin/commissions/' + id : '/admin/commissions', {
                method: editMode ? 'PUT' : 'POST',
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                App.toast(data.message || 'ذخیره شد.', 'success');
                closeModal();
                loadRules();
            } else {
                App.toast(firstError(data) || 'ذخیره ناموفق بود.', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    /* ---------- ابزارها ---------- */

    function firstError(data) {
        if (data.message && !data.errors) return data.message;
        if (data.errors) {
            const first = Object.values(data.errors)[0];
            if (Array.isArray(first) && first.length) return first[0];
        }
        return data.message || null;
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    /* ---------- بارگذاری اولیه ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        fillGlobal();
        loadRules();
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
