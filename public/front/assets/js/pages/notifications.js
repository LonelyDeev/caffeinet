/* اپ مشتری — زنگ اعلان‌ها (فاز ۱۰)
   پولینگ بج هر ۲۵ ثانیه + شیت پایین اعلان‌ها + علامت‌گذاری خوانده‌شده
   global CN, jQuery */
(function ($) {
    'use strict';

    var POLL_MS = 25000;
    var $btn = $('#appBell');
    var $badge = $('#appBellBadge');
    var $sheet = $('#appNotifSheet');
    var $overlay = $('#appNotifOverlay');
    var $list = $('#appNotifList');
    var $count = $('#appNotifCount');
    var $markAll = $('#appNotifMarkAll');
    var isOpen = false;
    var loading = false;

    if (!$btn.length || !CN.token()) { return; }

    var ICONS = {
        order: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>',
        ticket: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Z"/><path d="M18 11h3v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2Z"/><path d="M21 11a9 9 0 0 0-18 0"/></svg>',
        withdrawal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>',
        settlement: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/></svg>',
        system: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="11" x2="12" y2="16.5"/></svg>'
    };

    function setBadge(count) {
        count = Math.max(0, count | 0);
        if (count > 0) {
            $badge.text(count > 99 ? '۹۹+' : CN.toFaDigits(count));
            $badge.addClass('on');
        } else {
            $badge.removeClass('on');
        }
    }

    function poll() {
        if (!CN.token()) { return; }
        if (document.hidden) { return; } // تب مخفی — بدون درخواست بی‌مورد
        CN.api('/notifications/badge', {
            success: function (resp) { setBadge(resp.count || 0); },
            error: function () { /* بی‌صدا */ }
        });
    }

    // وقتی تب دوباره visible شد، بلافاصله بج تازه شود
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { poll(); }
    });

    /* ---------- شیت ---------- */
    function openSheet() {
        isOpen = true;
        $sheet.addClass('open');
        $overlay.addClass('show');
        $btn.attr('aria-expanded', 'true');
        loadList();
    }

    function closeSheet() {
        isOpen = false;
        $sheet.removeClass('open');
        $overlay.removeClass('show');
        $btn.attr('aria-expanded', 'false');
    }

    $btn.on('click', function () {
        if (isOpen) { closeSheet(); } else { openSheet(); }
    });

    $overlay.on('click', closeSheet);

    $(document).on('keydown.appNotif', function (e) {
        if (e.key === 'Escape' && isOpen) { closeSheet(); }
    });

    function itemHtml(n) {
        var icon = ICONS[n.type] || ICONS.system;
        var time = [n.date_fa, n.time_fa].filter(Boolean).join(' — ');
        var urlAttr = n.url ? ' data-url="' + CN.esc(n.url) + '"' : '';

        return '<button type="button" class="ns-item ' + (n.read ? '' : 'unread') + '" data-id="' + CN.esc(n.id) + '"' + urlAttr + '>' +
            '  <span class="ns-ico" data-type="' + CN.esc(n.type) + '">' + icon + '</span>' +
            '  <span class="ns-body">' +
            '    <span class="ns-title">' + CN.esc(n.title) + '</span>' +
            '    <span class="ns-text">' + CN.esc(n.body) + '</span>' +
            '    <span class="ns-time">' + CN.esc(time) + '</span>' +
            '  </span>' +
            '</button>';
    }

    function loadList() {
        if (loading) { return; }
        loading = true;
        $list.html('<div class="ns-loading"><span class="spinner"></span></div>');

        CN.api('/notifications', {
            success: function (resp) {
                loading = false;
                var rows = (resp && resp.data) || [];
                setBadge(0);

                if (!rows.length) {
                    $list.html('<div class="ns-empty">اعلان جدیدی ندارید.</div>');
                    $count.text('');
                    $markAll.prop('disabled', true);
                    return;
                }

                var unread = 0;
                rows.forEach(function (n) { if (!n.read) { unread++; } });

                $list.html(rows.map(itemHtml).join(''));
                $count.text(unread ? CN.toFaDigits(unread) + ' جدید' : '');
                $markAll.prop('disabled', unread === 0);

                $list.find('.ns-item').on('click', function () {
                    var $item = $(this);
                    var id = $item.data('id');
                    var url = $item.data('url');

                    CN.api('/notifications/read', {
                        method: 'POST',
                        data: { id: id },
                        success: function () { /* noop */ }
                    });

                    if (url) {
                        window.location.href = CN.withPort(url);
                    } else {
                        $item.removeClass('unread');
                        poll();
                    }
                });
            },
            error: function () {
                loading = false;
                $list.html('<div class="ns-empty">خطا در دریافت اعلان‌ها.</div>');
            }
        });
    }

    $markAll.on('click', function () {
        $(this).prop('disabled', true);
        CN.api('/notifications/read', {
            method: 'POST',
            data: {},
            success: function () {
                setBadge(0);
                closeSheet();
            }
        });
    });

    poll();
    setInterval(poll, POLL_MS);

    /* ---------- Realtime پوشر (فاز ۱۳) — بیدارباش زنگ ----------
       پیکربندی عمومی (enabled/key/cluster) از data-rt-config لود شده؛
       فقط کانال شخصی کاربر از API خوانده می‌شود؛ سپس با رویداد notif.new
       بج بلافاصله تازه می‌شود. اگر پوشر خاموش/در دسترس نباشد، همان
       پولینگ قبلی کار می‌کند. */
    (function initRealtime() {
        if (!window.RT) { return; }

        if (!RT.cfg.enabled) { return; } // پوشر خاموش — پولینگ کافی است

        if (RT.cfg.channel) { subscribeUserChannel(); return; }

        // کانال شخصی کاربر را از API بگیر (یک درخواست سبک — فقط هنگام ورود)
        CN.api('/realtime/config', {
            success: function (resp) {
                if (!resp || !resp.enabled || !resp.channel) { return; }
                RT.cfg.channel = resp.channel;
                subscribeUserChannel();
            },
            error: function () { /* پولینگ کافی است */ }
        });
    })();

    function subscribeUserChannel() {
        if (!window.RT || !RT.active()) { return; }
        RT.bindUser('notif.new', function () {
            if (document.hidden) { return; } // تب مخفی — با visible شدن تازه می‌شود
            poll();
        });
    }
})(jQuery);
