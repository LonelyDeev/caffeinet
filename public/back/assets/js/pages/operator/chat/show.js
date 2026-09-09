/**
 * کافی‌نت آنلاین — اسکریپت صفحه «گفتگوی سفارش» پنل اپراتور (فاز ۷)
 * فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد
 *
 * چت تلگرام‌گونه: پولینگ افزایشی ۳ ثانیه + حباب‌ها + دیده‌شدن (تیک دوتایی)
 * + آپلود چندرسانه‌ای با نوار پیشرفت + عملیات سریع وضعیت.
 */
(function () {
    const PAGE = App.pageData();
    const URLS = PAGE.urls || {};
    const CAN_SEND = !!PAGE.canSend;
    const CAN_STATUS = !!PAGE.canUpdateStatus;
    const STAFF_MODE = !!PAGE.staffActions; // مدیر کل / مدیر کافی‌نت (گذارهای کامل)

    const POLL_MS = 3000;
    /* Realtime پوشر (فاز ۱۳): با فعال بودن بیدارباش پوشر، پولینگ آرام می‌شود */
    const POLL_MS_REALTIME = 12000;
    let currentPollMs = POLL_MS;
    let rtBound = false;
    const CHAT_STATUSES = ['accepted', 'in_progress', 'needs_info', 'paid'];
    const DONE_STATUSES = ['delivered', 'completed'];

    let lastId = 0;
    let polling = null;
    let sending = false;
    let pendingFile = null; // { type, file, duration }
    let groupedPrev = null; // آخرین پیام رندرشده (برای گروه‌بندی)
    let thumbUrl = null;   // فاز ۱۲ — بندانگشتی تصویر انتخاب‌شده

    const $ = window.jQuery;
    const els = {
        msgs: document.getElementById('chatMsgs'),
        pane: document.getElementById('chatPane'),
        input: document.getElementById('chatInput'),
        sendBtn: document.getElementById('sendBtn'),
        attachBtn: document.getElementById('attachBtn'),
        attachMenu: document.getElementById('attachMenu'),
        attachBackdrop: document.getElementById('attachBackdrop'),
        attachClose: document.getElementById('attachClose'),
        composer: document.getElementById('chatComposer'),
        readonly: document.getElementById('chatReadonly'),
        uploadBar: document.getElementById('uploadBar'),
        uploadFill: document.getElementById('uploadFill'),
        preview: document.getElementById('composerPreview'),
        pThumb: document.getElementById('pThumb'),
        pThumbImg: document.getElementById('pThumbImg'),
        pThumbIcon: document.getElementById('pThumbIcon'),
        pExt: document.getElementById('pExt'),
        pName: document.getElementById('pName'),
        pMeta: document.getElementById('pMeta'),
        pPct: document.getElementById('pPct'),
        pRemove: document.getElementById('pRemove'),
        pill: document.getElementById('newMsgsPill'),
        statusBadge: document.getElementById('orderStatusBadge'),
        statusActions: document.getElementById('status-actions'),
    };

    /* ================== بارگذاری پیام‌ها ================== */

    async function load(initial) {
        if (document.hidden && !initial) return;

        try {
            const url = URLS.data + (lastId > 0 ? `?after_id=${lastId}` : '');
            const res = await App.ajax(url);
            if (!res.ok) {
                if (res.status === 403 || res.status === 404) {
                    const data = await res.json().catch(() => ({}));
                    showError(data.message || 'دسترسی به این گفتگو وجود ندارد.');
                    stopPolling();
                }
                return;
            }
            const data = await res.json();
            applyPayload(data, initial);
        } catch { /* شبکه — پولینگ بعدی */ }
    }

    function applyPayload(data, initial) {
        // وضعیت چت/سفارش
        const chat = data.chat || {};
        const status = (data.order && data.order.status) || PAGE.status.value;
        syncStatus(status, (data.order && data.order.status_label) || PAGE.status.label, chat, data.order);

        const list = data.data || [];

        if (initial || lastId === 0) {
            renderAll(list);
        } else if (list.length) {
            const wasBottom = isNearBottom();
            appendMessages(list);
            if (wasBottom) scrollToBottom();
            else showPill();
        }

        // تیک دوتایی «دیده شد» برای پیام‌های خودم
        applySeenMine(data.seen_mine);

        // Realtime (فاز ۱۳): اشتراک پوشر چت + آرام‌سازی پولینگ
        if (data.rt && data.rt.enabled) { bindRealtime(data.rt); }

        if (list.length) lastId = Math.max(lastId, data.last_id || list[list.length - 1].id);
    }

    function applySeenMine(ids) {
        (ids || []).forEach((id) => {
            const el = document.querySelector(`#msg-${id} .ticks`);
            if (el && !el.classList.contains('seen')) {
                el.classList.add('seen');
                el.innerHTML = '<span class="tk">✓</span><span class="tk">✓</span>';
            }
        });
    }

    function renderAll(list) {
        els.msgs.innerHTML = '';
        groupedPrev = null;

        if (!list.length) {
            els.msgs.innerHTML = `
                <div class="cnchat-empty">
                    <span class="e-ico" aria-hidden="true">💬</span>
                    <p class="e-t">هنوز پیامی ردوبدل نشده</p>
                    <p class="e-s">اولین پیام خود را به مشتی بفرستید تا گفتگو آغاز شود.</p>
                </div>`;
            return;
        }

        appendMessages(list);
        scrollToBottom(false);
    }

    function appendMessages(list) {
        // حذف حالت خالی
        const empty = els.msgs.querySelector('.cnchat-empty');
        if (empty) empty.remove();

        for (const m of list) {
            if (document.getElementById(`msg-${m.id}`)) continue;

            // جداکننده روز
            if (dayKeyFromTs(m.ts) !== dayKeyFromTs(groupedPrev?.ts) || !groupedPrev) {
                els.msgs.insertAdjacentHTML('beforeend', daySeparator(m));
                groupedPrev = null;
            }

            els.msgs.insertAdjacentHTML('beforeend', renderMessage(m));
            groupedPrev = m;
        }
    }

    /* ================== رندر ================== */

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    function fa(n) { return String(n ?? 0).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

    function daySeparator(m) {
        const key = dayKeyFromTs(m.ts);
        const todayKey = dayKeyFromTs(Date.now() / 1000);
        const yesterdayKey = dayKeyFromTs(Date.now() / 1000 - 86400);

        let label;
        if (key === todayKey) label = 'امروز';
        else if (key === yesterdayKey) label = 'دیروز';
        else label = fa(m.date_fa || '');

        return `<div class="cnchat-day">${esc(label)}</div>`;
    }

    /** کلید روزِ محلی از timestamp (مقاوم در برابر فرمت تاریخ سرور) */
    function dayKeyFromTs(ts) {
        if (!ts) return null;
        const d = new Date(ts * 1000);
        return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
    }

    function renderMessage(m) {
        // پیام سیستمی
        if (m.role === 'system') {
            return `<div class="cnchat-sys" id="msg-${m.id}">${esc(m.content)}</div>`;
        }

        const mine = !!m.mine;
        const cls = mine ? 'mine' : 'other';
        const group = messageGroupClass(m);

        const body = renderBody(m);
        const meta = `
            <div class="msg-meta">
                <span class="ticks ${m.seen ? 'seen' : ''}" title="${m.seen ? 'دیده شد' : 'ارسال شد'}">
                    ${mine ? '<span class="tk">✓</span>' + (m.seen ? '<span class="tk">✓</span>' : '') : ''}
                </span>
                <span>${esc(m.time_fa || '')}</span>
            </div>`;

        const sender = mine ? '' : `<span class="sender-name">${esc(m.sender?.name || 'مشتری')}</span>`;

        return `
        <div class="cnmsg ${cls} ${group}" id="msg-${m.id}">
            ${sender}
            <div class="bubble ${mine ? 'own' : 'other'}">${body}${meta}</div>
        </div>`;
    }

    function messageGroupClass(m) {
        let cls = [];
        if (!groupedPrev || groupedPrev.role !== m.role || groupedPrev.mine !== m.mine || (m.ts - groupedPrev.ts > 300)) {
            cls.push('grp-first');
        }
        return cls.join(' ');
    }

    function renderBody(m) {
        switch (m.type) {
            case 'image':
                return `
                    <a class="msg-image" href="${m.file?.url || '#'}" target="_blank" rel="noopener" title="مشاهدهٔ تصویر اصلی">
                        <img src="${m.file?.url || ''}" alt="${esc(m.file?.name || 'تصویر')}">
                    </a>
                    ${m.content ? `<div class="msg-caption msg-text">${esc(m.content)}</div>` : ''}`;

            case 'video':
                return `
                    <div class="msg-video">
                        <video src="${m.file?.url || ''}" controls preload="metadata"></video>
                    </div>
                    ${m.content ? `<div class="msg-caption msg-text">${esc(m.content)}</div>` : ''}`;

            case 'audio':
                return `
                    <div class="msg-audio">
                        <audio src="${m.file?.url || ''}" controls preload="metadata"></audio>
                        ${m.file?.duration ? `<span class="dur">${fa(m.file.duration)} ثانیه</span>` : ''}
                    </div>
                    ${m.content ? `<div class="msg-caption msg-text">${esc(m.content)}</div>` : ''}`;

            case 'file':
                return `
                    <a class="msg-file" href="${m.file?.url || '#'}" target="_blank" rel="noopener">
                        <span class="f-ico" aria-hidden="true">📎</span>
                        <span class="f-info">
                            <span class="f-name">${esc(m.file?.name || 'فایل')}</span>
                            <div class="f-size">${esc(m.file?.size_fa || '')}</div>
                        </span>
                        <span class="f-dl" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                        </span>
                    </a>
                    ${m.content ? `<div class="msg-caption msg-text">${esc(m.content)}</div>` : ''}`;

            default:
                return `<div class="msg-text">${esc(m.content || '')}</div>`;
        }
    }

    function showError(msg) {
        els.msgs.innerHTML = `
            <div class="cnchat-empty">
                <span class="e-ico" aria-hidden="true">⚠</span>
                <p class="e-t">${esc(msg)}</p>
            </div>`;
    }

    /* ================== اسکرول ================== */

    function isNearBottom() {
        return els.pane.scrollHeight - els.pane.scrollTop - els.pane.clientHeight < 140;
    }

    function scrollToBottom(smooth = true) {
        els.pane.scrollTo({ top: els.pane.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }

    function showPill() {
        els.pill.classList.add('show');
    }

    /* ================== ارسال ================== */

    function canSendNow() {
        return CAN_SEND && !sending;
    }

    function syncSendState() {
        const hasText = els.input.value.trim().length > 0;
        els.sendBtn.disabled = !canSendNow() || (!hasText && !pendingFile);
    }

    async function send() {
        if (!canSendNow() || sending) return;

        const text = els.input.value.trim();
        if (!text && !pendingFile) return;

        sending = true;
        els.sendBtn.disabled = true;

        try {
            if (pendingFile) {
                await sendFile(pendingFile, text);
            } else {
                await sendText(text);
            }
        } finally {
            sending = false;
            syncSendState();
        }
    }

    async function sendText(text) {
        try {
            const res = await App.ajax(URLS.send, {
                method: 'POST',
                body: { type: 'text', content: text },
            });
            const data = await res.json();
            if (!res.ok) {
                App.toast(data.message || 'ارسال پیام ناموفق بود.', 'error');
                return;
            }
            els.input.value = '';
            autoGrow();
            ingestLocal(data.data);
            scrollToBottom();
            loadPollNow();
        } catch {
            App.toast('خطای شبکه؛ پیام ارسال نشد.', 'error');
        }
    }

    function sendFile(pfile, caption) {
        return new Promise((resolve) => {
            const fd = new FormData();
            fd.append('type', pfile.type);
            fd.append('file', pfile.file);
            if (caption) fd.append('content', caption);
            if (pfile.duration) fd.append('duration', String(pfile.duration));

            const token = document.querySelector('meta[name="csrf-token"]')?.content;

            $.ajax({
                url: App.url(URLS.send),
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                xhr: () => {
                    const xhr = new XMLHttpRequest();
                    setUploadState(true);
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const pct = Math.round((e.loaded / e.total) * 100);
                            els.uploadFill.style.width = pct + '%';
                            if (els.pPct) els.pPct.textContent = fa(pct) + '٪';
                        }
                    }, false);
                    return xhr;
                },
                success: (data) => {
                    setUploadState(false);
                    clearPendingFile();
                    els.input.value = '';
                    autoGrow();
                    ingestLocal(data.data);
                    scrollToBottom();
                    loadPollNow();
                    resolve();
                },
                error: (xhr) => {
                    setUploadState(false);
                    let msg = 'ارسال فایل ناموفق بود.';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch { /* noop */ }
                    App.toast(msg, 'error');
                    resolve();
                },
            });
        });
    }

    /** درج فوری پیام ارسالی (بدون انتظار برای پولینگ) */
    function ingestLocal(message) {
        if (!message || document.getElementById(`msg-${message.id}`)) return;

        if (dayKeyFromTs(message.ts) !== dayKeyFromTs(groupedPrev?.ts) || !groupedPrev) {
            els.msgs.insertAdjacentHTML('beforeend', daySeparator(message));
            groupedPrev = null;
        }

        els.msgs.insertAdjacentHTML('beforeend', renderMessage(message));
        groupedPrev = message;
        lastId = Math.max(lastId, message.id);
    }

    /* ================== پیوست فایل (فاز ۱۲ — شیت تلگرامی) ================== */

    const ATTACH_MAP = {
        image: {
            input: 'fileImage', label: 'تصویر', tile: 't-image',
            icon: '<rect width="18" height="18" x="3" y="3" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a1.5 1.5 0 0 0-2.12 0L5 20"/>',
        },
        video: {
            input: 'fileVideo', label: 'ویدیو', tile: 't-video',
            icon: '<path d="m16 13 5.2-3.1a.6.6 0 0 1 .8.5v3.2a.6.6 0 0 1-.8.5L16 11"/><rect width="14" height="10" x="2" y="7" rx="2"/><path d="m6 11 2 2 4-4"/>',
        },
        audio: {
            input: 'fileAudio', label: 'صدا', tile: 't-audio',
            icon: '<path d="M12 2v11"/><path d="M8 6.5a6 6 0 0 0 0 11"/><path d="M16 6.5a6 6 0 0 1 0 11"/>',
        },
        file: {
            input: 'fileFile', label: 'فایل', tile: 't-file',
            icon: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
        },
    };

    function bindAttach() {
        els.attachBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = els.attachMenu.classList.toggle('open');
            els.attachBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (els.attachBackdrop) els.attachBackdrop.hidden = !open;
        });

        els.attachMenu.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-attach]');
            if (!btn) return;
            e.stopPropagation();
            closeMenu();
            const type = btn.dataset.attach;
            const input = document.getElementById(ATTACH_MAP[type].input);
            input?.click();
        });

        if (els.attachClose) {
            els.attachClose.addEventListener('click', (e) => {
                e.stopPropagation();
                closeMenu();
            });
        }

        if (els.attachBackdrop) {
            els.attachBackdrop.addEventListener('click', closeMenu);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMenu();
        });

        Object.keys(ATTACH_MAP).forEach((type) => {
            const input = document.getElementById(ATTACH_MAP[type].input);
            input?.addEventListener('change', () => {
                const file = input.files && input.files[0];
                input.value = '';
                if (!file) return;
                setPendingFile(type, file);
            });
        });

        els.pRemove.addEventListener('click', clearPendingFile);
    }

    function closeMenu() {
        els.attachMenu.classList.remove('open');
        els.attachBtn.setAttribute('aria-expanded', 'false');
        if (els.attachBackdrop) els.attachBackdrop.hidden = true;
    }

    function setUploadState(on) {
        els.uploadBar.classList.toggle('on', !!on);
        els.preview.classList.toggle('uploading', !!on);
        if (on) {
            els.uploadFill.style.width = '0';
            if (els.pPct) els.pPct.textContent = fa(0) + '٪';
        }
    }

    function extOf(name) {
        const m = /\.([a-z0-9]+)$/i.exec(String(name || ''));
        return m ? m[1].toUpperCase() : '';
    }

    function releaseThumb() {
        if (thumbUrl) {
            try { URL.revokeObjectURL(thumbUrl); } catch { /* noop */ }
            thumbUrl = null;
        }
    }

    function setPendingFile(type, file) {
        pendingFile = { type, file, duration: null };

        const map = ATTACH_MAP[type];

        // فاز ۱۲ — بندانگشتی تصویر یا کاشی آیکن + بج پسوند
        releaseThumb();
        if (type === 'image') {
            try {
                thumbUrl = URL.createObjectURL(file);
                els.pThumbImg.src = thumbUrl;
                els.pThumbImg.hidden = false;
                els.pThumbIcon.style.display = 'none';
            } catch { /* noop */ }
        } else {
            els.pThumbImg.hidden = true;
            els.pThumbIcon.style.display = '';
        }

        els.pThumb.className = 'p-thumb ' + map.tile;
        els.pThumbIcon.innerHTML = map.icon;

        const ext = extOf(file.name);
        if (ext && type !== 'image') {
            els.pExt.textContent = ext;
            els.pExt.hidden = false;
        } else {
            els.pExt.hidden = true;
        }

        const sizeKb = Math.max(1, Math.round(file.size / 1024));
        let meta = `${fa(sizeKb)} کیلوبایت`;

        // مدت صدا/ویدیو را از متادیتای مرورگر بخوان
        if (type === 'audio' || type === 'video') {
            readDuration(file, (dur) => {
                pendingFile.duration = dur;
                els.pMeta.textContent = `${meta}${dur ? ' · ' + fa(Math.round(dur)) + ' ثانیه' : ''}`;
            });
        }

        els.pName.textContent = file.name || ATTACH_MAP[type].label;
        els.pMeta.textContent = meta;
        els.preview.classList.add('on');
        syncSendState();
    }

    function readDuration(file, cb) {
        try {
            const url = URL.createObjectURL(file);
            const media = document.createElement(file.type.startsWith('video') ? 'video' : 'audio');
            media.preload = 'metadata';
            media.src = url;
            media.onloadedmetadata = () => {
                const d = isFinite(media.duration) ? Math.round(media.duration) : null;
                URL.revokeObjectURL(url);
                cb(d);
            };
            media.onerror = () => URL.revokeObjectURL(url);
        } catch { cb(null); }
    }

    function clearPendingFile() {
        pendingFile = null;
        releaseThumb();
        els.pThumbImg.hidden = true;
        els.pThumbIcon.style.display = '';
        els.pExt.hidden = true;
        els.preview.classList.remove('on', 'uploading');
        els.uploadBar.classList.remove('on');
        els.uploadFill.style.width = '0';
        syncSendState();
    }

    /* ================== وضعیت سفارش ================== */

    const STATUS_BADGES = {
        amber: 'bg-amber-50 text-amber-700 border border-amber-200',
        sky: 'bg-sky-50 text-sky-700 border border-sky-200',
        blue: 'bg-teal-50 text-teal-700 border border-teal-200',
        orange: 'bg-amber-50 text-amber-700 border border-amber-200',
        teal: 'bg-teal-50 text-teal-700 border border-teal-200',
        emerald: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rose: 'bg-rose-50 text-rose-600 border border-rose-200',
    };
    const STATUS_COLOR = {
        pending_payment: 'amber', paid: 'amber', broadcasting: 'sky', accepted: 'blue',
        in_progress: 'blue', needs_info: 'orange', delivered: 'teal', completed: 'emerald',
        queued: 'sky', cancelled: 'rose', refunded: 'rose',
    };
    // فاز ۱۱ — نقشهٔ عملیات: از accepted تا قبل از پرداخت فقط «نیازمند اطلاعات»؛
    // paid = پرداخت انجام شده → شروع کار
    // آیتم: [status, label, نیاز-به-دلیل]
    const NEXT_ACTIONS = STAFF_MODE ? {
        // مدیر کل / مدیر کافی‌نت — همهٔ گذارهای مجاز
        accepted: PAGE.isPaid
            ? [['in_progress', 'شروع/ادامه کار'], ['needs_info', 'نیازمند اطلاعات'], ['cancelled', 'عدم امکان انجام', 1]]
            : [['needs_info', 'نیازمند اطلاعات'], ['cancelled', 'عدم امکان انجام', 1]],
        paid: [['in_progress', 'شروع کار'], ['cancelled', 'عدم امکان انجام', 1]],
        in_progress: [['needs_info', 'نیازمند اطلاعات'], ['delivered', 'تحویل شد'], ['cancelled', 'عدم امکان انجام', 1]],
        needs_info: [['in_progress', 'ادامه کار'], ['delivered', 'تحویل شد'], ['cancelled', 'عدم امکان انجام', 1]],
        delivered: [['completed', 'تکمیل نهایی'], ['refunded', 'بازگشت وجه', 1]],
    } : {
        // اپراتور — شروع/ادامه + تحویل + عدم امکان انجام (با دلیل)
        accepted: PAGE.isPaid
            ? [['in_progress', 'شروع کار'], ['needs_info', 'نیازمند اطلاعات'], ['cancelled', 'عدم امکان انجام', 1]]
            : [['needs_info', 'نیازمند اطلاعات'], ['cancelled', 'عدم امکان انجام', 1]],
        paid: [['in_progress', 'شروع کار'], ['cancelled', 'عدم امکان انجام', 1]],
        in_progress: [['needs_info', 'نیازمند اطلاعات'], ['delivered', 'تحویل شد'], ['cancelled', 'عدم امکان انجام', 1]],
        needs_info: [['in_progress', 'ادامه کار'], ['delivered', 'تحویل شد'], ['cancelled', 'عدم امکان انجام', 1]],
    };

    function syncStatus(status, label, chat, order) {
        // بج وضعیت
        if (els.statusBadge.textContent !== label) {
            const color = STATUS_COLOR[status] || 'amber';
            els.statusBadge.className = `badge ${STATUS_BADGES[color]}`;
            els.statusBadge.textContent = label;
        }

        // فاز ۱۱ — بج پرداخت (زنده با پولینگ)
        const paidBadge = document.getElementById('orderPaidBadge');
        if (paidBadge) {
            const isPaid = (order && typeof order.is_paid !== 'undefined') ? !!order.is_paid : !!PAGE.isPaid;
            const paidOn = isPaid || ['in_progress', 'needs_info', 'delivered', 'completed'].includes(status);
            if (paidOn && paidBadge.dataset.paid !== '1') {
                paidBadge.dataset.paid = '1';
                paidBadge.className = 'badge bg-emerald-50 text-emerald-700 border border-emerald-200';
                paidBadge.textContent = '✓ پرداخت‌شده';
                paidBadge.removeAttribute('title');
            } else if (!paidOn && paidBadge.dataset.paid !== '0') {
                paidBadge.dataset.paid = '0';
                paidBadge.className = 'badge bg-amber-50 text-amber-700 border border-amber-200 animate-pulse';
                paidBadge.textContent = '⏳ در انتظار پرداخت';
                paidBadge.title = 'شروع کار پس از پرداخت مشتری فعال می‌شود';
            }
        }

        // دکمه‌های وضعیت
        renderStatusActions(status, CAN_STATUS && chat.can_send);

        // حالت ارسال
        const canSend = !!chat.can_send;
        els.composer.style.display = canSend ? '' : 'none';
        els.readonly.classList.toggle('hidden', !chat.readonly);
        if (!canSend) clearPendingFile();
    }

    function renderStatusActions(status, allowed) {
        if (!CAN_STATUS || !allowed) { renderActionButtons([]); return; }
        renderActionButtons(NEXT_ACTIONS[status] || []);
    }

    function renderActionButtons(actions) {
        if (!CAN_STATUS) return;
        const current = els.statusActions.querySelectorAll('.status-action-btn');
        const desired = actions.map(a => a[0]).join(',');
        const currentKey = Array.from(current).map(b => b.dataset.status).join(',');
        if (currentKey === desired) return;

        current.forEach(b => b.remove());
        actions.forEach(([value, label, needsReason]) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'status-action-btn btn-ghost ui-press !py-2 !px-4 !text-xs' + (needsReason ? ' text-rose-600 hover:bg-rose-50' : '');
            btn.dataset.status = value;
            if (needsReason) btn.dataset.reason = '1';
            btn.textContent = label;
            els.statusActions.insertBefore(btn, els.statusBadge);
        });
    }

    async function changeStatus(status, needsReason) {
        let reason = '';

        if (needsReason) {
            reason = await promptReason();
            if (reason === null) return; // انصراف کاربر
        }

        const btns = els.statusActions.querySelectorAll('.status-action-btn');
        btns.forEach(b => b.disabled = true);
        try {
            const body = { status };
            if (reason) body.reason = reason;
            const res = await App.ajax(URLS.status, { method: 'PATCH', body });
            const data = await res.json();
            if (!res.ok) {
                App.toast(data.message || 'تغییر وضعیت ناموفق بود.', 'error');
                return;
            }
            App.toast(data.message, 'success');
            syncStatus(data.data.status.value, data.data.status.label, data.data.chat || { can_send: false, readonly: true });
            loadPollNow(); // پیام سیستمی فوراً
        } catch {
            App.toast('خطای شبکه.', 'error');
        } finally {
            btns.forEach(b => b.disabled = false);
        }
    }

    /* ---------- مودال دلیل (لغو / بازگشت وجه) ---------- */

    const reasonModal = document.getElementById('reason-modal');
    const reasonForm = document.getElementById('reason-form');
    const reasonInput = document.getElementById('reason-input');
    const reasonError = document.getElementById('reason-error');

    function promptReason() {
        return new Promise((resolve) => {
            if (!reasonModal || !reasonForm) { resolve(null); return; }

            reasonInput.value = '';
            reasonError.classList.add('hidden');
            reasonModal.classList.remove('hidden');
            setTimeout(() => reasonInput.focus(), 50);

            const done = (value) => {
                reasonModal.classList.add('hidden');
                reasonForm.onsubmit = null;
                reasonForm.querySelector('#reason-cancel').onclick = null;
                reasonModal.querySelector('[data-close-reason]').onclick = null;
                resolve(value);
            };

            reasonForm.onsubmit = (e) => {
                e.preventDefault();
                const val = reasonInput.value.trim();
                if (val.length < 3) {
                    reasonError.classList.remove('hidden');
                    reasonInput.focus();
                    return;
                }
                done(val);
            };
            reasonForm.querySelector('#reason-cancel').onclick = () => done(null);
            reasonModal.querySelector('[data-close-reason]').onclick = () => done(null);
        });
    }

    /* ================== پولینگ ================== */

    function startPolling() {
        stopPolling();
        polling = setInterval(() => load(false), currentPollMs);
    }

    function stopPolling() {
        if (polling) { clearInterval(polling); polling = null; }
    }

    function loadPollNow() { load(false); }

    /* پولینگ تطبیقی: با فعال شدن پوشر بازهٔ پول بزرگ می‌شود */
    function relaxPolling() {
        if (currentPollMs === POLL_MS_REALTIME) { return; }
        currentPollMs = POLL_MS_REALTIME;
        if (polling) { startPolling(); } // بازسازی interval با بازهٔ جدید
    }

    /* ================== Realtime پوشر (فاز ۱۳) — بیدارباش چت ==================
       کانال/کلید از payload چت (data.rt) می‌آید؛ با رویداد message.new
       پول همان لحظه اجرا می‌شود → پیام مشتری آنی می‌رسد و MySQL
       فقط با فاصلهٔ طولانی چک می‌شود. */
    function bindRealtime(rt) {
        if (rtBound) { return; }
        if (!rt.channel || !rt.key || !window.RT) { return; }

        const ok = RT.on(rt.channel, rt.event || 'message.new', () => {
            if (document.hidden) { return; }
            load(false);
        });

        if (ok) {
            rtBound = true;
            relaxPolling();
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) load(false);
    });

    /* ================== ورودی ================== */

    function autoGrow() {
        els.input.style.height = 'auto';
        els.input.style.height = Math.min(els.input.scrollHeight, 140) + 'px';
    }

    function bindInput() {
        els.input.addEventListener('input', () => { autoGrow(); syncSendState(); });
        els.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                send();
            }
        });

        els.sendBtn.addEventListener('click', send);

        els.pane.addEventListener('scroll', () => {
            if (isNearBottom()) els.pill.classList.remove('show');
        });

        els.pill.addEventListener('click', () => {
            els.pill.classList.remove('show');
            scrollToBottom();
        });
    }

    function bindStatusActions() {
        if (!CAN_STATUS) return;
        els.statusActions.addEventListener('click', (e) => {
            const btn = e.target.closest('.status-action-btn');
            if (!btn || btn.disabled) return;
            changeStatus(btn.dataset.status, btn.dataset.reason === '1');
        });
    }

    /* ---------- boot ---------- */

    let booted = false;
    function boot() {
        if (booted) return;
        booted = true;

        if (!CAN_SEND) {
            els.composer.style.display = 'none';
            els.readonly.classList.toggle('hidden', !PAGE.readonly);
        }

        bindInput();
        bindAttach();
        bindStatusActions();

        syncSendState();
        autoGrow();

        load(true);
        startPolling();
    }

    /* رویداد app:ready گاهی قبل از ثبت این شنونده fire می‌شود (core.js همزمان اجرا می‌شود)
       — بنابراین اگر App از قبل موجود است بلافاصله boot می‌کنیم. */
    if (typeof window.App !== 'undefined') {
        boot();
    } else {
        window.addEventListener('app:ready', boot, { once: true });
        document.addEventListener('DOMContentLoaded', boot, { once: true });
        setTimeout(boot, 2500);
    }
})();
