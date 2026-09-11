/* اپ مشتری — گفتگوی سفارش (فاز ۷ — چت تلگرام‌گونه) */
/* فاز ۱۲ — شیت پیوست تلگرامی + آپلودر زیبا + کش‌ودرگ + paste تصویر */
/* فایل مستقل (Blade + jQuery) — بدون Node / بدون بیلد */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireCompleteProfile()) { return; }

    var orderId = Number(window.location.pathname.split('/').pop()) || 0;
    var API = '/orders/' + orderId + '/messages';

    var POLL_MS = 3000;

    /* Realtime پوشر (فاز ۱۳): وقتی فعال است پولینگ «آرام» می‌شود (۱۲ ثانیه)
       چون بیدارباش لحظه‌ای را پوشر انجام می‌دهد — کاهش فشار MySQL. */
    var POLL_MS_REALTIME = 12000;
    var currentPollMs = POLL_MS;
    var rtBound = false;

    var lastId = 0;
    var pollTimer = null;
    var sending = false;
    var pendingFile = null; // { type, file, duration }
    var groupedPrev = null;
    var thumbUrl = null;

    var els = {
        card: document.getElementById('chatCard'),
        head: document.getElementById('chatHead'),
        headAvatar: document.getElementById('chAvatar'),
        headName: document.getElementById('chName'),
        headSub: document.getElementById('chSub'),
        badge: document.getElementById('chatStatusBadge'),
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
        chat: document.getElementById('cnchat'),
        dropzone: document.getElementById('cnchatDropzone'),
    };

    /* ================== بارگذاری ================== */

    function load(initial) {
        if (document.hidden && !initial) { return; }

        CN.api(API + (lastId > 0 ? '?after_id=' + lastId : ''), {
            success: function (resp) { applyPayload(resp, initial); },
            error: function () { /* پولینگ بعدی */ }
        });
    }

    function applyPayload(data, initial) {
        var chat = data.chat || {};
        var order = data.order || {};

        // نمایش/بستن کارت — فاز ۱۲: اطلاع‌رسانی به order-detail برای جایگذاری پرداخت
        if (chat.enabled) {
            els.card.classList.remove('hidden');
            notifyVisibility(true);
        } else {
            els.card.classList.add('hidden');
            notifyVisibility(false);
            return;
        }

        if (order.status_label && els.badge.textContent !== order.status_label) {
            els.badge.textContent = order.status_label;
        }

        // سربرگ گفتگو — اطلاعات اپراتور متصل (تصویر/آواتار + نام + کافی‌نت)
        renderHead(order);

        syncComposer(chat);

        var list = data.data || [];

        if (initial || lastId === 0) {
            renderAll(list);
        } else if (list.length) {
            var wasBottom = isNearBottom();
            appendMessages(list);
            if (wasBottom) { scrollToBottom(); }
            else { showPill(); }
        }

        // تیک دوتایی «دیده شد» برای پیام‌های خودم
        applySeenMine(data.seen_mine);

        // Realtime (فاز ۱۳): اشتراک پوشر چت + آرام‌سازی پولینگ
        if (data.rt && data.rt.enabled) { bindRealtime(data.rt); }

        if (list.length) {
            lastId = Math.max(lastId, data.last_id || list[list.length - 1].id);
        }
    }

    /* رویداد جهت order-detail.js: کارت گفتگو در دسترس است یا نه */
    function notifyVisibility(visible) {
        try {
            document.dispatchEvent(new CustomEvent('chat:visibility', { detail: { visible: !!visible } }));
        } catch (e) { /* noop */ }
    }

    /* ---------- سربرگ گفتگو — اپراتور متصل با آواتار ---------- */
    function renderHead(order) {
        var op = order.operator || null;
        var net = order.coffeenet || null;

        if (op && op.name) {
            // آواتار حرف اول نام اپراتور
            var first = String(op.name).trim().charAt(0) || 'اپ';
            els.headAvatar.textContent = first;
            els.headAvatar.classList.add('ch-avatar--on');
            els.headName.textContent = op.name;

            var sub = net ? ('اپراتور کافی‌نت «' + net.name + '»') : 'اپراتور';
            if (order.accepted_at_fa) {
                sub += ' · متصل از ' + order.accepted_at_fa;
            }
            els.headSub.textContent = sub;
        } else if (net && net.name) {
            els.headAvatar.textContent = '☕';
            els.headAvatar.classList.remove('ch-avatar--on');
            els.headName.textContent = 'گفتگو با کافی‌نت';
            els.headSub.textContent = 'کافی‌نت «' + net.name + '»' + (order.accepted_at_fa ? ' · متصل از ' + order.accepted_at_fa : '');
        } else {
            els.headAvatar.textContent = '💬';
            els.headAvatar.classList.remove('ch-avatar--on');
            els.headName.textContent = 'گفتگو با اپراتور';
            els.headSub.textContent = 'در انتظار اتصال اپراتور…';
        }
    }

    function applySeenMine(ids) {
        (ids || []).forEach(function (id) {
            var el = document.querySelector('#msg-' + id + ' .ticks');
            if (el && el.classList && !el.classList.contains('seen')) {
                el.classList.add('seen');
                el.innerHTML = '<span class="tk">✓</span><span class="tk">✓</span>';
            }
        });
    }

    function syncComposer(chat) {
        var canSend = !!chat.can_send;
        els.composer.style.display = canSend ? '' : 'none';
        els.readonly.classList.toggle('hidden', !chat.readonly);
        if (!canSend) { clearPendingFile(); }
        syncSendState();
    }

    function renderAll(list) {
        els.msgs.innerHTML = '';
        groupedPrev = null;

        if (!list.length) {
            els.msgs.innerHTML =
                '<div class="cnchat-empty">' +
                '  <span class="e-ico" aria-hidden="true">💬</span>' +
                '  <p class="e-t">گفتگو آغاز نشده است</p>' +
                '  <p class="e-s">اگر سوالی درباره سفارش دارید، همین‌جا بپرسید؛ اپراتور پاسخ می‌دهد.</p>' +
                '</div>';
            return;
        }

        appendMessages(list);
        scrollToBottom(false);
    }

    function appendMessages(list) {
        var empty = els.msgs.querySelector('.cnchat-empty');
        if (empty) { empty.remove(); }

        list.forEach(function (m) {
            if (document.getElementById('msg-' + m.id)) { return; }

            if (dayKeyFromTs(m.ts) !== dayKeyFromTs(groupedPrev ? groupedPrev.ts : null) || !groupedPrev) {
                els.msgs.insertAdjacentHTML('beforeend', daySeparator(m));
                groupedPrev = null;
            }

            els.msgs.insertAdjacentHTML('beforeend', renderMessage(m));
            groupedPrev = m;
        });
    }

    /* ================== رندر ================== */

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fa(n) { return String(n || 0).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; }); }

    function daySeparator(m) {
        var key = dayKeyFromTs(m.ts);
        var todayKey = dayKeyFromTs(Date.now() / 1000);
        var yesterdayKey = dayKeyFromTs(Date.now() / 1000 - 86400);

        var label;
        if (key === todayKey) { label = 'امروز'; }
        else if (key === yesterdayKey) { label = 'دیروز'; }
        else { label = fa(m.date_fa || ''); }

        return '<div class="cnchat-day">' + esc(label) + '</div>';
    }

    function dayKeyFromTs(ts) {
        if (!ts) { return null; }
        var d = new Date(ts * 1000);
        return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
    }

    function renderMessage(m) {
        if (m.role === 'system') {
            // رویدادهای سیستمی — «اتصال اپراتور» با کارت زیبا رندر می‌شود
            if (m.kind === 'connected') {
                return '<div class="cnchat-event ev-connected" id="msg-' + m.id + '" role="status">' +
                    '<span class="ev-ico" aria-hidden="true">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>' +
                    '</span>' +
                    '<div class="ev-body">' +
                    '<strong class="ev-title">اپراتور به درخواست شما متصل شد</strong>' +
                    '<p class="ev-text">' + esc(m.content) + '</p>' +
                    '</div>' +
                    '<span class="ev-time">' + esc(m.time_fa || '') + '</span>' +
                    '</div>';
            }

            return '<div class="cnchat-sys sys-k-' + esc(m.kind || 'info') + '" id="msg-' + m.id + '">' + esc(m.content) + '</div>';
        }

        var mine = !!m.mine;
        var cls = mine ? 'mine' : 'other';
        var group = (!groupedPrev || groupedPrev.role !== m.role || groupedPrev.mine !== m.mine || (m.ts - groupedPrev.ts > 300)) ? 'grp-first' : '';

        var body = renderBody(m);
        var ticks = mine
            ? '<span class="ticks ' + (m.seen ? 'seen' : '') + '" title="' + (m.seen ? 'دیده شد' : 'ارسال شد') + '">' +
              '<span class="tk">✓</span>' + (m.seen ? '<span class="tk">✓</span>' : '') + '</span>'
            : '';

        var meta =
            '<div class="msg-meta">' + ticks + '<span>' + esc(m.time_fa || '') + '</span></div>';

        var sender = mine ? '' : '<span class="sender-name">' + esc((m.sender && m.sender.name) || 'اپراتور') + '</span>';

        return '<div class="cnmsg ' + cls + ' ' + group + '" id="msg-' + m.id + '">' +
            sender +
            '<div class="bubble ' + (mine ? 'own' : 'other') + '">' + body + meta + '</div>' +
            '</div>';
    }

    function renderBody(m) {
        var f = m.file || {};

        switch (m.type) {
            case 'image':
                return '<a class="msg-image" href="' + (f.url || '#') + '" target="_blank" rel="noopener" title="مشاهده تصویر اصلی">' +
                    '<img src="' + (f.url || '') + '" alt="' + esc(f.name || 'تصویر') + '">' +
                    '</a>' +
                    (m.content ? '<div class="msg-caption msg-text">' + esc(m.content) + '</div>' : '');

            case 'video':
                return '<div class="msg-video"><video src="' + (f.url || '') + '" controls preload="metadata"></video></div>' +
                    (m.content ? '<div class="msg-caption msg-text">' + esc(m.content) + '</div>' : '');

            case 'audio':
                return '<div class="msg-audio"><audio src="' + (f.url || '') + '" controls preload="metadata"></audio>' +
                    (f.duration ? '<span class="dur">' + fa(f.duration) + ' ثانیه</span>' : '') +
                    '</div>' +
                    (m.content ? '<div class="msg-caption msg-text">' + esc(m.content) + '</div>' : '');

            case 'file':
                return '<a class="msg-file" href="' + (f.url || '#') + '" target="_blank" rel="noopener">' +
                    '<span class="f-ico" aria-hidden="true">📎</span>' +
                    '<span class="f-info"><span class="f-name">' + esc(f.name || 'فایل') + '</span>' +
                    '<div class="f-size">' + esc(f.size_fa || '') + '</div></span>' +
                    '<span class="f-dl" aria-hidden="true">⬇</span>' +
                    '</a>' +
                    (m.content ? '<div class="msg-caption msg-text">' + esc(m.content) + '</div>' : '');

            default:
                return '<div class="msg-text">' + esc(m.content || '') + '</div>';
        }
    }

    /* ================== اسکرول ================== */

    function isNearBottom() {
        return els.pane.scrollHeight - els.pane.scrollTop - els.pane.clientHeight < 140;
    }

    function scrollToBottom(smooth) {
        els.pane.scrollTo({ top: els.pane.scrollHeight, behavior: smooth === false ? 'auto' : 'smooth' });
    }

    function showPill() { els.pill.classList.add('show'); }

    /* ================== ارسال ================== */

    function syncSendState() {
        var hasText = els.input.value.trim().length > 0;
        els.sendBtn.disabled = !(!sending && (hasText || !!pendingFile));
    }

    function send() {
        if (sending) { return; }

        var text = els.input.value.trim();
        if (!text && !pendingFile) { return; }

        sending = true;
        syncSendState();

        if (pendingFile) {
            sendFile(pendingFile, text, function () {
                sending = false;
                syncSendState();
            });
        } else {
            sendText(text, function () {
                sending = false;
                syncSendState();
            });
        }
    }

    function sendText(text, done) {
        CN.api(API, {
            method: 'POST',
            data: { type: 'text', content: text },
            success: function (resp) {
                els.input.value = '';
                autoGrow();
                ingestLocal(resp.data);
                scrollToBottom();
                load(false);
                done();
            },
            error: function (xhr, message) {
                CN.toast(message || 'ارسال پیام ناموفق بود.', 'error');
                done();
            }
        });
    }

    function sendFile(pfile, caption, done) {
        var fd = new FormData();
        fd.append('type', pfile.type);
        fd.append('file', pfile.file);
        if (caption) { fd.append('content', caption); }
        if (pfile.duration) { fd.append('duration', String(pfile.duration)); }

        setUploadState(true);

        $.ajax({
            url: CN.apiUrl(API),
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + CN.token()
            },
            xhr: function () {
                var xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        var pct = Math.round((e.loaded / e.total) * 100);
                        els.uploadFill.style.width = pct + '%';
                        els.pPct.textContent = fa(pct) + '٪';
                    }
                }, false);
                return xhr;
            },
            success: function (resp) {
                setUploadState(false);
                clearPendingFile();
                els.input.value = '';
                autoGrow();
                ingestLocal(resp.data);
                scrollToBottom();
                load(false);
                done();
            },
            error: function (xhr) {
                setUploadState(false);
                var message = 'ارسال فایل ناموفق بود.';
                try { message = JSON.parse(xhr.responseText).message || message; } catch (e) { /* noop */ }
                CN.toast(message, 'error');
                done();
            }
        });
    }

    /* حالت آپلود: کارت آپلودر زنده می‌شود (شیمر + درصد) */
    function setUploadState(on) {
        els.uploadBar.classList.toggle('on', !!on);
        els.preview.classList.toggle('uploading', !!on);
        if (on) {
            els.uploadFill.style.width = '0';
            els.pPct.textContent = fa(0) + '٪';
        }
    }

    function ingestLocal(message) {
        if (!message || document.getElementById('msg-' + message.id)) { return; }

        if (dayKeyFromTs(message.ts) !== dayKeyFromTs(groupedPrev ? groupedPrev.ts : null) || !groupedPrev) {
            els.msgs.insertAdjacentHTML('beforeend', daySeparator(message));
            groupedPrev = null;
        }

        els.msgs.insertAdjacentHTML('beforeend', renderMessage(message));
        groupedPrev = message;
        lastId = Math.max(lastId, message.id);
    }

    /* ================== پیوست (فاز ۱۲ — شیت تلگرامی) ================== */

    var ATTACH_MAP = {
        image: {
            input: 'fileImage', label: 'تصویر', tile: 't-image',
            icon: '<rect width="18" height="18" x="3" y="3" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a1.5 1.5 0 0 0-2.12 0L5 20"/>'
        },
        video: {
            input: 'fileVideo', label: 'ویدیو', tile: 't-video',
            icon: '<path d="m16 13 5.2-3.1a.6.6 0 0 1 .8.5v3.2a.6.6 0 0 1-.8.5L16 11"/><rect width="14" height="10" x="2" y="7" rx="2"/><path d="m6 11 2 2 4-4"/>'
        },
        audio: {
            input: 'fileAudio', label: 'صدا', tile: 't-audio',
            icon: '<path d="M12 2v11"/><path d="M8 6.5a6 6 0 0 0 0 11"/><path d="M16 6.5a6 6 0 0 1 0 11"/>'
        },
        file: {
            input: 'fileFile', label: 'فایل', tile: 't-file',
            icon: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>'
        }
    };

    function bindAttach() {
        els.attachBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleMenu();
        });

        els.attachMenu.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-attach]');
            if (!btn) { return; }
            e.stopPropagation();
            closeMenu();
            var input = document.getElementById(ATTACH_MAP[btn.dataset.attach].input);
            if (input) { input.click(); }
        });

        if (els.attachClose) {
            els.attachClose.addEventListener('click', function (e) {
                e.stopPropagation();
                closeMenu();
            });
        }

        if (els.attachBackdrop) {
            els.attachBackdrop.addEventListener('click', closeMenu);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeMenu(); }
        });

        Object.keys(ATTACH_MAP).forEach(function (type) {
            var input = document.getElementById(ATTACH_MAP[type].input);
            if (!input) { return; }
            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                input.value = '';
                if (file) { setPendingFile(type, file); }
            });
        });

        els.pRemove.addEventListener('click', clearPendingFile);

        bindDragDrop();
        bindPaste();
    }

    function toggleMenu() {
        var isOpen = els.attachMenu.classList.contains('open');
        if (isOpen) { closeMenu(); } else { openMenu(); }
    }

    function openMenu() {
        els.attachMenu.classList.add('open');
        els.attachBtn.setAttribute('aria-expanded', 'true');
        if (els.attachBackdrop) {
            els.attachBackdrop.hidden = false;
            // ری‌استارت انیمیشن پله‌ای کارت‌ها
            els.attachMenu.querySelectorAll('.as-item').forEach(function (el) {
                el.style.animation = 'none';
                void el.offsetWidth;
                el.style.animation = '';
            });
        }
    }

    function closeMenu() {
        els.attachMenu.classList.remove('open');
        els.attachBtn.setAttribute('aria-expanded', 'false');
        if (els.attachBackdrop) { els.attachBackdrop.hidden = true; }
    }

    /* ---------- کش‌ودرگ روی چت + paste تصویر ---------- */

    function detectType(file) {
        if (file.type && file.type.indexOf('image/') === 0) { return 'image'; }
        if (file.type && file.type.indexOf('video/') === 0) { return 'video'; }
        if (file.type && file.type.indexOf('audio/') === 0) { return 'audio'; }
        return 'file';
    }

    function bindDragDrop() {
        if (!els.chat) { return; }

        var depth = 0;

        els.chat.addEventListener('dragenter', function (e) {
            if (!e.dataTransfer || Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') === -1) { return; }
            e.preventDefault();
            depth++;
            els.chat.classList.add('dropping');
        });

        els.chat.addEventListener('dragover', function (e) {
            e.preventDefault();
        });

        els.chat.addEventListener('dragleave', function (e) {
            e.preventDefault();
            depth = Math.max(0, depth - 1);
            if (depth === 0) { els.chat.classList.remove('dropping'); }
        });

        els.chat.addEventListener('drop', function (e) {
            e.preventDefault();
            depth = 0;
            els.chat.classList.remove('dropping');
            if (els.composer.style.display === 'none') { return; }
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) { setPendingFile(detectType(file), file); }
        });
    }

    function bindPaste() {
        document.addEventListener('paste', function (e) {
            if (!e.clipboardData || els.composer.style.display === 'none' || pendingFile) { return; }
            var items = e.clipboardData.files && e.clipboardData.files.length
                ? e.clipboardData.files
                : null;
            if (items && items[0] && items[0].type.indexOf('image/') === 0) {
                setPendingFile('image', items[0]);
            }
        });
    }

    /* ---------- آپلودر زیبا ---------- */

    function setPendingFile(type, file) {
        pendingFile = { type: type, file: file, duration: null };

        var map = ATTACH_MAP[type];

        // بندانگشتی تصویر یا کاشی آیکن
        releaseThumb();
        if (type === 'image') {
            try {
                thumbUrl = URL.createObjectURL(file);
                els.pThumbImg.src = thumbUrl;
                els.pThumbImg.hidden = false;
                els.pThumbIcon.style.display = 'none';
            } catch (e) { /* noop */ }
        } else {
            els.pThumbImg.hidden = true;
            els.pThumbIcon.style.display = '';
        }

        els.pThumb.className = 'p-thumb ' + map.tile;
        els.pThumbIcon.innerHTML = map.icon;

        // بج پسوند فایل (برای غیر تصویر)
        var ext = extOf(file.name);
        if (ext && type !== 'image') {
            els.pExt.textContent = ext;
            els.pExt.hidden = false;
        } else {
            els.pExt.hidden = true;
        }

        var sizeKb = Math.max(1, Math.round(file.size / 1024));
        var meta = fa(sizeKb) + ' کیلوبایت';

        if (type === 'audio' || type === 'video') {
            readDuration(file, function (dur) {
                pendingFile.duration = dur;
                els.pMeta.textContent = meta + (dur ? ' · ' + fa(dur) + ' ثانیه' : '');
            });
        }

        els.pName.textContent = file.name || map.label;
        els.pMeta.textContent = meta;
        els.preview.classList.add('on');
        syncSendState();
    }

    function extOf(name) {
        var m = /\.([a-z0-9]+)$/i.exec(String(name || ''));
        return m ? m[1].toUpperCase() : '';
    }

    function releaseThumb() {
        if (thumbUrl) {
            try { URL.revokeObjectURL(thumbUrl); } catch (e) { /* noop */ }
            thumbUrl = null;
        }
    }

    function readDuration(file, cb) {
        try {
            var url = URL.createObjectURL(file);
            var media = document.createElement(file.type.indexOf('video') === 0 ? 'video' : 'audio');
            media.preload = 'metadata';
            media.src = url;
            media.onloadedmetadata = function () {
                var d = isFinite(media.duration) ? Math.round(media.duration) : null;
                URL.revokeObjectURL(url);
                cb(d);
            };
            media.onerror = function () { URL.revokeObjectURL(url); };
        } catch (e) { cb(null); }
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

    /* ================== ورودی + پولینگ ================== */

    function autoGrow() {
        els.input.style.height = 'auto';
        els.input.style.height = Math.min(els.input.scrollHeight, 140) + 'px';
    }

    function bindInput() {
        els.input.addEventListener('input', function () { autoGrow(); syncSendState(); });
        els.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                send();
            }
        });

        els.sendBtn.addEventListener('click', send);

        els.pane.addEventListener('scroll', function () {
            if (isNearBottom()) { els.pill.classList.remove('show'); }
        });

        els.pill.addEventListener('click', function () {
            els.pill.classList.remove('show');
            scrollToBottom();
        });
    }

    function startPolling() {
        pollTimer = window.setInterval(function () {
            if (!document.hidden) { load(false); }
        }, currentPollMs);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { load(false); }
        });
    }

    /* پولینگ تطبیقی: با فعال شدن پوشر بازهٔ پول بزرگ می‌شود (ترفند منابع) */
    function relaxPolling() {
        if (currentPollMs === POLL_MS_REALTIME) { return; }
        currentPollMs = POLL_MS_REALTIME;
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = window.setInterval(function () {
                if (!document.hidden) { load(false); }
            }, currentPollMs);
        }
    }

    /* ---------- Realtime پوشر (فاز ۱۳) — بیدارباش چت ----------
       کانال/کلید از payload خود چت (data.rt) می‌آید؛ با رویداد message.new
       پول همان لحظه اجرا می‌شود → پیام طرف مقابل آنی می‌رسد و MySQL
       فقط با فاصلهٔ طولانی چک می‌شود. */
    function bindRealtime(rt) {
        if (rtBound) { return; }
        if (!rt || !rt.enabled || !rt.key || !rt.channel || !window.RT) { return; }

        var ok = RT.on(rt.channel, rt.event || 'message.new', function () {
            if (document.hidden) { return; }
            load(false);
        });

        if (ok) {
            rtBound = true;
            relaxPolling();
        }
    }

    /* ---------- boot ---------- */
    bindInput();
    bindAttach();
    autoGrow();
    load(true);
    startPolling();
})(jQuery);
