/* اپ مشتری — تیکت‌های پشتیبانی (فاز ۱۰)
   global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireAuth()) { return; }

    var state = { status: '', loading: false };

    var STATUS_LABEL = {
        open: 'باز',
        answered: 'پاسخ داده‌شده',
        customer_reply: 'در گفتگو',
        closed: 'بسته'
    };
    var PRIO_LABEL = { low: 'کم', normal: 'معمولی', high: 'فوری' };

    function load() {
        if (state.loading) { return; }
        state.loading = true;

        var qs = state.status ? ('?status=' + state.status) : '';
        $('#ticketList').html('<div class="skeleton" style="height:72px"></div><div class="skeleton" style="height:72px"></div>');

        CN.api('/tickets' + qs, {
            success: function (resp) {
                state.loading = false;
                var rows = (resp && resp.data) || [];

                if (!rows.length) {
                    $('#ticketList').html('').addClass('hidden');
                    $('#ticketEmpty').removeClass('hidden');
                    return;
                }

                $('#ticketEmpty').addClass('hidden');
                $('#ticketList').removeClass('hidden');

                var html = '';
                rows.forEach(function (t) { html += rowHtml(t); });
                $('#ticketList').html(html);
            },
            error: function () {
                state.loading = false;
                $('#ticketList').html('<div class="ns-empty">خطا در دریافت تیکت‌ها.</div>');
            }
        });
    }

    function rowHtml(t) {
        return '<a class="sup-row" href="' + CN.withPort('/app/support/' + t.id) + '">' +
            '  <span class="sup-icon" data-status="' + CN.esc(t.status) + '">' +
            '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Z"/><path d="M18 11h3v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2Z"/><path d="M21 11a9 9 0 0 0-18 0"/></svg>' +
            '  </span>' +
            '  <span class="sup-body">' +
            '    <span class="sup-top">' +
            '      <strong class="sup-subject">' + CN.esc(t.subject) + '</strong>' +
            '      <span class="sup-badge" data-status="' + CN.esc(t.status) + '">' + CN.esc(STATUS_LABEL[t.status] || t.status) + '</span>' +
            '    </span>' +
            '    <span class="sup-last">' + CN.esc(t.last_message || '—') + '</span>' +
            '    <span class="sup-meta">' +
            '      <span class="sup-num" dir="ltr">' + CN.esc(t.ticket_number) + '</span>' +
            (t.order_number ? ' · <span dir="ltr">' + CN.esc(t.order_number) + '</span>' : '') +
            (t.priority === 'high' ? ' · <b class="sup-prio">فوری</b>' : '') +
            '      · ' + CN.esc(t.last_message_at || t.created_at || '') +
            '    </span>' +
            '  </span>' +
            '  <svg class="sup-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>' +
            '</a>';
    }

    /* ---------- فیلتر چیپ‌ها ---------- */
    $('.chip').on('click', function () {
        $('.chip').removeClass('is-on').attr('aria-selected', 'false');
        $(this).addClass('is-on').attr('aria-selected', 'true');
        state.status = $(this).data('status') || '';
        load();
    });

    /* ---------- شیت تیکت جدید ---------- */
    var $backdrop = $('#newSheetBackdrop');
    var $sheet = $('#newSheet');

    function openSheet() {
        $sheet.addClass('open');
        $backdrop.addClass('show');
        loadOrders();
    }

    function closeSheet() {
        $sheet.removeClass('open');
        $backdrop.removeClass('show');
        $('#err_subject, #err_message').text('').removeClass('show');
        $('#nt-subject, #nt-message').val('');
        $('#nt-priority').val('normal');
    }

    $('#btnNewTicket').on('click', openSheet);
    $('#closeNewSheet').on('click', closeSheet);
    $backdrop.on('click', closeSheet);
    $(document).on('keydown.newTicket', function (e) {
        if (e.key === 'Escape') { closeSheet(); }
    });

    /* سفارش‌های کاربر برای انتخاب مرتبط */
    function loadOrders() {
        var $select = $('#nt-order');
        if ($select.data('loaded')) { return; }

        CN.api('/orders?per_page=30', {
            success: function (resp) {
                $select.data('loaded', 1);
                var rows = (resp && resp.data) || [];
                var html = '<option value="">بدون سفارش</option>';
                rows.forEach(function (o) {
                    html += '<option value="' + o.id + '">' + CN.esc(o.order_number) + ' — ' + CN.esc(o.status_label || '') + '</option>';
                });
                $select.html(html);
            }
        });
    }

    /* ---------- ثبت ---------- */
    $('#newTicketForm').on('submit', function (e) {
        e.preventDefault();

        var subject = $.trim($('#nt-subject').val());
        var message = $.trim($('#nt-message').val());
        var priority = $('#nt-priority').val();
        var orderId = $('#nt-order').val();

        $('#err_subject, #err_message').text('').removeClass('show');

        if (!subject) {
            $('#err_subject').text('موضوع الزامی است.').addClass('show');
            return;
        }
        if (!message) {
            $('#err_message').text('توضیح مشکل الزامی است.').addClass('show');
            return;
        }

        var $btn = $('#nt-submit');
        CN.btnLoading($btn, true, 'در حال ثبت…');

        CN.api('/tickets', {
            method: 'POST',
            data: {
                subject: subject,
                message: message,
                priority: priority,
                order_id: orderId ? +orderId : null
            },
            success: function (resp) {
                CN.btnLoading($btn, false);
                CN.toast(resp.message || 'تیکت ثبت شد.', 'success');
                closeSheet();
                window.location.href = CN.withPort('/app/support/' + resp.data.id);
            },
            error: function (xhr, msg) {
                CN.btnLoading($btn, false);
                if (xhr && xhr.responseJSON && xhr.responseJSON.errors) {
                    CN.applyErrors(xhr.responseJSON.errors);
                } else {
                    CN.toast(msg || 'ثبت تیکت ناموفق بود.', 'error');
                }
            }
        });
    });

    load();
})(jQuery);
