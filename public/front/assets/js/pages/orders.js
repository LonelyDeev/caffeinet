/* اپ مشتری — فهرست سفارش‌ها */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireAuth()) { return; }

    var state = { status: '', page: 1, hasMore: false, loading: false };

    function load() {
        if (state.loading) { return; }
        state.loading = true;

        if (state.page === 1) {
            $('#ordersList').html('<div class="skeleton svc"></div><div class="skeleton svc"></div>');
        } else {
            $('#ordersMoreLoader').removeClass('hidden');
        }

        var params = '?page=' + state.page;
        if (state.status) { params += '&status=' + state.status; }

        CN.api('/orders' + params, {
            success: function (resp) {
                state.loading = false;

                if (state.page === 1) { $('#ordersList').empty(); }
                $('#ordersMoreLoader').addClass('hidden');

                (resp.data || []).forEach(function (o) {
                    $('#ordersList').append(orderCard(o));
                });

                state.hasMore = !!resp.next_page_url;
                $('#ordersLoadMore').toggleClass('hidden', !state.hasMore);

                var empty = !(resp.data || []).length;
                $('#ordersEmpty').toggleClass('hidden', !empty || state.page > 1);
                $('#ordersList').toggleClass('hidden', empty);
            },
            error: function () {
                state.loading = false;
                $('#ordersMoreLoader').addClass('hidden');
            }
        });
    }

    /* رنگ نوار وضعیت بر اساس استاتوس */
    var STRIP = {
        pending_payment: 'st-amber',
        paid: 'st-green',
        broadcasting: 'st-teal',
        accepted: 'st-green',
        in_progress: 'st-teal',
        needs_info: 'st-orange',
        delivered: 'st-teal',
        completed: 'st-green',
        queued: 'st-stone',
        cancelled: 'st-rose',
        refunded: 'st-rose'
    };

    function orderCard(o) {
        var icon = (o.service && o.service.icon) || '📄';
        var name = (o.service && o.service.name) || 'سفارش ' + CN.esc(o.order_number);
        var strip = STRIP[o.status] || 'st-stone';

        return '<a class="order-cardv2 ' + strip + '" href="' + CN.withPort('/app/orders/' + o.id) + '" aria-label="جزئیات سفارش ' + CN.esc(o.order_number) + '">' +
            '<span class="oc-strip" aria-hidden="true"></span>' +
            '<span class="oc-main">' +
            '<span class="oc-top">' +
            '<span class="oc-icon" aria-hidden="true">' + CN.esc(icon) + '</span>' +
            '<span class="oc-titles">' +
            '<strong class="oc-name">' + CN.esc(name) + '</strong>' +
            '<span class="oc-meta"><span class="num" dir="ltr">' + CN.esc(o.order_number) + '</span> · ' + CN.esc(o.created_at_fa || '') + '</span>' +
            '</span>' +
            CN.statusBadge(o.status, o.status_label) +
            '</span>' +
            '<span class="oc-bottom">' +
            '<span class="oc-amount">' + CN.faMoney(o.total_amount) + ' <small>تومان</small></span>' +
            '<span class="oc-cta">مشاهده جزئیات' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>' +
            '</span>' +
            '</span>' +
            '</span>' +
            '</a>';
    }

    /* فیلتر وضعیت */
    $('#statusChips').on('click', '.chip', function () {
        $('#statusChips .chip').removeClass('active');
        $(this).addClass('active');
        state.status = $(this).data('status') || '';
        state.page = 1;
        load();
    });

    $('#ordersLoadMore').on('click', function () {
        if (!state.hasMore || state.loading) { return; }
        state.page++;
        load();
    });

    load();
})(jQuery);
