/**
 * کافی‌نت آنلاین — صفحه «اطلاعیه‌های سامانه» (فاز ۱۵)
 * لیست + ایجاد/ویرایش با رسانه (تصویر/ویدیو) + مخاطب + پنجره نمایش
 */
/* global App, PanelUI, jQuery, CNJdp */
(function () {
    'use strict';

    const $ = jQuery;

    /* ---------- هلپرهای محلی ---------- */
    const esc = v => String(v ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const faNum = n => (Number(n) || 0).toLocaleString('fa-IR', { maximumFractionDigits: 0 });

    /* ---------- وضعیت ---------- */
    let rows = [];
    let filter = 'all';
    let editMode = null; // null = ایجاد جدید
    let imageFile = null;
    let videoFile = null;
    let removeMedia = false;

    const el = id => document.getElementById(id);

    /* ---------- آیکون‌ها ---------- */
    const AUD_BADGES = {
        customers: 'bg-amber-50 text-amber-700 border border-amber-200',
        admins: 'bg-rose-50 text-rose-700 border border-rose-200',
        org_managers: 'bg-yellow-50 text-yellow-700 border border-yellow-200',
        coffeenet_managers: 'bg-teal-50 text-teal-700 border border-teal-200',
        operators: 'bg-sky-50 text-sky-700 border border-sky-200',
        all_panels: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        everyone: 'bg-stone-100 text-stone-500 border border-stone-200',
    };

    const MEDIA_LABELS = { none: 'متن', image: 'تصویر', video: 'ویدیو' };

    /* ---------- بارگذاری لیست ---------- */
    async function load() {
        el('an-list').innerHTML = '<div class="an-loading"><span class="size-4 border-2 border-amber-400/40 border-t-amber-500 rounded-full animate-spin inline-block align-middle me-2"></span>در حال بارگذاری…</div>';

        try {
            const res = await App.ajax('/admin/announcements/data');
            const data = await res.json();
            rows = data.data || [];
            render();
        } catch {
            el('an-list').innerHTML = '<div class="an-loading text-rose-500">بارگذاری لیست ناموفق بود — دوباره تلاش کنید.</div>';
        }
    }

    function matchesFilter(r) {
        if (filter === 'all') return true;
        if (filter === 'active') return r.is_active;
        if (filter === 'customers') return ['customers', 'everyone'].includes(r.audience);
        if (filter === 'panels') return !['customers'].includes(r.audience);
        return true;
    }

    /* ---------- رندر ---------- */
    function render() {
        const list = el('an-list');
        const visible = rows.filter(matchesFilter);

        // آمار
        el('stat-total').textContent = faNum(rows.length) + ' اطلاعیه';
        el('stat-active').textContent = faNum(rows.filter(r => r.is_active).length) + ' فعال';
        el('cnt-all').textContent = faNum(rows.length);

        el('an-empty').classList.toggle('hidden', rows.length > 0);

        if (!visible.length) {
            list.innerHTML = rows.length
                ? '<div class="an-loading">موردی با این فیلتر یافت نشد.</div>'
                : '';
            return;
        }

        let html = '';
        visible.forEach((r, i) => { html += cardHtml(r, i); });
        list.innerHTML = html;

        // رویدادها
        list.querySelectorAll('[data-edit]').forEach(btn => {
            btn.addEventListener('click', () => openEdit(+btn.dataset.edit));
        });
        list.querySelectorAll('[data-toggle]').forEach(btn => {
            btn.addEventListener('click', () => toggleActive(+btn.dataset.toggle));
        });
        list.querySelectorAll('[data-del]').forEach(btn => {
            btn.addEventListener('click', () => removeAnnouncement(+btn.dataset.del));
        });
    }

    function cardHtml(r, i) {
        const delay = `animation-delay: ${Math.min(i * 0.05, 0.4)}s`;

        const mediaHtml = r.media_type === 'image' && r.media_url
            ? `<img src="${r.media_url}" alt="رسانه اطلاعیه ${esc(r.title)}">`
            : r.media_type === 'video'
                ? `<span class="an-media-ph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 13 5.2-3.1a.6.6 0 0 1 .8.5v3.2a.6.6 0 0 1-.8.5L16 11"/><rect width="14" height="10" x="2" y="7" rx="2"/></svg></span>`
                : `<span class="an-media-ph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h12"/></svg></span>`;

        const windowMeta = r.starts_at_label || r.ends_at_label
            ? `${r.starts_at_label ? 'از ' + r.starts_at_label : ''}${r.starts_at_label && r.ends_at_label ? ' ' : ''}${r.ends_at_label ? 'تا ' + r.ends_at_label : ''}`
            : 'بدون محدودیت زمانی';

        return `
        <article class="an-card animate-fade-up ${r.is_active ? '' : 'is-dimmed'}" data-media="${r.media_type}" style="${delay}">
            <div class="an-card-media">${mediaHtml}</div>
            <div class="an-card-body">
                <h3 class="an-card-title">${esc(r.title)}</h3>
                ${r.body ? `<p class="an-card-text">${esc(r.body)}</p>` : ''}
                <div class="an-card-badges">
                    <span class="badge ${AUD_BADGES[r.audience] || AUD_BADGES.everyone}">${esc(r.audience_label)}</span>
                    <span class="badge bg-stone-100 text-stone-500 border border-stone-200">${MEDIA_LABELS[r.media_type] || '—'}</span>
                    ${r.is_active
                        ? '<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">فعال</span>'
                        : '<span class="badge bg-stone-100 text-stone-400 border border-stone-200">غیرفعال</span>'}
                </div>
                <p class="an-card-meta">
                    ${windowMeta} · ${esc(r.creator)} · ${r.created_at_label}
                </p>
            </div>
            <div class="an-card-foot">
                <button type="button" class="ui-row-btn" data-edit="${r.id}" title="ویرایش" aria-label="ویرایش اطلاعیه">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                </button>
                <button type="button" class="ui-row-btn" data-toggle="${r.id}" title="${r.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی'}" aria-label="تغییر وضعیت">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/></svg>
                </button>
                <button type="button" class="ui-row-btn" data-del="${r.id}" data-tone="danger" title="حذف" aria-label="حذف اطلاعیه">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
                <span class="an-reads" title="تعداد دیده‌شدن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    ${faNum(r.reads_count)} بازدید
                </span>
            </div>
        </article>`;
    }

    /* ---------- مودال ---------- */
    function openModal() {
        const modal = el('an-modal');
        modal.classList.remove('hidden');
    }

    function closeModal() {
        const modal = el('an-modal');
        modal.classList.add('hidden');
        resetForm();
    }

    function resetForm() {
        editMode = null;
        imageFile = null;
        videoFile = null;
        removeMedia = false;

        el('an-form').reset();
        el('an-id').value = '';
        el('an-modal-title-text').textContent = 'اطلاعیه جدید';
        el('an-save').lastChild.textContent = ' ارسال اطلاعیه';

        // پیش‌فرض‌ها
        setMediaPick('none');
        setAudience('customers');
        setVideoTab('upload');
        el('an-active').checked = true;

        // پیش‌نمایش‌ها مخفی
        el('an-image-preview').classList.add('hidden');
        el('an-video-preview').classList.add('hidden');
        el('an-video-link').classList.add('hidden');
        el('an-video-upload').classList.remove('hidden');
        el('an-video-url').value = '';
        el('an-image-file').value = '';
        el('an-video-file').value = '';
        el('an-start-date').value = '';
        el('an-end-date').value = '';
        el('an-start-time').value = '';
        el('an-end-time').value = '';

        document.querySelectorAll('#an-form .err').forEach(p => p.classList.add('hidden'));
    }

    async function openEdit(id) {
        try {
            const res = await App.ajax(`/admin/announcements/${id}`);
            const { data } = await res.json();

            resetForm();
            editMode = data;

            el('an-id').value = data.id;
            el('an-modal-title-text').textContent = 'ویرایش اطلاعیه';
            el('an-save').lastChild.textContent = ' ذخیره تغییرات';
            el('an-title').value = data.title || '';
            el('an-body').value = data.body || '';
            el('an-active').checked = !!data.is_active;

            // مخاطب
            setAudience(data.audience || 'customers');

            // رسانه
            setMediaPick(data.media_type || 'none');
            if (data.media_type === 'image' && data.media_url) {
                el('an-image-preview-img').src = data.media_url;
                el('an-image-preview').classList.remove('hidden');
            }
            if (data.media_type === 'video') {
                if (data.is_video_upload && data.media_url) {
                    el('an-video-preview-el').src = data.media_url;
                    el('an-video-preview').classList.remove('hidden');
                } else if (data.video_url) {
                    setVideoTab('link');
                    el('an-video-url').value = data.video_url;
                }
            }

            // پنجره نمایش (ISO → شمسی از سرور نمی‌آید؛ فرمت خام)
            if (data.starts_at) fillDate('an-start-date', 'an-start-time', data.starts_at);
            if (data.ends_at) fillDate('an-end-date', 'an-end-time', data.ends_at);

            openModal();
        } catch {
            App.toast('دریافت اطلاعات اطلاعیه ناموفق بود.', 'error');
        }
    }

    /** ISO (Y-m-dTH:i) → تاریخ شمسی با CNJdp (اگر موجود) وگرنه خام */
    function fillDate(dateId, timeId, iso) {
        const m = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/.exec(iso || '');
        if (!m) return;

        el(timeId).value = m[2];

        if (window.CNJdp && typeof window.CNJdp.toJalaali === 'function'
            && typeof window.CNJdp.formatJalali === 'function') {
            try {
                const [gy, gm, gd] = m[1].split('-').map(Number);
                const j = window.CNJdp.toJalaali(gy, gm, gd);
                el(dateId).value = window.CNJdp.formatJalali(j);
                return;
            } catch { /* noop */ }
        }
        el(dateId).value = m[1];
    }

    /* ---------- انتخاب‌ها ---------- */
    function setMediaPick(value) {
        document.querySelectorAll('#an-media-picks .st-prov-card').forEach(c => {
            const on = c.dataset.media === value;
            c.classList.toggle('is-selected', on);
            c.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        el('an-media-type').value = value;

        el('an-image-group').classList.toggle('hidden', value !== 'image');
        el('an-video-group').classList.toggle('hidden', value !== 'video');
    }

    function setAudience(value) {
        document.querySelectorAll('#an-audience-picks .st-prov-card').forEach(c => {
            const on = c.dataset.audience === value;
            c.classList.toggle('is-selected', on);
            c.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        el('an-audience').value = value;
    }

    function setVideoTab(tab) {
        document.querySelectorAll('.an-vtab').forEach(t => {
            const on = t.dataset.vtab === tab;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        el('an-video-upload').classList.toggle('hidden', tab !== 'upload');
        el('an-video-link').classList.toggle('hidden', tab !== 'link');
    }

    /* ---------- آپلود تصویر ---------- */
    function bindUploadZone(zoneId, inputId, onFile) {
        const zone = el(zoneId);
        const input = el(inputId);
        if (!zone || !input) return;

        zone.addEventListener('click', () => input.click());
        zone.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
        });

        input.addEventListener('change', () => {
            if (input.files && input.files[0]) onFile(input.files[0]);
        });

        // درگ‌انددراپ
        let depth = 0;
        zone.addEventListener('dragenter', e => {
            if (e.dataTransfer && [...(e.dataTransfer.types || [])].includes('Files')) {
                e.preventDefault(); depth++; zone.classList.add('drag');
            }
        });
        zone.addEventListener('dragover', e => e.preventDefault());
        zone.addEventListener('dragleave', e => {
            e.preventDefault(); depth = Math.max(0, depth - 1);
            if (depth === 0) zone.classList.remove('drag');
        });
        zone.addEventListener('drop', e => {
            e.preventDefault(); depth = 0; zone.classList.remove('drag');
            const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) onFile(file);
        });
    }

    bindUploadZone('an-image-zone', 'an-image-file', file => {
        if (!file.type.startsWith('image/')) {
            App.toast('فایل انتخاب‌شده تصویر نیست.', 'error');
            return;
        }
        imageFile = file;
        el('an-image-preview-img').src = URL.createObjectURL(file);
        el('an-image-preview').classList.remove('hidden');
    });

    bindUploadZone('an-video-upload', 'an-video-file', file => {
        if (!file.type.startsWith('video/')) {
            App.toast('فایل انتخاب‌شده ویدیو نیست.', 'error');
            return;
        }
        videoFile = file;
        el('an-video-preview-el').src = URL.createObjectURL(file);
        el('an-video-preview').classList.remove('hidden');
    });

    el('an-image-remove')?.addEventListener('click', e => {
        e.stopPropagation();
        imageFile = null;
        removeMedia = true;
        el('an-image-file').value = '';
        el('an-image-preview').classList.add('hidden');
    });

    el('an-video-remove')?.addEventListener('click', e => {
        e.stopPropagation();
        videoFile = null;
        removeMedia = true;
        el('an-video-file').value = '';
        el('an-video-preview').classList.add('hidden');
    });

    /* ---------- ذخیره ---------- */
    el('an-form').addEventListener('submit', async e => {
        e.preventDefault();

        document.querySelectorAll('#an-form .err').forEach(p => p.classList.add('hidden'));

        const title = el('an-title').value.trim();
        const mediaType = el('an-media-type').value;
        const videoUrl = el('an-video-url').value.trim();

        // اعتبارسنجی محلی
        if (!title) { showErr('title', 'عنوان اطلاعیه الزامی است.'); return; }
        if (mediaType === 'none' && !el('an-body').value.trim()) {
            showErr('body', 'برای اطلاعیه متنی، متن الزامی است (یا تصویر/ویدیو انتخاب کنید).');
            return;
        }
        if (mediaType === 'image' && !imageFile && !el('an-image-preview').classList.contains('hidden') === false && !editMode?.media_url) {
            // بدون فایل جدید و بدون تصویر قبلی
            showErr('media_file', 'انتخاب تصویر الزامی است.');
            return;
        }
        if (mediaType === 'video' && !videoFile && !videoUrl && !editMode?.media_url) {
            showErr('video_url', 'فایل ویدیو آپلود کنید یا لینک مستقیم وارد کنید.');
            return;
        }

        const fd = new FormData();
        if (editMode) fd.append('_method', 'PUT');
        fd.append('title', title);
        fd.append('body', el('an-body').value.trim());
        fd.append('audience', el('an-audience').value);
        fd.append('is_active', el('an-active').checked ? '1' : '0');
        fd.append('media_type', mediaType);
        fd.append('remove_media', removeMedia ? '1' : '0');

        if (mediaType === 'video' && videoUrl) fd.append('video_url', videoUrl);
        if (imageFile) fd.append('media_file', imageFile);
        if (videoFile) fd.append('media_file', videoFile);

        // پنجره نمایش: تاریخ شمسی + ساعت → «JALALI TIME»
        const startDate = el('an-start-date').value.trim();
        const startTime = el('an-start-time').value || '00:00';
        const endDate = el('an-end-date').value.trim();
        const endTime = el('an-end-time').value || '23:59';
        if (startDate) fd.append('starts_at', `${startDate} ${startTime}`);
        if (endDate) fd.append('ends_at', `${endDate} ${endTime}`);

        // دکمه loading
        const btn = el('an-save');
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> در حال ارسال…';

        try {
            const url = editMode ? `/admin/announcements/${editMode.id}` : '/admin/announcements';
            const res = await App.ajax(url, { method: 'POST', body: fd });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                App.toast(data.message || 'اطلاعیه ذخیره شد.', 'success');
                closeModal();
                load();
            } else {
                applyServerErrors(data.errors || {});
                App.toast(data.message || 'خطا در ذخیره‌سازی.', 'error');
            }
        } catch {
            App.toast('ارتباط با سرور برقرار نشد.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    function showErr(field, message) {
        const p = document.querySelector(`#an-form .err[data-for="${field}"]`);
        if (p) { p.textContent = message; p.classList.remove('hidden'); }
        App.toast(message, 'error');
    }

    function applyServerErrors(errors) {
        Object.entries(errors || {}).forEach(([field, list]) => {
            showErr(field, Array.isArray(list) ? list[0] : String(list));
        });
    }

    /* ---------- عملیات ردیف ---------- */
    async function toggleActive(id) {
        try {
            const res = await App.ajax(`/admin/announcements/${id}/toggle`, { method: 'PATCH' });
            const data = await res.json();
            App.toast(data.message || 'وضعیت تغییر کرد.', res.ok ? 'success' : 'error');
            if (res.ok) load();
        } catch {
            App.toast('عملیات ناموفق بود.', 'error');
        }
    }

    function removeAnnouncement(id) {
        const row = rows.find(r => r.id === id);
        PanelUI.confirm({
            title: 'حذف اطلاعیه',
            desc: `اطلاعیه «${row?.title || ''}» برای همیشه حذف می‌شود. مطمئن هستید؟`,
            okText: 'حذف',
            danger: true,
        }, async () => {
            try {
                const res = await App.ajax(`/admin/announcements/${id}`, { method: 'DELETE' });
                const data = await res.json();
                App.toast(data.message || 'حذف شد.', res.ok ? 'success' : 'error');
                if (res.ok) load();
            } catch {
                App.toast('حذف ناموفق بود.', 'error');
            }
        });
    }

    /* ---------- رویدادهای عمومی ---------- */
    el('btn-new-announcement').addEventListener('click', () => {
        resetForm();
        openModal();
    });

    document.querySelectorAll('[data-close-an]').forEach(elm => {
        elm.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !el('an-modal').classList.contains('hidden')) closeModal();
    });

    document.querySelectorAll('#an-media-picks .st-prov-card').forEach(card => {
        card.addEventListener('click', () => setMediaPick(card.dataset.media));
    });

    document.querySelectorAll('#an-audience-picks .st-prov-card').forEach(card => {
        card.addEventListener('click', () => setAudience(card.dataset.audience));
    });

    document.querySelectorAll('.an-vtab').forEach(tab => {
        tab.addEventListener('click', () => setVideoTab(tab.dataset.vtab));
    });

    document.querySelectorAll('.an-cat').forEach(cat => {
        cat.addEventListener('click', () => {
            filter = cat.dataset.filter;
            document.querySelectorAll('.an-cat').forEach(c => c.classList.toggle('is-active', c === cat));
            render();
        });
    });

    /* ---------- شروع ---------- */
    load();
})();
