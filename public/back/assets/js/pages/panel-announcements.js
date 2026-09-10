/**
 * کافی‌نت آنلاین — نمایش اطلاعیه‌ها در پنل‌ها (فاز ۱۵ — مشترک هر ۴ پنل)
 *
 * نحوهٔ کار:
 *   ۱) آدرس‌های API از data-attr روی <body> خوانده می‌شوند:
 *      data-ann-pending (GET) و data-ann-read (POST با {id})
 *   ۲) اطلاعیه‌های دیده‌نشده به ترتیب در یک مودال زیبا (بلندگو + رسانه) نمایش داده می‌شوند.
 *   ۳) با بستن هر اطلاعیه، «دیده‌شده» در سرور ثبت می‌شود و اطلاعیه بعدی می‌آید.
 */
/* global jQuery */
(function ($) {
    'use strict';

    const body = document.body;
    const pendingUrl = body.dataset.annPending || '';
    const readUrl = body.dataset.annRead || '';

    if (!pendingUrl || !readUrl) { return; }

    const queue = [];
    let active = null; // اطلاعیهٔ در حال نمایش

    const MEGAPHONE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>';

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    /* ---------- ساخت مودال ---------- */
    function show(item) {
        active = item;

        const mediaHtml = item.media_type === 'image' && item.media_url
            ? `<div class="annm-media"><img src="${esc(item.media_url)}" alt="رسانهٔ اطلاعیه"></div>`
            : item.media_type === 'video' && item.video_url
                ? `<div class="annm-media"><video src="${esc(item.video_url)}" controls preload="metadata" playsinline></video></div>`
                : '';

        const textHtml = item.body
            ? `<p class="annm-text">${esc(item.body)}</p>`
            : (mediaHtml ? '<p class="annm-text"> </p>' : '<p class="annm-text">—</p>');

        const remaining = queue.length;

        const $wrap = $(`
            <div class="annm-backdrop" role="dialog" aria-modal="true" aria-labelledby="annm-title">
                <div class="annm" data-id="${esc(item.id)}">
                    <div class="annm-head">
                        <span class="annm-icon">${MEGAPHONE}</span>
                        <div class="annm-head-text min-w-0">
                            <span class="annm-kicker">📢 اطلاعیه سامانه</span>
                            <h2 class="annm-title" id="annm-title">${esc(item.title)}</h2>
                            <p class="annm-date">${esc(item.created_at_label || '')}</p>
                        </div>
                    </div>
                    <div class="annm-body">
                        ${mediaHtml}
                        ${textHtml}
                    </div>
                    <div class="annm-foot">
                        ${remaining > 1
                            ? `<span class="annm-count">${remaining} اطلاعیه در انتظار</span>`
                            : '<span class="annm-count"></span>'}
                        <button type="button" class="btn btn-primary btn-shine ui-press annm-btn">متوجه شدم</button>
                    </div>
                </div>
            </div>`);

        $('body').append($wrap);

        const close = () => {
            // توقف ویدیو هنگام بستن
            $wrap.find('video').each(function () { try { this.pause(); } catch (e) { /* noop */ } });

            $wrap.addClass('annm-closing');
            $wrap.find('.annm').addClass('annm-closing');
            markRead(item.id);
            setTimeout(() => {
                $wrap.remove();
                active = null;
                next();
            }, 230);
        };

        $wrap.find('.annm-btn').on('click', close);
        $wrap.on('click', e => { if (e.target === $wrap[0]) { close(); } });
        $(document).on('keydown.annm', e => {
            if (e.key === 'Escape' && active) { close(); }
        });
    }

    function next() {
        $(document).off('keydown.annm');
        if (queue.length) {
            show(queue.shift());
        }
    }

    function markRead(id) {
        fetch(App.url(readUrl.replace('__ID__', String(id))), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id }),
            credentials: 'same-origin',
        }).catch(() => { /* بی‌صدا — دفعهٔ بعد دوباره نمایش داده می‌شود */ });
    }

    /* ---------- دریافت اطلاعیه‌ها ---------- */
    $(function () {
        fetch(App.url(pendingUrl), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
            .then(r => (r.ok ? r.json() : null))
            .then(resp => {
                const items = (resp && resp.data) || [];
                if (items.length) {
                    items.reverse(); // قدیمی → جدید
                    queue.push(...items);
                    setTimeout(() => next(), 700); // کمی تأخیر تا صفحه آرام بگیرد
                }
            })
            .catch(() => { /* noop */ });
    });
})(jQuery);
