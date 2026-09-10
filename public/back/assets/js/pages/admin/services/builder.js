/**
 * کافی‌نت آنلاین — اسکریپت صفحه «فرم‌ساز خدمت»
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 * داده‌های سرور از #page-data (data-payload) خوانده می‌شود
 */
(function () {
    /* ================== داده‌های سرور (از #page-data) ================== */
    const PAGE = App.pageData();
    const MODE = PAGE.mode;
    const PAYLOAD = PAGE.payload;
    const FIELD_TYPES = PAGE.field_types;
    const CATEGORY_NAMES = PAGE.category_names || {};

    const FIELD_META = {
        text:          { label: 'متن کوتاه',   icon: '📝', hint: 'یک خط متنی' },
        textarea:      { label: 'متن بلند',     icon: '🗒️', hint: 'توضیح چندخطی' },
        number:        { label: 'عدد',          icon: '🔢', hint: 'ورودی عددی' },
        mobile:        { label: 'موبایل',       icon: '📱', hint: 'شماره همراه' },
        national_code: { label: 'کد ملی',       icon: '🪪', hint: 'کد ملی ۱۰ رقمی' },
        email:         { label: 'ایمیل',        icon: '✉️', hint: 'نشانی ایمیل' },
        date:          { label: 'تاریخ',        icon: '📅', hint: 'انتخاب تاریخ' },
        select:        { label: 'لیست کشویی',   icon: '🔽', hint: 'انتخاب از گزینه‌ها' },
        radio:         { label: 'تک‌انتخابی',   icon: '⭕', hint: 'یک گزینه' },
        checkbox:      { label: 'چندانتخابی',   icon: '☑️', hint: 'چند گزینه' },
        file:          { label: 'فایل',         icon: '📎', hint: 'بارگذاری مدرک' },
    };
    const OPTION_TYPES = ['select', 'radio', 'checkbox'];

    /* ================== وضعیت ================== */
    let uidSeq = 1;
    const state = {
        service: {
            id: PAYLOAD.service.id || null,
            name: PAYLOAD.service.name || '',
            category_id: PAYLOAD.service.category_id || null,
            description: PAYLOAD.service.description || '',
            base_price: Number(PAYLOAD.service.base_price) || 0,
            estimated_time: Number(PAYLOAD.service.estimated_time) || 0,
            requires_upload: !!PAYLOAD.service.requires_upload,
            requires_verification: !!PAYLOAD.service.requires_verification,
            is_active: PAYLOAD.service.is_active !== false,
            is_featured: !!PAYLOAD.service.is_featured,
            sort: Number(PAYLOAD.service.sort) || 0,
            // فاز ۱۵ — رسانه، وضعیت و آلرت
            image_url: PAYLOAD.service.image_url || null,
            availability: PAYLOAD.service.availability || 'active',
            unavailable_note: PAYLOAD.service.unavailable_note || '',
            expires_date: PAYLOAD.service.expires_date || '',
            expires_time: PAYLOAD.service.expires_time || '23:59',
            expired_note: PAYLOAD.service.expired_note || '',
            alert_type: PAYLOAD.service.alert_type || 'none',
            alert_text: PAYLOAD.service.alert_text || '',
            alert_image_url: PAYLOAD.service.alert_image_url || null,
        },
        costs: (PAYLOAD.costs || []).map(c => ({ ...c, amount: Number(c.amount) || 0 })),
        fields: (PAYLOAD.fields || []).map(f => ({ ...f, uid: uidSeq++, options: Array.isArray(f.options) ? [...f.options] : [] })),
        version: PAYLOAD.version || 0,
        dirty: false,
    };

    /* فایل‌های جدید (فاز ۱۵) */
    let imageFile = null;
    let alertImageFile = null;
    let removeImage = false;
    let removeAlertImage = false;
    let expandedUid = null;

    /* ================== عناصر ================== */
    const el = id => document.getElementById(id);
    const costsList = el('costs-list'), costsSummary = el('costs-summary');
    const fieldsList = el('fields-list'), paletteEl = el('palette');
    const previewEl = el('preview');

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fa = n => Number(n || 0).toLocaleString('fa-IR');
    const markDirty = () => { state.dirty = true; };

    /* ================== مقداردهی اولیه ورودی‌های پایه ================== */
    el('s-name').value = state.service.name;
    el('s-category').value = state.service.category_id || '';
    el('s-price').value = state.service.base_price || '';
    el('s-time').value = state.service.estimated_time || '';
    el('s-sort').value = state.service.sort || 0;
    el('s-desc').value = state.service.description || '';
    el('s-active').checked = state.service.is_active;
    el('s-featured').checked = state.service.is_featured;
    el('s-upload').checked = state.service.requires_upload;
    el('s-verify').checked = state.service.requires_verification;

    if (MODE === 'edit') {
        const badge = el('version-badge');
        badge.textContent = 'نسخه ' + fa(state.version);
        badge.classList.remove('hidden');
    }

    /* ================== فاز ۱۵: مقداردهی اولیه بخش ۴ + تصویر ================== */
    el('svb-availability').value = state.service.availability;
    el('svb-unavailable-note').value = state.service.unavailable_note;
    el('svb-expires-date').value = state.service.expires_date;
    el('svb-expires-time').value = state.service.expires_time || '23:59';
    el('svb-expired-note').value = state.service.expired_note;
    el('svb-alert-type').value = state.service.alert_type;
    el('svb-alert-text').value = state.service.alert_text;

    function syncAvailability(value) {
        state.service.availability = value;
        el('svb-availability').value = value;
        document.querySelectorAll('#svb-availability-picks .st-prov-card').forEach(c => {
            const on = c.dataset.avail === value;
            c.classList.toggle('is-selected', on);
            c.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        el('svb-unavailable-note-group').classList.toggle('hidden', value !== 'unavailable');
        updateStateBadge();
    }

    function syncAlertType(value) {
        state.service.alert_type = value;
        el('svb-alert-type').value = value;
        document.querySelectorAll('#svb-alert-picks .st-prov-card').forEach(c => {
            const on = c.dataset.alert === value;
            c.classList.toggle('is-selected', on);
            c.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        el('svb-alert-text-group').classList.toggle('hidden', value !== 'text');
        el('svb-alert-image-group').classList.toggle('hidden', value !== 'image');
    }

    function updateStateBadge() {
        const badge = el('svb-state-badge');
        const d = el('svb-expires-date')?.value.trim();
        const expired = d && jalaliExpired(d, el('svb-expires-time')?.value || '23:59');
        if (state.service.availability === 'unavailable') {
            badge.textContent = 'قطع از سایت اصلی';
            badge.className = 'badge bg-rose-50 text-rose-700 border border-rose-200';
        } else if (expired) {
            badge.textContent = 'مهلت تمام شده';
            badge.className = 'badge bg-amber-50 text-amber-700 border border-amber-200';
        } else {
            badge.textContent = 'فعال و برخط';
            badge.className = 'badge bg-emerald-50 text-emerald-700 border border-emerald-200';
        }
    }

    /** آیا تاریخ شمسی واردشده گذشته؟ (تقریبی — سرور مرجع نهایی است) */
    function jalaliExpired(dateStr, timeStr) {
        try {
            const parts = dateStr.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).split('/').map(Number);
            if (parts.length !== 3 || parts.some(isNaN)) { return false; }
            const [jy, jm, jd] = parts;
            // تبدیل تقریبی جلالی→میلادی (الگوریتم استاندارد)
            const gy = jy + 621;
            const g = jalaliToGregorian(jy, jm, jd);
            const time = /^\d{1,2}:\d{2}$/.test(timeStr || '') ? timeStr : '23:59';
            const deadline = new Date(`${g}T${time}`);
            return deadline.getTime() < Date.now();
        } catch { return false; }
    }

    function jalaliToGregorian(jy, jm, jd) {
        const gDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        const jy2 = jy - 979;
        let gy = jy2 + 621;
        let days = (365 * jy2) + Math.floor(jy2 / 33) * 8 + Math.floor((jy2 % 33 + 3) / 4) + 78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        let gy2 = gy;
        let gd = 0, gm = 0;
        // محاسبه از تعداد روزها
        const leap = gy2 => (gy2 % 4 === 0 && gy2 % 100 !== 0) || (gy2 % 400 === 0);
        let remaining = days;
        gy = gy2;
        let year = gy;
        let dayCount = 0;
        // ساده: از 1970 شروع کنیم
        const ms = (days - 226894) * 86400000; // تعداد روز از 1970 تقریبی
        const dt = new Date(ms);
        if (isNaN(dt.getTime())) { return null; }
        const pad = n => String(n).padStart(2, '0');
        return `${dt.getUTCFullYear()}-${pad(dt.getUTCMonth() + 1)}-${pad(dt.getUTCDate())}`;
    }

    document.querySelectorAll('#svb-availability-picks .st-prov-card').forEach(card => {
        card.addEventListener('click', () => { syncAvailability(card.dataset.avail); markDirty(); });
    });
    document.querySelectorAll('#svb-alert-picks .st-prov-card').forEach(card => {
        card.addEventListener('click', () => { syncAlertType(card.dataset.alert); markDirty(); });
    });
    ['svb-unavailable-note', 'svb-expired-note', 'svb-alert-text', 'svb-expires-date'].forEach(id => {
        el(id)?.addEventListener('input', () => { markDirty(); if (id === 'svb-expires-date') { updateStateBadge(); } });
    });

    /* آپلودرهای تصویر (کشیدن/انتخاب) */
    function bindSvbZone(zoneId, inputId, onFile) {
        const zone = el(zoneId), input = el(inputId);
        if (!zone || !input) { return; }
        zone.addEventListener('click', () => input.click());
        zone.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
        });
        input.addEventListener('change', () => {
            if (input.files && input.files[0]) { onFile(input.files[0]); }
        });
        let depth = 0;
        zone.addEventListener('dragenter', e => {
            if (e.dataTransfer && [...(e.dataTransfer.types || [])].includes('Files')) {
                e.preventDefault(); depth++; zone.classList.add('drag');
            }
        });
        zone.addEventListener('dragover', e => e.preventDefault());
        zone.addEventListener('dragleave', e => {
            e.preventDefault(); depth = Math.max(0, depth - 1);
            if (depth === 0) { zone.classList.remove('drag'); }
        });
        zone.addEventListener('drop', e => {
            e.preventDefault(); depth = 0; zone.classList.remove('drag');
            const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) { onFile(file); }
        });
    }

    bindSvbZone('s-image-zone', 's-image-file', file => {
        if (!file.type.startsWith('image/')) { App.toast('فایل انتخاب‌شده تصویر نیست.', 'error'); return; }
        if (file.size > 2 * 1024 * 1024) { App.toast('حجم تصویر حداکثر ۲ مگابایت است.', 'error'); return; }
        imageFile = file;
        removeImage = false;
        el('s-image-preview-img').src = URL.createObjectURL(file);
        el('s-image-preview').classList.remove('hidden');
        markDirty();
    });

    bindSvbZone('svb-alert-image-zone', 'svb-alert-image-file', file => {
        if (!file.type.startsWith('image/')) { App.toast('فایل انتخاب‌شده تصویر نیست.', 'error'); return; }
        if (file.size > 2 * 1024 * 1024) { App.toast('حجم تصویر حداکثر ۲ مگابایت است.', 'error'); return; }
        alertImageFile = file;
        removeAlertImage = false;
        el('svb-alert-image-preview-img').src = URL.createObjectURL(file);
        el('svb-alert-image-preview').classList.remove('hidden');
        markDirty();
    });

    el('s-image-remove')?.addEventListener('click', e => {
        e.stopPropagation();
        imageFile = null; removeImage = true;
        el('s-image-file').value = '';
        el('s-image-preview').classList.add('hidden');
        markDirty();
    });

    el('svb-alert-image-remove')?.addEventListener('click', e => {
        e.stopPropagation();
        alertImageFile = null; removeAlertImage = true;
        el('svb-alert-image-file').value = '';
        el('svb-alert-image-preview').classList.add('hidden');
        markDirty();
    });

    /* تصاویر موجود (ویرایش) */
    if (state.service.image_url) {
        el('s-image-preview-img').src = state.service.image_url;
        el('s-image-preview').classList.remove('hidden');
    }
    if (state.service.alert_image_url) {
        el('svb-alert-image-preview-img').src = state.service.alert_image_url;
        el('svb-alert-image-preview').classList.remove('hidden');
    }

    syncAvailability(state.service.availability);
    syncAlertType(state.service.alert_type);

    ['s-name', 's-desc'].forEach(id => el(id).addEventListener('input', function () {
        state.service[{ 's-name': 'name', 's-desc': 'description' }[id]] = this.value;
        markDirty(); renderPreview();
    }));
    el('s-category').addEventListener('change', function () {
        state.service.category_id = this.value ? +this.value : null;
        markDirty(); renderPreview();
    });
    ['s-price', 's-time', 's-sort'].forEach(id => el(id).addEventListener('input', function () {
        state.service[{ 's-price': 'base_price', 's-time': 'estimated_time', 's-sort': 'sort' }[id]] = Number(this.value) || 0;
        markDirty(); renderCostsSummary(); renderPreview();
    }));
    ['s-active', 's-featured', 's-upload', 's-verify'].forEach(id => el(id).addEventListener('change', function () {
        state.service[{ 's-active': 'is_active', 's-featured': 'is_featured', 's-upload': 'requires_upload', 's-verify': 'requires_verification' }[id]] = this.checked;
        markDirty(); renderPreview();
    }));

    /* ================== پالت فیلدها ================== */
    paletteEl.innerHTML = FIELD_TYPES.map(t => {
        const m = FIELD_META[t] || { label: t, icon: '❓', hint: '' };
        return `
        <button type="button" draggable="true" data-type="${t}" class="palette-btn group flex flex-col items-center gap-1 rounded-xl border border-stone-200 bg-white px-2 py-2.5 text-stone-600 hover:border-amber-300 hover:bg-amber-50 hover:text-amber-700 hover:shadow-sm hover:shadow-amber-100 active:scale-95 transition-all cursor-grab active:cursor-grabbing" title="${esc(m.hint)}">
            <span class="text-xl leading-none select-none">${m.icon}</span>
            <span class="text-[10px] font-bold leading-tight text-center">${esc(m.label)}</span>
        </button>`;
    }).join('');

    paletteEl.querySelectorAll('.palette-btn').forEach(btn => {
        btn.addEventListener('click', () => insertField(btn.dataset.type, state.fields.length));
        btn.addEventListener('dragstart', e => {
            drag = { kind: 'palette', type: btn.dataset.type };
            e.dataTransfer.effectAllowed = 'copy';
            e.dataTransfer.setData('text/plain', 'palette:' + btn.dataset.type);
        });
        btn.addEventListener('dragend', () => { drag = null; clearIndicators(); });
    });

    /* ================== مدیریت فیلدها ================== */
    function insertField(type, at) {
        const f = {
            uid: uidSeq++,
            field_type: type,
            label: FIELD_META[type]?.label || type,
            name: '',
            placeholder: '',
            help_text: '',
            is_required: false,
            is_active: true,
            options: OPTION_TYPES.includes(type) ? ['گزینه اول', 'گزینه دوم'] : [],
        };
        state.fields.splice(Math.max(0, Math.min(at, state.fields.length)), 0, f);
        expandedUid = f.uid;
        markDirty();
        renderFields(); renderPreview();
        const card = fieldsList.querySelector(`[data-uid="${f.uid}"]`);
        if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function removeField(uid) {
        const i = state.fields.findIndex(f => f.uid === uid);
        if (i === -1) return;
        const card = fieldsList.querySelector(`[data-uid="${uid}"]`);
        if (card) {
            card.style.height = card.offsetHeight + 'px';
            requestAnimationFrame(() => {
                card.classList.add('opacity-0', 'scale-95');
                card.style.height = '0';
                card.style.overflow = 'hidden';
                card.style.margin = '0';
                card.style.border = '0';
                setTimeout(() => {
                    state.fields.splice(i, 1);
                    if (expandedUid === uid) expandedUid = null;
                    renderFields(); renderPreview(); markDirty();
                }, 180);
            });
        } else {
            state.fields.splice(i, 1);
            renderFields(); renderPreview(); markDirty();
        }
    }

    function duplicateField(uid) {
        const i = state.fields.findIndex(f => f.uid === uid);
        if (i === -1) return;
        const copy = { ...state.fields[i], uid: uidSeq++, options: [...(state.fields[i].options || [])], name: state.fields[i].name };
        state.fields.splice(i + 1, 0, copy);
        expandedUid = copy.uid;
        markDirty(); renderFields(); renderPreview();
    }

    function moveField(uid, dir) {
        const i = state.fields.findIndex(f => f.uid === uid);
        const j = i + dir;
        if (i === -1 || j < 0 || j >= state.fields.length) return;
        [state.fields[i], state.fields[j]] = [state.fields[j], state.fields[i]];
        markDirty(); renderFields(); renderPreview();
    }

    function renderFields() {
        el('fields-count').textContent = fa(state.fields.length) + ' فیلد';

        if (!state.fields.length) {
            fieldsList.innerHTML = `
            <div class="fields-empty rounded-2xl border-2 border-dashed border-stone-200 bg-stone-50/50 py-12 text-center transition-colors">
                <div class="mx-auto grid place-items-center size-14 rounded-2xl bg-white border border-stone-200 text-stone-300 mb-3 shadow-sm">
                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="m9 15 3-3 3 3"/></svg>
                </div>
                <p class="text-xs font-bold text-stone-500">هنوز فیلدی اضافه نشده</p>
                <p class="text-[11px] text-stone-400 mt-1">از پالت بالا یک نوع فیلد انتخاب کنید یا آن را اینجا رها کنید</p>
            </div>`;
            return;
        }

        fieldsList.innerHTML = state.fields.map((f, i) => fieldCardHtml(f, i)).join('');
        bindFieldCards();
    }

    function fieldCardHtml(f, i) {
        const m = FIELD_META[f.field_type] || { label: f.field_type, icon: '❓' };
        const expanded = expandedUid === f.uid;
        const hasOptions = OPTION_TYPES.includes(f.field_type);
        const canCheck = canFieldPass(f);

        return `
        <div class="field-card animate-fade-up rounded-2xl border ${f.is_active ? 'border-stone-200 bg-white' : 'border-stone-100 bg-stone-50/80'} overflow-hidden transition-shadow ${expanded ? 'shadow-md shadow-stone-200/60' : 'hover:shadow-sm'}" data-uid="${f.uid}" data-index="${i}">
            <div class="flex items-center gap-2 px-3 py-2.5 select-none">
                <span class="drag-handle grid place-items-center size-8 rounded-lg text-stone-300 hover:text-amber-500 hover:bg-amber-50 cursor-grab active:cursor-grabbing transition-colors shrink-0" title="جابجایی با درگ" aria-label="جابجایی فیلد ${esc(f.label)}">
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>
                </span>
                <span class="grid place-items-center size-9 rounded-xl bg-stone-50 border border-stone-100 text-base shrink-0">${m.icon}</span>
                <button type="button" class="field-toggle flex-1 min-w-0 text-right bg-transparent border-0 p-0" title="${expanded ? 'بستن تنظیمات' : 'باز کردن تنظیمات'}">
                    <p class="text-[13px] font-bold text-stone-700 truncate">
                        ${esc(f.label)}
                        ${f.is_required ? '<span class="text-rose-500">*</span>' : ''}
                    </p>
                    <p class="text-[10px] text-stone-400 truncate">${esc(m.label)}${f.name ? ' · <span class="font-mono" dir="ltr">' + esc(f.name) + '</span>' : ''}${hasOptions ? ' · ' + fa((f.options || []).length) + ' گزینه' : ''}</p>
                </button>
                ${f.is_active ? '' : '<span class="badge bg-stone-100 text-stone-400 border border-stone-200 !text-[9px]">غیرفعال</span>'}
                ${canCheck ? '' : '<span class="badge bg-rose-50 text-rose-500 border border-rose-100 !text-[9px]" title="این فیلد هنوز کامل نیست">ناقص</span>'}
                <span class="badge bg-stone-50 text-stone-400 border border-stone-100 !text-[9px] font-mono shrink-0">${fa(i + 1)}</span>
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" class="field-up grid place-items-center size-7 rounded-lg text-stone-300 hover:text-stone-600 hover:bg-stone-100 transition-colors" title="انتقال به بالا" aria-label="انتقال فیلد به بالا">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
                    </button>
                    <button type="button" class="field-down grid place-items-center size-7 rounded-lg text-stone-300 hover:text-stone-600 hover:bg-stone-100 transition-colors" title="انتقال به پایین" aria-label="انتقال فیلد به پایین">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <button type="button" class="field-dup grid place-items-center size-7 rounded-lg text-stone-300 hover:text-sky-600 hover:bg-sky-50 transition-colors" title="کپی فیلد" aria-label="کپی فیلد ${esc(f.label)}">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="8" height="8" x="8" y="8" rx="1"/><path d="M4 16V6a2 2 0 0 1 2-2h10"/><path d="M16 4a2 2 0 0 1 2 2v10"/></svg>
                    </button>
                    <button type="button" class="field-del grid place-items-center size-7 rounded-lg text-stone-300 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="حذف فیلد" aria-label="حذف فیلد ${esc(f.label)}">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>

            <div class="field-body ${expanded ? '' : 'hidden'} border-t border-stone-100 px-4 py-4 bg-stone-50/40 space-y-3">
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="lbl !text-[11px]">برچسب فیلد <span class="text-rose-500">*</span> <span class="font-normal text-stone-400">(متن نمایشی به مشتری)</span></label>
                        <input class="field !py-2 text-[13px] fld-label" value="${esc(f.label)}" maxlength="150" placeholder="مثلاً شماره پیگیری درخواست">
                        <p class="err text-[11px] text-rose-500 mt-1 hidden" data-uid="${f.uid}" data-for="label"></p>
                    </div>
                    <div>
                        <label class="lbl !text-[11px]">نام فنی <span class="font-normal text-stone-400">(اختیاری — خالی = خودکار)</span></label>
                        <input class="field !py-2 text-[13px] font-mono fld-name" dir="ltr" value="${esc(f.name)}" maxlength="60" placeholder="auto">
                    </div>
                    <div>
                        <label class="lbl !text-[11px]">متن راهنما (placeholder)</label>
                        <input class="field !py-2 text-[13px] fld-placeholder" value="${esc(f.placeholder)}" maxlength="200" placeholder="داخل کادر نمایش داده می‌شود">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="lbl !text-[11px]">توضیح کمکی زیر فیلد</label>
                        <input class="field !py-2 text-[13px] fld-help" value="${esc(f.help_text)}" maxlength="300" placeholder="مثلاً کد ۱۰ رقمی پشت کارت">
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <label class="switch-chip !text-[11px] !py-1.5">
                        <input type="checkbox" class="size-3.5 accent-rose-500 fld-required" ${f.is_required ? 'checked' : ''}>
                        <span>پر کردن الزامی</span>
                    </label>
                    <label class="switch-chip !text-[11px] !py-1.5">
                        <input type="checkbox" class="size-3.5 accent-amber-600 fld-active" ${f.is_active ? 'checked' : ''}>
                        <span>نمایش در فرم</span>
                    </label>
                </div>

                ${hasOptions ? `
                <div class="rounded-xl border border-stone-200 bg-white p-3 space-y-2">
                    <p class="lbl !text-[11px]">گزینه‌ها <span class="text-rose-500">*</span> <span class="font-normal text-stone-400">(حداقل ۲ گزینه)</span></p>
                    <div class="fld-options space-y-1.5">
                        ${(f.options || []).map((opt, oi) => `
                        <div class="flex gap-1.5 items-center">
                            <span class="grid place-items-center size-7 rounded-lg bg-stone-50 border border-stone-100 text-[10px] text-stone-400 font-mono shrink-0">${fa(oi + 1)}</span>
                            <input class="field !py-1.5 !text-xs opt-input" value="${esc(opt)}" maxlength="150" placeholder="متن گزینه">
                            <button type="button" class="opt-del grid place-items-center size-7 rounded-lg text-stone-300 hover:text-rose-500 hover:bg-rose-50 transition-colors shrink-0" title="حذف گزینه" aria-label="حذف گزینه ${oi + 1}">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </div>`).join('')}
                    </div>
                    <button type="button" class="opt-add btn-ghost !py-1.5 !px-3 !text-[11px]">
                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        گزینه جدید
                    </button>
                    <p class="err text-[11px] text-rose-500 hidden" data-uid="${f.uid}" data-for="options"></p>
                </div>` : ''}
            </div>
        </div>`;
    }

    function canFieldPass(f) {
        if (OPTION_TYPES.includes(f.field_type)) return (f.options || []).filter(o => String(o).trim()).length >= 2;
        return true;
    }

    function findField(uid) { return state.fields.find(f => f.uid === uid); }

    function bindFieldCards() {
        fieldsList.querySelectorAll('.field-card').forEach(card => {
            const uid = +card.dataset.uid;

            card.querySelector('.field-toggle').addEventListener('click', () => {
                expandedUid = expandedUid === uid ? null : uid;
                renderFields();
            });
            card.querySelector('.field-up').addEventListener('click', () => moveField(uid, -1));
            card.querySelector('.field-down').addEventListener('click', () => moveField(uid, 1));
            card.querySelector('.field-dup').addEventListener('click', () => duplicateField(uid));
            card.querySelector('.field-del').addEventListener('click', () => removeField(uid));

            /* درگ فقط از طریق دستگیره */
            const handle = card.querySelector('.drag-handle');
            handle.addEventListener('mousedown', () => card.draggable = true);
            handle.addEventListener('touchstart', () => card.draggable = false, { passive: true });
            card.addEventListener('dragstart', e => {
                if (!card.draggable) { e.preventDefault(); return; }
                drag = { kind: 'move', uid };
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', 'move:' + uid);
            });
            card.addEventListener('dragend', () => {
                card.draggable = false;
                card.classList.remove('dragging');
                clearIndicators();
                drag = null;
            });

            /* ورودی‌های تنظیمات */
            const bindInput = (sel, key, after) => {
                const input = card.querySelector(sel);
                if (!input) return;
                input.addEventListener('input', function () {
                    const f = findField(uid);
                    if (!f) return;
                    f[key] = this.value;
                    markDirty();
                    (after || renderPreview)();
                });
            };
            bindInput('.fld-label', 'label', () => { renderPreview(); updateCardHeader(card, uid); });
            bindInput('.fld-name', 'name', () => updateCardHeader(card, uid));
            bindInput('.fld-placeholder', 'placeholder');
            bindInput('.fld-help', 'help_text');

            card.querySelector('.fld-required')?.addEventListener('change', function () {
                const f = findField(uid); if (!f) return;
                f.is_required = this.checked; markDirty(); updateCardHeader(card, uid); renderPreview();
            });
            card.querySelector('.fld-active')?.addEventListener('change', function () {
                const f = findField(uid); if (!f) return;
                f.is_active = this.checked; markDirty();
                card.classList.toggle('border-stone-200', f.is_active);
                card.classList.toggle('bg-white', f.is_active);
                card.classList.toggle('border-stone-100', !f.is_active);
                card.classList.toggle('bg-stone-50/80', !f.is_active);
                renderPreview();
            });

            /* گزینه‌ها */
            const optionsWrap = card.querySelector('.fld-options');
            if (optionsWrap) {
                optionsWrap.querySelectorAll('.opt-input').forEach((input, oi) => {
                    input.addEventListener('input', function () {
                        const f = findField(uid); if (!f) return;
                        f.options[oi] = this.value;
                        markDirty(); renderPreview();
                    });
                });
                optionsWrap.querySelectorAll('.opt-del').forEach((btn, oi) => {
                    btn.addEventListener('click', () => {
                        const f = findField(uid); if (!f) return;
                        f.options.splice(oi, 1);
                        markDirty(); renderFields(); renderPreview();
                    });
                });
            }
            card.querySelector('.opt-add')?.addEventListener('click', () => {
                const f = findField(uid); if (!f) return;
                f.options.push('');
                expandedUid = uid;
                markDirty(); renderFields();
                const inputs = fieldsList.querySelector(`[data-uid="${uid}"] .fld-options`)?.querySelectorAll('.opt-input');
                inputs?.[inputs.length - 1]?.focus();
            });
        });
    }

    function updateCardHeader(card, uid) {
        const f = findField(uid); if (!f) return;
        const m = FIELD_META[f.field_type] || { label: f.field_type, icon: '❓' };
        const p = card.querySelector('.field-toggle p');
        if (p) p.innerHTML = `${esc(f.label)}${f.is_required ? '<span class="text-rose-500">*</span>' : ''}`;
        const sub = card.querySelector('.field-toggle p:last-child');
        if (sub) sub.innerHTML = `${esc(m.label)}${f.name ? ' · <span class="font-mono" dir="ltr">' + esc(f.name) + '</span>' : ''}${OPTION_TYPES.includes(f.field_type) ? ' · ' + fa((f.options || []).length) + ' گزینه' : ''}`;
        const badge = card.querySelector('.badge.bg-rose-50');
        if (badge) badge.classList.toggle('hidden', canFieldPass(f));
    }

    /* ================== درگ‌انددراپ ================== */
    let drag = null, lastDrop = null;

    function clearIndicators() {
        fieldsList.querySelectorAll('.drop-before, .drop-after').forEach(c => c.classList.remove('drop-before', 'drop-after'));
        fieldsList.querySelector('.fields-empty')?.classList.remove('drop-hover');
        lastDrop = null;
    }

    fieldsList.addEventListener('dragover', e => {
        if (!drag) return;
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = drag.kind === 'palette' ? 'copy' : 'move';
        clearIndicators();
        const card = e.target.closest('.field-card');
        if (card) {
            const r = card.getBoundingClientRect();
            const before = e.clientY < r.top + r.height / 2;
            card.classList.add(before ? 'drop-before' : 'drop-after');
            lastDrop = { uid: +card.dataset.uid, before };
        } else {
            fieldsList.querySelector('.fields-empty')?.classList.add('drop-hover');
            lastDrop = null;
        }
    });

    fieldsList.addEventListener('dragleave', e => {
        if (!fieldsList.contains(e.relatedTarget)) clearIndicators();
    });

    fieldsList.addEventListener('drop', e => {
        if (!drag) return;
        e.preventDefault();
        let insertAt = state.fields.length;
        if (lastDrop) {
            const idx = state.fields.findIndex(f => f.uid === lastDrop.uid);
            if (idx > -1) insertAt = lastDrop.before ? idx : idx + 1;
        }

        if (drag.kind === 'palette') {
            insertField(drag.type, insertAt);
        } else if (drag.kind === 'move') {
            const from = state.fields.findIndex(f => f.uid === drag.uid);
            if (from > -1) {
                const [item] = state.fields.splice(from, 1);
                if (from < insertAt) insertAt--;
                state.fields.splice(Math.max(0, insertAt), 0, item);
                renderFields(); renderPreview(); markDirty();
            }
        }
        clearIndicators();
        drag = null;
    });

    /* ================== هزینه‌ها ================== */
    el('btn-add-cost').addEventListener('click', () => {
        state.costs.push({ type: 'expense', title: '', amount: 0, is_commission: false, note: '' });
        markDirty(); renderCosts();
        const rows = costsList.querySelectorAll('.cost-row');
        rows[rows.length - 1]?.querySelector('.cost-title')?.focus();
    });

    function renderCosts() {
        if (!state.costs.length) {
            costsList.innerHTML = `
            <div class="rounded-2xl border-2 border-dashed border-stone-200 bg-stone-50/50 py-8 text-center">
                <p class="text-xs font-bold text-stone-500">هیچ ردیف هزینه‌ای ثبت نشده</p>
                <p class="text-[11px] text-stone-400 mt-1">اگر خدمت فقط قیمت پایه دارد، همین حالت درست است.</p>
            </div>`;
        } else {
            costsList.innerHTML = state.costs.map((c, i) => `
            <div class="cost-row rounded-2xl border border-stone-200 bg-white px-3.5 py-3 space-y-2.5 transition-all ${c.is_commission ? '!border-amber-200 !bg-amber-50/40' : ''}" data-index="${i}">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="grid place-items-center size-7 rounded-lg bg-stone-50 border border-stone-100 text-[10px] font-mono text-stone-400 shrink-0">${fa(i + 1)}</span>
                    <select class="cost-type field !py-1.5 !text-xs !w-auto" title="نوع ردیف">
                        <option value="expense" ${c.type === 'expense' ? 'selected' : ''}>هزینه</option>
                        <option value="fee" ${c.type === 'fee' ? 'selected' : ''}>کارمزد</option>
                    </select>
                    <input class="cost-title field !py-1.5 !text-[13px] flex-1 min-w-36" value="${esc(c.title)}" maxlength="150" placeholder="عنوان ردیف — مثلاً هزینه پیک یا کارمزد سامانه">
                    <button type="button" class="cost-del grid place-items-center size-8 rounded-lg text-stone-300 hover:text-rose-600 hover:bg-rose-50 transition-colors shrink-0" title="حذف ردیف" aria-label="حذف ردیف هزینه ${fa(i + 1)}">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                    <p class="err text-[11px] text-rose-500 w-full hidden" data-cost-for="title"></p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="flex items-center gap-1.5">
                        <label class="text-[10px] font-bold text-stone-400 shrink-0">مبلغ (تومان)</label>
                        <input class="cost-amount field !py-1.5 !text-xs w-32 font-mono" dir="ltr" type="number" min="0" step="any" value="${c.amount || ''}" placeholder="0">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer select-none rounded-xl px-2.5 py-1.5 border transition-all ${c.is_commission ? 'border-amber-300 bg-amber-50' : 'border-stone-200 hover:border-amber-200 hover:bg-amber-50/50'}" title="این ردیف در محاسبه کمیسیون تسویه لحاظ شود">
                        <input type="checkbox" class="cost-commission size-4 accent-amber-600" ${c.is_commission ? 'checked' : ''}>
                        <span class="text-[11px] font-bold ${c.is_commission ? 'text-amber-700' : 'text-stone-500'}">💰 جزو کمیسیون</span>
                    </label>
                    <input class="cost-note field !py-1.5 !text-[11px] flex-1 min-w-32 !text-stone-500" value="${esc(c.note || '')}" maxlength="500" placeholder="یادداشت داخلی (اختیاری)">
                    <p class="err text-[11px] text-rose-500 w-full hidden" data-cost-for="amount"></p>
                </div>
            </div>`).join('');

            costsList.querySelectorAll('.cost-row').forEach((row, i) => {
                row.querySelector('.cost-type').addEventListener('change', function () {
                    state.costs[i].type = this.value; markDirty();
                });
                row.querySelector('.cost-title').addEventListener('input', function () {
                    state.costs[i].title = this.value; markDirty();
                });
                row.querySelector('.cost-amount').addEventListener('input', function () {
                    state.costs[i].amount = Number(this.value) || 0; markDirty(); renderCostsSummary(); renderPreview();
                });
                row.querySelector('.cost-commission').addEventListener('change', function () {
                    state.costs[i].is_commission = this.checked; markDirty();
                    renderCosts(); renderCostsSummary(); renderPreview();
                });
                row.querySelector('.cost-note').addEventListener('input', function () {
                    state.costs[i].note = this.value; markDirty();
                });
                row.querySelector('.cost-del').addEventListener('click', () => {
                    state.costs.splice(i, 1); markDirty(); renderCosts(); renderCostsSummary(); renderPreview();
                });
            });
        }
        renderCostsSummary();
    }

    function renderCostsSummary() {
        const base = Number(state.service.base_price) || 0;
        const total = state.costs.reduce((s, c) => s + (Number(c.amount) || 0), 0);
        const commission = state.costs.filter(c => c.is_commission).reduce((s, c) => s + (Number(c.amount) || 0), 0);
        const flagged = state.costs.filter(c => c.is_commission).length;

        costsSummary.innerHTML = `
        <div class="flex items-center justify-between px-4 py-2.5">
            <span class="text-stone-500">قیمت پایه</span>
            <span class="font-bold text-stone-700">${fa(base)} تومان</span>
        </div>
        <div class="flex items-center justify-between px-4 py-2.5">
            <span class="text-stone-500">جمع ${fa(state.costs.length)} ردیف هزینه</span>
            <span class="font-bold text-stone-700">${fa(total)} تومان</span>
        </div>
        <div class="flex items-center justify-between px-4 py-2.5 bg-emerald-50/60">
            <span class="text-emerald-700 font-bold">مبلغ نهایی مشتری</span>
            <span class="font-extrabold text-emerald-700">${fa(base + total)} تومان</span>
        </div>
        <div class="flex items-center justify-between px-4 py-2.5 ${flagged ? 'bg-amber-50/70' : ''}">
            <span class="${flagged ? 'text-amber-700 font-bold' : 'text-stone-400'}">مشمول کمیسیون (${fa(flagged)} ردیف فلگ‌دار)</span>
            <span class="${flagged ? 'font-extrabold text-amber-700' : 'font-bold text-stone-400'}">${fa(commission)} تومان</span>
        </div>`;
    }

    /* ================== پیش‌نمایش زنده ================== */
    function renderPreview() {
        const s = state.service;
        const active = state.fields.filter(f => f.is_active);
        const total = (Number(s.base_price) || 0) + state.costs.reduce((t, c) => t + (Number(c.amount) || 0), 0);

        previewEl.innerHTML = `
        <div class="space-y-4">
            <div>
                <div class="flex items-center gap-1.5 flex-wrap mb-1.5">
                    ${s.category_id && CATEGORY_NAMES[s.category_id] ? `<span class="text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-2 py-0.5">${esc(CATEGORY_NAMES[s.category_id])}</span>` : ''}
                    ${s.is_featured ? '<span class="text-[10px] font-bold text-amber-600">⭐ ویژه</span>' : ''}
                    ${s.requires_verification ? '<span class="text-[10px] font-bold text-violet-600">🛡️ نیازمند تأیید</span>' : ''}
                </div>
                <h4 class="text-sm font-extrabold text-stone-800 leading-6">${esc(s.name) || '<span class="text-stone-300">نام خدمت…</span>'}</h4>
                ${s.description ? `<p class="text-[11px] text-stone-500 leading-5 mt-1 line-clamp-3">${esc(s.description)}</p>` : ''}
                ${s.estimated_time ? `<p class="text-[10px] text-stone-400 mt-1.5">⏱️ حدود ${fa(s.estimated_time)} دقیقه</p>` : ''}
            </div>

            ${active.length || s.requires_upload ? `
            <div class="space-y-3">
                ${active.map(f => previewField(f)).join('')}
                ${s.requires_upload ? `
                <div class="rounded-2xl border-2 border-dashed border-stone-200 bg-stone-50/50 py-6 text-center">
                    <p class="text-2xl">📎</p>
                    <p class="text-[11px] font-bold text-stone-600 mt-1">بارگذاری مدرک / فایل</p>
                    <p class="text-[10px] text-stone-400 mt-0.5">فایل مرتبط با این خدمت را پیوست کنید</p>
                </div>` : ''}
            </div>` : `<p class="text-[11px] text-stone-300 text-center py-6 rounded-2xl border border-dashed border-stone-200">فرمی تعریف نشده — از بخش ۳ فیلد اضافه کنید</p>`}

            <div class="rounded-2xl border border-stone-200 divide-y divide-stone-100 overflow-hidden bg-white">
                <div class="flex items-center justify-between px-3.5 py-2.5 text-[11px]">
                    <span class="text-stone-500">قیمت پایه</span>
                    <span class="font-bold text-stone-600">${fa(s.base_price)}</span>
                </div>
                ${state.costs.map(c => `
                <div class="flex items-center justify-between px-3.5 py-2 text-[11px]">
                    <span class="text-stone-500 truncate flex items-center gap-1">${esc(c.title) || '<span class="text-stone-300">بدون عنوان</span>'}${c.is_commission ? '<span class="text-[9px] text-amber-600 bg-amber-50 rounded px-1 py-px" title="جزو کمیسیون">کمیسیون</span>' : ''}</span>
                    <span class="font-bold text-stone-600 shrink-0 ms-2">${fa(c.amount)}</span>
                </div>`).join('')}
                <div class="flex items-center justify-between px-3.5 py-2.5 bg-stone-50">
                    <span class="text-[11px] font-bold text-stone-700">مبلغ قابل پرداخت</span>
                    <span class="text-[13px] font-extrabold text-stone-800">${fa(total)} <span class="text-[10px] font-normal">تومان</span></span>
                </div>
            </div>

            <button type="button" class="w-full py-3 rounded-2xl bg-gradient-to-l from-amber-600 to-amber-500 text-white text-sm font-extrabold shadow-lg shadow-amber-200 active:scale-[0.98] transition-transform pointer-events-none">ثبت سفارش</button>
        </div>`;
    }

    function previewField(f) {
        const req = f.is_required ? '<span class="text-rose-500">*</span>' : '';
        let control = '';

        switch (f.field_type) {
            case 'textarea':
                control = `<textarea class="field !py-2 !text-[12px] pointer-events-none" tabindex="-1" readonly rows="2" placeholder="${esc(f.placeholder) || '—'}"></textarea>`;
                break;
            case 'select':
                control = `<select class="field !py-2 !text-[12px] pointer-events-none" tabindex="-1"><option value="">${esc(f.placeholder) || 'انتخاب کنید…'}</option>${(f.options || []).map(o => `<option>${esc(o)}</option>`).join('')}</select>`;
                break;
            case 'radio':
                control = `<div class="space-y-1.5 pt-1">${(f.options || []).map(o => `
                    <label class="flex items-center gap-2.5 rounded-xl border border-stone-200 px-3 py-2 cursor-not-allowed">
                        <span class="grid place-items-center size-4 rounded-full border-2 border-stone-300"></span>
                        <span class="text-[11px] text-stone-600">${esc(o) || '—'}</span>
                    </label>`).join('')}</div>`;
                break;
            case 'checkbox':
                control = `<div class="space-y-1.5 pt-1">${(f.options || []).map(o => `
                    <label class="flex items-center gap-2.5 rounded-xl border border-stone-200 px-3 py-2 cursor-not-allowed">
                        <span class="grid place-items-center size-4 rounded-md border-2 border-stone-300"></span>
                        <span class="text-[11px] text-stone-600">${esc(o) || '—'}</span>
                    </label>`).join('')}</div>`;
                break;
            case 'file':
                control = `<div class="rounded-2xl border-2 border-dashed border-stone-200 bg-stone-50/50 py-4 text-center">
                    <p class="text-lg">📎</p>
                    <p class="text-[10px] text-stone-400 mt-0.5">${esc(f.placeholder) || 'انتخاب فایل'}</p>
                </div>`;
                break;
            case 'mobile':
                control = `<input class="field !py-2 !text-[12px] font-mono pointer-events-none" tabindex="-1" readonly dir="ltr" placeholder="${esc(f.placeholder) || '09xxxxxxxxx'}">`;
                break;
            case 'national_code':
                control = `<input class="field !py-2 !text-[12px] font-mono pointer-events-none" tabindex="-1" readonly dir="ltr" placeholder="${esc(f.placeholder) || 'xxxxxxxxxx'}">`;
                break;
            case 'email':
                control = `<input class="field !py-2 !text-[12px] font-mono pointer-events-none" tabindex="-1" readonly dir="ltr" placeholder="${esc(f.placeholder) || 'name@example.com'}">`;
                break;
            case 'date':
                control = `<div class="relative"><input class="field !py-2 !text-[12px] font-mono pointer-events-none !text-center" tabindex="-1" readonly dir="ltr" placeholder="${esc(f.placeholder) || '۱۴۰۴/۰۱/۰۱'}"><svg class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-stone-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M8 2v4"/><path d="M16 2v4"/><path d="M3 10h18"/></svg></div>`;
                break;
            default:
                control = `<input class="field !py-2 !text-[12px] pointer-events-none" tabindex="-1" readonly placeholder="${esc(f.placeholder) || '—'}">`;
        }

        return `
        <div>
            <p class="text-[11px] font-bold text-stone-600 mb-1.5">${esc(f.label) || '<span class="text-stone-300">بدون برچسب</span>'} ${req}</p>
            ${control}
            ${f.help_text ? `<p class="text-[9px] text-stone-400 mt-1 leading-4">ℹ️ ${esc(f.help_text)}</p>` : ''}
        </div>`;
    }

    /* ================== اعتبارسنجی سمت کلاینت ================== */
    function validateClient() {
        clearAllErrors();
        const problems = [];

        if (!state.service.name.trim()) { showBasicError('name', 'نام خدمت الزامی است.'); problems.push('نام خدمت'); }
        if (!state.service.category_id) { showBasicError('category_id', 'انتخاب دسته‌بندی الزامی است.'); problems.push('دسته‌بندی'); }
        if (isNaN(state.service.base_price) || state.service.base_price < 0) { showBasicError('base_price', 'قیمت پایه معتبر نیست.'); problems.push('قیمت پایه'); }

        state.costs.forEach((c, i) => {
            if (!String(c.title).trim()) { showCostError(i, 'title', 'عنوان ردیف هزینه الزامی است.'); problems.push('هزینه ' + fa(i + 1)); }
            if (isNaN(c.amount) || c.amount < 0) { showCostError(i, 'amount', 'مبلغ معتبر نیست.'); problems.push('مبلغ هزینه ' + fa(i + 1)); }
        });

        state.fields.forEach((f, i) => {
            if (!String(f.label).trim()) { showFieldError(f.uid, 'label', 'برچسب فیلد الزامی است.'); problems.push('فیلد ' + fa(i + 1)); }
            if (OPTION_TYPES.includes(f.field_type) && (f.options || []).filter(o => String(o).trim()).length < 2) {
                showFieldError(f.uid, 'options', 'فیلد گزینه‌ای حداقل ۲ گزینه لازم دارد.');
                problems.push('گزینه‌های «' + (f.label || f.field_type) + '»');
            }
        });

        return problems;
    }

    function clearAllErrors() {
        document.querySelectorAll('.err[data-for]').forEach(e => e.classList.add('hidden'));
        document.querySelectorAll('.field-error').forEach(e => e.classList.remove('field-error'));
    }

    function showBasicError(key, msg) {
        const p = document.querySelector(`.err[data-for="${key}"]`);
        if (p) { p.textContent = msg; p.classList.remove('hidden'); }
        const input = { name: 's-name', category_id: 's-category', base_price: 's-price', estimated_time: 's-time' }[key];
        if (input) el(input).classList.add('field-error');
    }

    function showCostError(i, key, msg) {
        const row = costsList.querySelectorAll('.cost-row')[i];
        if (row) {
            const p = row.querySelector(`.err[data-cost-for="${key}"]`);
            if (p) { p.textContent = msg; p.classList.remove('hidden'); }
            const input = row.querySelector(key === 'title' ? '.cost-title' : '.cost-amount');
            input?.classList.add('field-error');
        }
    }

    function showFieldError(uid, key, msg) {
        expandedUid = uid;
        renderFields();
        const card = fieldsList.querySelector(`[data-uid="${uid}"]`);
        if (card) {
            card.classList.add('field-error');
            const p = card.querySelector(`.err[data-for="${key}"]`);
            if (p) { p.textContent = msg; p.classList.remove('hidden'); }
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    /* ================== ذخیره ================== */
    async function save() {
        const problems = validateClient();
        if (problems.length) {
            App.toast('موارد زیر را اصلاح کنید: ' + problems.slice(0, 3).join('، ') + (problems.length > 3 ? ' و ' + fa(problems.length - 3) + ' مورد دیگر' : ''), 'error');
            return;
        }

        /* فاز ۱۵ — اعتبارسنجی آلرت تصویری */
        if (state.service.alert_type === 'text' && !el('svb-alert-text').value.trim()) {
            App.toast('برای آلرت متنی، متن آلرت الزامی است.', 'error');
            return;
        }
        if (state.service.alert_type === 'image' && !alertImageFile && !state.service.alert_image_url) {
            App.toast('برای آلرت تصویری، انتخاب تصویر الزامی است.', 'error');
            return;
        }

        const service = {
            name: state.service.name.trim(),
            category_id: state.service.category_id,
            description: state.service.description.trim() || null,
            base_price: Number(state.service.base_price) || 0,
            estimated_time: Number(state.service.estimated_time) || 0,
            requires_upload: state.service.requires_upload,
            requires_verification: state.service.requires_verification,
            is_active: state.service.is_active,
            is_featured: state.service.is_featured,
            sort: Number(state.service.sort) || 0,
            // فاز ۱۵
            availability: state.service.availability,
            unavailable_note: el('svb-unavailable-note').value.trim() || null,
            expired_note: el('svb-expired-note').value.trim() || null,
            alert_type: state.service.alert_type === 'none' ? null : state.service.alert_type,
            alert_text: el('svb-alert-text').value.trim() || null,
            remove_image: removeImage ? '1' : '0',
            remove_alert_image: removeAlertImage ? '1' : '0',
        };

        const expiresDate = el('svb-expires-date').value.trim();
        const expiresTime = el('svb-expires-time').value || '23:59';
        if (expiresDate) {
            service.expires_at = `${expiresDate} ${expiresTime}`;
        } else {
            service.expires_at = '';
        }

        const payload = {
            ...service,
            costs: state.costs.map(c => ({
                type: c.type,
                title: String(c.title).trim(),
                amount: Number(c.amount) || 0,
                is_commission: !!c.is_commission,
                note: String(c.note || '').trim() || null,
            })),
            fields: state.fields.map(f => ({
                field_type: f.field_type,
                label: String(f.label).trim(),
                name: String(f.name || '').trim() || null,
                placeholder: String(f.placeholder || '').trim() || null,
                help_text: String(f.help_text || '').trim() || null,
                is_required: !!f.is_required,
                is_active: !!f.is_active,
                options: OPTION_TYPES.includes(f.field_type)
                    ? (f.options || []).map(o => String(o).trim()).filter(Boolean)
                    : null,
            })),
        };

        /* فاز ۱۵ — فرم multipart برای تصاویر */
        const fd = new FormData();
        if (MODE === 'edit') {
            fd.append('_method', 'PUT'); // method spoofing برای multipart
        }
        Object.entries(payload).forEach(([key, value]) => {
            if (value === null || value === undefined) { return; }
            if (typeof value === 'boolean') { fd.append(key, value ? '1' : '0'); return; }
            if (key === 'costs' || key === 'fields') {
                fd.append(key, JSON.stringify(value));
                return;
            }
            fd.append(key, value);
        });
        if (imageFile) { fd.append('image', imageFile); }
        if (alertImageFile) { fd.append('alert_image', alertImageFile); }

        const btn = el('btn-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال ذخیره…';

        try {
            const url = MODE === 'edit' ? '/admin/services/' + state.service.id : '/admin/services';
            const res = await App.ajax(url, { method: 'POST', body: fd });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                state.dirty = false;
                try { sessionStorage.setItem('flash-toast', (data.message || 'ذخیره شد.') + '||success'); } catch (e) {}
                window.location = App.url('/admin/services');
                return;
            }

            if (res.status === 422 && data.errors) {
                applyServerErrors(data.errors);
                App.toast(data.message || 'خطاهای اعتبارسنجی را اصلاح کنید.', 'error');
            } else {
                App.toast(data.message || 'خطا در ذخیره‌سازی. دوباره تلاش کنید.', 'error');
            }
        } catch {
            App.toast('خطای ارتباط با سرور.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = MODE === 'edit' ? 'ذخیره تغییرات' : 'ثبت خدمت';
            const svg = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>';
            btn.insertAdjacentHTML('afterbegin', svg);
        }
    }

    function applyServerErrors(errors) {
        clearAllErrors();
        const first = { field: null, cost: null, basic: null };

        Object.entries(errors).forEach(([key, messages]) => {
            const msg = messages[0];
            let m;
            if ((m = key.match(/^fields\.(\d+)\.(.+)$/))) {
                const idx = +m[1];
                const f = state.fields[idx];
                if (f) showFieldError(f.uid, m[2], msg);
                if (!first.field) first.field = msg;
            } else if ((m = key.match(/^costs\.(\d+)\.(.+)$/))) {
                showCostError(+m[1], m[2], msg);
                if (!first.cost) first.cost = msg;
            } else if ((m = key.match(/^(name|category_id|base_price|description|estimated_time|sort|is_active|is_featured|requires_upload|requires_verification)$/))) {
                showBasicError(m[1], msg);
                if (!first.basic) first.basic = msg;
            }
        });
    }

    el('btn-save').addEventListener('click', save);

    /* هشدار خروج با تغییرات ذخیره‌نشده */
    window.addEventListener('beforeunload', e => {
        if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    /* پرش به پیش‌نمایش در موبایل */
    el('btn-scroll-preview').addEventListener('click', () => {
        el('preview-anchor').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    /* ================== boot ================== */
    function boot() {
        renderCosts();
        renderFields();
        renderPreview();
    }

    if (typeof window.App !== 'undefined') boot();
    else { window.addEventListener('app:ready', boot, { once: true }); setTimeout(boot, 2500); }
})();
