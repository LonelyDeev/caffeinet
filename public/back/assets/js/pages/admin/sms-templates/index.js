/**
 * کافی‌نت آنلاین — مرکز پیامک (بازطراحی درخواست بازخوردی ۶-۶)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 *
 * گرید کارت‌های رویداد + ویرایش متن/پترن + فعال/غیرفعال سریع +
 * پیش‌نمایش زنده + ارسال آزمایشی + فیلتر دسته/جستجو
 */
(function () {
    const list = document.getElementById('sc-list');
    const modal = document.getElementById('sc-modal');
    const form = document.getElementById('sc-form');

    let templates = [];
    let categories = {};
    let currentCat = 'all';
    let currentQuery = '';

    /* ---------- بارگذاری ---------- */
    async function load() {
        list.innerHTML = '<div class="sc-loading"><div class="sc-spin"></div>در حال بارگذاری…</div>';

        try {
            const res = await App.ajax('/admin/sms-templates/data');
            const data = await res.json();
            templates = data.data || [];
            categories = data.categories || {};
            renderStats();
            render();
        } catch {
            list.innerHTML = '<div class="sc-loading">بارگذاری ناموفق بود — صفحه را رفرش کنید.</div>';
        }
    }

    function renderStats() {
        const counts = {};
        let active = 0, patterns = 0;

        templates.forEach(t => {
            counts[t.category] = (counts[t.category] || 0) + 1;
            if (t.is_active) active++;
            if (t.pattern_code) patterns++;
        });

        document.getElementById('cnt-all').textContent = App.digits(templates.length);
        ['auth', 'order', 'wallet', 'support'].forEach(c => {
            const el = document.getElementById('cnt-' + c);
            if (el) el.textContent = App.digits(counts[c] || 0);
        });

        document.getElementById('stat-active').textContent = App.digits(active) + ' فعال';
        document.getElementById('stat-pattern').textContent = App.digits(patterns) + ' پترن';
    }

    function matches(t) {
        if (currentCat !== 'all' && t.category !== currentCat) return false;
        if (currentQuery) {
            const q = currentQuery.toLowerCase();
            const hay = (t.title + ' ' + t.key + ' ' + (t.body || '')).toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    }

    function render() {
        const visible = templates.filter(matches);

        if (!visible.length) {
            list.innerHTML = '<div class="sc-loading">قالبی مطابق فیلتر فعلی یافت نشد.</div>';
            return;
        }

        list.innerHTML = visible.map(card).join('');
        bindCards();
    }

    function card(t) {
        const vars = extractVars(t.variables);
        const off = t.is_active ? '' : 'is-off';

        return `
        <article class="sc-card ${off}" data-id="${t.id}">
            <div class="sc-card-head">
                <div class="sc-card-title">
                    <b>${escapeHtml(t.title)}</b>
                    <span class="sc-card-key">${escapeHtml(t.key)}</span>
                </div>
                <label class="sc-toggle" title="${t.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی'} این پیامک">
                    <input type="checkbox" class="sc-card-toggle" data-id="${t.id}" ${t.is_active ? 'checked' : ''}>
                    <span class="sc-toggle-track" aria-hidden="true"></span>
                </label>
            </div>

            <div class="sc-card-body ${((t.body || '').length > 190) ? 'is-clamped' : ''}">${escapeHtml(t.body || '—')}</div>

            <div class="sc-vars-mini">
                ${vars.slice(0, 6).map(v => `<span class="sc-var-chip">{${escapeHtml(v)}}</span>`).join('')}
                ${vars.length > 6 ? `<span class="sc-var-chip" style="opacity:.6">+${vars.length - 6}</span>` : ''}
            </div>

            ${t.pattern_code ? `
            <div>
                <span class="sc-pattern-tag" title="کد پترن پرووایدر">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                    ${escapeHtml(t.pattern_code)}
                </span>
            </div>` : ''}

            <div class="sc-card-foot">
                <span class="sc-updated">${t.updated_at ? 'آخرین ویرایش: ' + t.updated_at : '—'}</span>
                <div class="sc-card-actions">
                    <button type="button" class="sc-act-btn sc-edit" data-id="${t.id}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v16"/><path d="m21.12 8.88-8.24 8.24a2 2 0 0 1-1.42.58H8.5v-3a2 2 0 0 1 .58-1.42l8.24-8.24a2 2 0 0 1 2.82 0l1.98 1.98a2 2 0 0 1 0 2.82Z"/></svg>
                        ویرایش
                    </button>
                    <button type="button" class="sc-act-btn sc-act-btn--primary sc-test" data-id="${t.id}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/></svg>
                        تست
                    </button>
                </div>
            </div>
        </article>`;
    }

    function bindCards() {
        list.querySelectorAll('.sc-edit').forEach(b => b.addEventListener('click', () => openEdit(+b.dataset.id)));
        list.querySelectorAll('.sc-test').forEach(b => b.addEventListener('click', () => openTestModal(+b.dataset.id)));
        list.querySelectorAll('.sc-card-toggle').forEach(cb => cb.addEventListener('change', () => askToggle(+cb.dataset.id, cb.checked)));
    }

    /* ---------- استخراج متغیرها از رشتهٔ variables ---------- */
    function extractVars(variablesStr) {
        const matches = (variablesStr || '').match(/\{[a-zA-Z0-9_.]+\}/g) || [];
        return [...new Set(matches.map(m => m.slice(1, -1)))];
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[c]);
    }

    /* ---------- فیلتر دسته + جستجو ---------- */
    document.querySelectorAll('.sc-cat').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.sc-cat').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            currentCat = btn.dataset.cat;
            render();
        });
    });

    let searchTimer = null;
    document.getElementById('sc-search').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentQuery = this.value.trim();
            render();
        }, 300);
    });

    /* ---------- فعال/غیرفعال سریع ---------- */
    async function askToggle(id, next) {
        // optimistic UI: کارت همین حالا سبک شود
        const t = templates.find(x => x.id === id);
        if (t) t.is_active = next;
        renderStats();

        try {
            const res = await App.ajax(`/admin/sms-templates/${id}/toggle`, { method: 'PATCH' });
            const data = await res.json();
            if (res.ok) {
                App.toast(data.message, 'success');
            } else {
                App.toast(data.message || 'خطا در تغییر وضعیت.', 'error');
                if (t) t.is_active = !next;
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
            if (t) t.is_active = !next;
        }
        renderStats();
        render();
    }

    /* ---------- مودال ویرایش ---------- */
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
        document.getElementById('sc-id').value = '';
        form.querySelectorAll('.err').forEach(e => e.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(e => e.classList.remove('field-error'));
    }

    let editingId = null;

    function openEdit(id) {
        const t = templates.find(x => x.id === id);
        if (!t) return;

        editingId = id;
        document.getElementById('sc-id').value = t.id;
        document.getElementById('sc-key-badge').textContent = t.key;
        document.getElementById('sc-cat-badge').textContent = categories[t.category] || t.category || 'سایر';
        document.getElementById('sc-title').value = t.title;
        document.getElementById('sc-pattern').value = t.pattern_code || '';
        document.getElementById('sc-body').value = t.body || '';
        document.getElementById('sc-active').checked = !!t.is_active;

        renderVarChips(t);
        updateLivePreview();
        updateCharCount();

        openModal();
    }

    function renderVarChips(t) {
        const vars = extractVars(t.variables);
        const holder = document.getElementById('sc-vars');

        holder.innerHTML = vars.length
            ? vars.map(v => `<span class="sc-var-chip" data-var="${escapeHtml(v)}" role="button" tabindex="0" title="درج در متن">{${escapeHtml(v)}}</span>`).join('')
            : '<span class="text-[11px] text-stone-400">متغیری تعریف نشده است.</span>';

        holder.querySelectorAll('.sc-var-chip[data-var]').forEach(chip => {
            const insert = () => {
                const body = document.getElementById('sc-body');
                const token = '{' + chip.dataset.var + '}';
                const pos = body.selectionStart ?? body.value.length;
                body.value = body.value.slice(0, pos) + token + body.value.slice(body.selectionEnd ?? pos);
                body.focus();
                body.setSelectionRange(pos + token.length, pos + token.length);
                updateLivePreview();
                updateCharCount();
            };
            chip.addEventListener('click', insert);
            chip.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); insert(); } });
        });
    }

    /* پیش‌نمایش زنده با مقادیر نمونه */
    const SAMPLE_VARS = {
        code: '۱۲۳۴۵', minutes: '۳',
        order_number: 'CN050615-9441', trace: 'TRC-8412',
        accepted_by: 'کافی‌نت «نمونه»', operator: 'مریم احمدی',
        reason: 'نمونهٔ دلیل', amount: '۲۵۰٫۰۰۰', balance: '۱٫۲۵۰٫۰۰۰',
        ticket_number: 'TKT-1024', app_name: 'کافی‌نت آنلاین',
        coffeenet: 'نمونه',
    };

    function updateLivePreview() {
        const body = document.getElementById('sc-body').value;
        const active = document.getElementById('sc-active').checked;
        const out = document.getElementById('sc-live-preview');

        if (!active) {
            out.textContent = 'قالب غیرفعال است — با متن پیش‌فرض امن سیستم ارسال می‌شود.';
            out.style.opacity = '0.6';
            return;
        }

        out.style.opacity = '1';
        let rendered = body.replace(/\{[a-zA-Z0-9_.]+\}/g, m => {
            const name = m.slice(1, -1);
            return SAMPLE_VARS[name] ?? m;
        });
        out.textContent = rendered || '—';
    }

    function updateCharCount() {
        const len = document.getElementById('sc-body').value.length;
        document.getElementById('sc-chars').textContent = App.digits(len) + ' / ۱۰۰۰';
    }

    document.getElementById('sc-body').addEventListener('input', () => { updateLivePreview(); updateCharCount(); });
    document.getElementById('sc-active').addEventListener('change', updateLivePreview);

    form.querySelector('[data-close-sc]').addEventListener('click', closeModal);
    modal.querySelector('[data-close]').addEventListener('click', closeModal);

    /* ذخیره */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!editingId) return;

        form.querySelectorAll('.err').forEach(el => el.classList.add('hidden'));
        form.querySelectorAll('.field').forEach(el => el.classList.remove('field-error'));

        const payload = {
            title: document.getElementById('sc-title').value.trim(),
            body: document.getElementById('sc-body').value.trim(),
            pattern_code: document.getElementById('sc-pattern').value.trim() || null,
            is_active: document.getElementById('sc-active').checked,
        };

        const btn = document.getElementById('sc-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> ذخیره...';

        try {
            const res = await App.ajax(`/admin/sms-templates/${editingId}`, {
                method: 'PUT',
                body: payload,
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                // به‌روزرسانی مدل محلی
                const t = templates.find(x => x.id === editingId);
                if (t) Object.assign(t, data.data || payload);
                App.toast(data.message || 'ذخیره شد.', 'success');
                closeModal();
                renderStats();
                render();
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => {
                    const el = form.querySelector(`.err[data-for="${k}"]`);
                    if (el) { el.textContent = v[0]; el.classList.remove('hidden'); }
                });
                App.toast(Object.values(data.errors)[0][0], 'error');
            } else {
                App.toast(data.message || 'خطا در ذخیره‌سازی.', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.textContent = 'ذخیره قالب';
        }
    });

    /* ---------- تست ارسال (پیش‌نمایش + موبایل) — مودال اختصاصی ---------- */
    const testModal = document.getElementById('sc-test-modal');
    const testForm = document.getElementById('sc-test-form');

    function openTestModal(id) {
        const t = templates.find(x => x.id === id);
        if (!t) return;

        document.getElementById('sc-test-id').value = id;
        document.getElementById('sc-test-title').textContent = 'تست «' + t.title + '»';
        document.getElementById('sc-test-send').checked = false;

        const body = (t.body || '').replace(/\{[a-zA-Z0-9_.]+\}/g, m => SAMPLE_VARS[m.slice(1, -1)] ?? m);
        document.getElementById('sc-test-preview').textContent = t.is_active ? (body || '—') : 'قالب غیرفعال است — برای ارسال آزمایشی فعالش کنید.';

        testModal.classList.remove('hidden');
        testModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('sc-test-mobile').focus(), 60);
    }

    function closeTestModal() {
        testModal.classList.add('hidden');
        testModal.classList.remove('flex');
        document.body.style.overflow = '';
        testForm.reset();
        document.getElementById('sc-test-err').classList.add('hidden');
        document.getElementById('sc-test-mobile').classList.remove('field-error');
    }

    document.querySelectorAll('[data-close-test]').forEach(el => el.addEventListener('click', closeTestModal));

    testForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const id = document.getElementById('sc-test-id').value;
        const mobile = document.getElementById('sc-test-mobile').value.trim();
        const send = document.getElementById('sc-test-send').checked;
        const errEl = document.getElementById('sc-test-err');

        errEl.classList.add('hidden');
        document.getElementById('sc-test-mobile').classList.remove('field-error');

        if (!/^09[0-9]{9}$/.test(mobile)) {
            errEl.textContent = 'فرمت موبایل صحیح نیست (مثل 09123456789).';
            errEl.classList.remove('hidden');
            document.getElementById('sc-test-mobile').classList.add('field-error');
            return;
        }

        const btn = document.getElementById('sc-test-submit');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال تست...';

        try {
            const res = await App.ajax(`/admin/sms-templates/${id}/test`, {
                method: 'POST',
                body: { mobile, send },
            });
            const data = await res.json();

            if (res.ok) {
                App.toast(data.message, 'success');
                if (data.sent) closeTestModal();
            } else {
                App.toast(data.message || 'خطا در ارسال آزمایشی.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'تست';
        }
    });

    /* ---------- شروع ---------- */
    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;
        load();
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
