/* اپ مشتری — جزئیات سفارش + پرداخت */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireAuth()) { return; }

    var orderId = Number(window.location.pathname.split('/').pop()) || 0;
    var order = null;

    var BROADCAST_TOTAL = 60;      // مهلت پخش (از سرور تنظیم می‌شود)
    var RING_C = 2 * Math.PI * 40; // محیط حلقه (r=40, viewBox 96)
    var pollTimer = null;
    var tickTimer = null;
    var lastStatus = null;
    var chatCardVisible = false;   // فاز ۱۲ — کارت گفتگو در دسترس است؟ (رویداد chat:visibility)

    /* پیام‌های بازگشت از درگاه */
    var query = {};
    try { query = Object.fromEntries(new URLSearchParams(window.location.search)); } catch (e) { /* noop */ }
    if (query.paid === '1') {
        CN.toast('پرداخت با موفقیت انجام شد؛ اپراتور کار شما را آغاز می‌کند.', 'success', 5200);
    } else if (query.payment === 'failed') {
        CN.toast('پرداخت ناموفق بود یا لغو شد؛ می‌توانید دوباره تلاش کنید.', 'error', 5200);
    }

    function load() {
        $('#orderLoader').removeClass('hidden');

        CN.api('/orders/' + orderId, {
            success: function (resp) {
                $('#orderLoader').addClass('hidden');
                order = resp.data;
                render();
            },
            error: function (xhr, message) {
                $('#orderLoader').addClass('hidden');
                $('#orderNumber').text('—');
                $('#orderService').text(message);
            }
        });
    }

    /* بارگذاری بی‌صدا (polling وضعیت پخش/صف) */
    function loadSilent() {
        if (document.hidden) { return; } // تب مخفی — بدون درخلود بی‌مورد

        CN.api('/orders/' + orderId, {
            success: function (resp) {
                if (!order || resp.data.status !== order.status) {
                    order = resp.data;
                    render();
                    return;
                }
                // هم‌گام‌سازی ثانیهٔ شمارش معکوس
                if (resp.data.status === 'broadcasting') {
                    var s = parseInt(resp.data.broadcast_seconds_left, 10) || 0;
                    $('#broadcastCard').data('seconds', s);
                }
            }
        });
    }

    function startPolling() {
        stopPolling();
        pollTimer = window.setInterval(loadSilent, 4000);
        if (!tickTimer) {
            tickTimer = window.setInterval(tickBroadcast, 1000);
        }
    }

    function stopPolling() {
        if (pollTimer) { window.clearInterval(pollTimer); pollTimer = null; }
    }

    function stopTicking() {
        if (tickTimer) { window.clearInterval(tickTimer); tickTimer = null; }
    }

    /* شمارش معکوس محلی هر ثانیه */
    function tickBroadcast() {
        var card = $('#broadcastCard');
        if (card.hasClass('hidden')) { return; }

        var s = parseInt(String(card.data('seconds') || '0'), 10);
        if (s > 0) { s -= 1; card.data('seconds', s); }

        var numEl = document.getElementById('broadcastSeconds');
        var ring = document.getElementById('broadcastRing');
        var timer = document.getElementById('broadcastTimer');

        if (numEl) { numEl.textContent = CN.toFaDigits(s); }
        if (ring) {
            var total = Math.max(15, BROADCAST_TOTAL);
            var ratio = Math.min(1, s / total);
            ring.setAttribute('stroke-dasharray', String(RING_C));
            ring.setAttribute('stroke-dashoffset', String(RING_C * (1 - ratio)));
        }
        if (timer) { timer.classList.toggle('danger', s <= 10); }

        if (s <= 0) {
            window.setTimeout(loadSilent, 1200); // تعیین‌تکلیف تنبل سرور
        }
    }

    function render() {
        var o = order;

        /* سربرگ */
        $('#orderNumber').text(o.order_number);
        $('#orderStatusBadge').replaceWith(CN.statusBadge(o.status, o.status_label).replace('<span class="badge', '<span id="orderStatusBadge" class="badge'));
        $('#orderIcon').text(o.service ? o.service.icon || '📄' : '📄');
        $('#orderService').text(o.service ? o.service.name : '—');
        $('#orderDate').text(o.created_at_fa || '');

        /* کارت پرداخت — فاز ۱۲: accepted = فاکتور داخل چت؛ legacy pending_payment = کارت جدا */
        renderPayment();

        /* کارت لغو — تا قبل از پرداخت */
        var cancelable = ['pending_payment', 'broadcasting', 'queued', 'accepted'].indexOf(o.status) !== -1 && !o.is_paid;
        $('#cancelCard').toggleClass('hidden', !cancelable);

        /* ---------- فاز ۶ — کارت‌های تخصیص ---------- */
        renderAssignment(o);

        /* ---------- نظرسنجی پس از اتمام ---------- */
        renderSurvey(o);

        /* اعلان تغییر وضعیت (مثلاً پذیرش در حین تماشا) */
        if (lastStatus && lastStatus !== o.status) {
            if (o.status === 'accepted' && o.coffeenet) {
                CN.toast('هورا! ' + (o.operator ? 'اپراتور «' + o.operator.name + '» از ' : '') + 'کافی‌نت «' + o.coffeenet.name + '» به درخواست شما متصل شد؛ حالا پرداخت را انجام دهید.', 'success', 7000);
            } else if (o.status === 'queued') {
                CN.toast('مهلت پخش پایان یافت؛ درخواست به صف بررسی کارشناسان منتقل شد.', 'info', 6000);
            } else if (o.status === 'paid') {
                CN.toast('پرداخت ثبت شد؛ اپراتور کار شما را آغاز می‌کند.', 'success');
            } else if (o.status === 'in_progress') {
                CN.toast('کار روی درخواست شما آغاز شد.', 'success');
            }
        }
        lastStatus = o.status;

        /* زمان‌بندی */
        var timeline = '';
        var history = o.status_history || [];
        var currentShown = false;
        history.forEach(function (h, i) {
            var cls = i === 0 ? (currentShown ? '' : 'current') : 'done';
            if (i === 0) { currentShown = true; cls = 'current'; }
            timeline += '<div class="tl-item ' + cls + '">' +
                '<div class="tl-title">' + CN.esc(h.to_status_label || h.to_status) + '</div>' +
                (h.created_at_fa ? '<div class="tl-time">' + CN.esc(h.created_at_fa) + '</div>' : '') +
                (h.note ? '<div class="tl-note">' + CN.esc(h.note) + '</div>' : '') +
                '</div>';
        });
        if (!timeline.length) {
            timeline = '<div class="tl-item current"><div class="tl-title">' + CN.esc(o.status_label) + '</div></div>';
        }
        $('#timeline').html(timeline);

        /* خلاصه هزینه */
        var rows = '<div class="price-row"><span class="pr-title">💰 کارمزد خدمت</span><span class="pr-amount">' + CN.faMoney(o.price) + ' تومان</span></div>';
        if (o.expenses > 0) {
            rows += '<div class="price-row"><span class="pr-title">📦 هزینه‌های جانبی</span><span class="pr-amount">' + CN.faMoney(o.expenses) + ' تومان</span></div>';
        }
        rows += '<div class="price-row total"><span class="pr-title">مبلغ کل</span><span class="pr-amount">' + CN.faMoney(o.total_amount) + ' تومان</span></div>';
        $('#orderCostRows').html(rows);

        /* داده‌های فرم */
        var fdHtml = '';
        (o.form_data_display || []).forEach(function (row) {
            fdHtml += '<div class="data-row"><span class="data-key">' + CN.esc(row.label) + '</span><span class="data-val">' + CN.esc(row.value) + '</span></div>';
        });
        $('#orderFormData').html(fdHtml || '<p class="text-faint tiny">فرمی ثبت نشده است.</p>');

        /* دلیل لغو */
        $('#cancelReasonBox').toggleClass('hidden', !o.cancel_reason);
        $('#cancelReasonText').text(o.cancel_reason || '');

        /* مدارک */
        var files = o.files || [];
        $('#filesCard').toggleClass('hidden', !files.length);
        var fHtml = '';
        files.forEach(function (f) {
            fHtml += '<a class="btn btn-outline btn-sm btn-block" href="' + CN.esc(f.url) + '" target="_blank" rel="noopener">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>' +
                CN.esc(f.original_name) + ' <span class="tiny text-faint">(' + CN.toFaDigits(f.size_kb) + 'KB)</span></a>';
        });
        $('#filesList').html(fHtml);

        /* پرداخت‌ها */
        var payments = o.payments || [];
        $('#paymentsCard').toggleClass('hidden', !payments.length);
        var pHtml = '';
        payments.forEach(function (p) {
            pHtml += '<div class="data-row"><span class="data-key">' + CN.esc(p.driver_label) + (p.ref_id ? ' — ' + CN.esc(p.ref_id) : '') + '</span>' +
                '<span class="data-val">' + CN.faMoney(p.amount) + ' تومان · ' + CN.esc(p.status_label) + (p.paid_at_fa ? ' · ' + CN.esc(p.paid_at_fa) : '') + '</span></div>';
        });
        $('#paymentsList').html(pHtml);
    }

    /* ---------- نظرسنجی سفارش (پس از تحویل/تکمیل) ---------- */

    var surveyValue = 0;
    var surveySubmitting = false;
    var RATING_HINTS = {
        1: 'خیلی ضعیف بود 😞',
        2: 'ضعیف بود 🙁',
        3: 'متوسط بود 🙂',
        4: 'خوب بود 😊',
        5: 'عالی بود! 🤩'
    };

    function renderSurvey(o) {
        var done = ['delivered', 'completed'].indexOf(o.status) !== -1;
        var rated = !!(o.rating && o.rating.rating);

        $('#surveyCard').toggleClass('hidden', !done);
        if (!done) { return; }

        if (rated) {
            $('#surveyFormBox').addClass('hidden');
            $('#surveyDoneBox').removeClass('hidden');

            var stars = '';
            for (var i = 1; i <= 5; i++) {
                stars += '<svg class="s-done' + (i <= o.rating.rating ? '' : ' s-off') + '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg>';
            }
            $('#surveyDoneStars').html(stars);
            $('#surveyDoneComment').text(o.rating.comment ? '«' + o.rating.comment + '»' : (o.rating.rated_at_fa ? 'ثبت‌شده در ' + o.rating.rated_at_fa : ''));
        } else {
            $('#surveyFormBox').removeClass('hidden');
            $('#surveyDoneBox').addClass('hidden');
            $('#surveyIntro').text(o.status === 'delivered'
                ? 'سفارش شما تحویل شد! از تجربه‌تان چه امتیازی می‌دهید؟'
                : 'سفارش شما تکمیل شد! از تجربه‌تان چه امتیازی می‌دهید؟');
        }
    }

    function setSurveyStars(value) {
        surveyValue = value;
        $('#surveyStars .s-star').each(function () {
            var v = parseInt(this.dataset.value, 10) || 0;
            var on = v <= value;
            $(this).toggleClass('on', on);
            this.setAttribute('aria-checked', on && v === value ? 'true' : 'false');
        });
        var hint = $('#surveyRatingHint');
        if (value > 0) {
            hint.text(RATING_HINTS[value] || '').addClass('hint-on');
        } else {
            hint.text('امتیاز خود را انتخاب کنید').removeClass('hint-on');
        }
        $('#surveySubmitBtn').prop('disabled', value === 0);
    }

    $('#surveyStars').on('click', '.s-star', function () {
        setSurveyStars(parseInt(this.dataset.value, 10) || 0);
    });

    $('#surveyStars .s-star').on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            setSurveyStars(parseInt(this.dataset.value, 10) || 0);
        }
    });

    $('#surveySubmitBtn').on('click', function () {
        if (!surveyValue || surveySubmitting) { return; }

        surveySubmitting = true;
        var $btn = $(this);
        $btn.prop('disabled', true).text('در حال ثبت…');
        $('#surveyError').text('');

        CN.api('/orders/' + orderId + '/rating', {
            method: 'POST',
            data: {
                rating: surveyValue,
                comment: ($('#surveyComment').val() || '').trim() || null
            },
            success: function (resp) {
                surveySubmitting = false;
                CN.toast(resp.message || 'از بازخورد شما سپاسگزاریم.', 'success');
                order = resp.data || order;
                render();
            },
            error: function (xhr, message) {
                surveySubmitting = false;
                $btn.prop('disabled', false).text('ثبت نظرسنجی');
                $('#surveyError').text(message || 'ثبت نظرسنجی ناموفق بود.');
            }
        });
    });

    /* ---------- فاز ۶/۱۱/۱۲+: کارت‌های ارسال/صف — اتصال داخل چت نمایش داده می‌شود ---------- */
    function renderAssignment(o) {
        var broadcasting = o.status === 'broadcasting';
        var queued = o.status === 'queued';

        $('#broadcastCard').toggleClass('hidden', !broadcasting);
        $('#queuedCard').toggleClass('hidden', !queued);

        if (broadcasting) {
            var s = parseInt(o.broadcast_seconds_left, 10) || 0;
            BROADCAST_TOTAL = Math.max(15, s > 0 ? s : 60);
            $('#broadcastCard').data('seconds', s);

            var notes = [];
            if (o.broadcast_attempts > 1) {
                notes.push('دور ارسال ' + CN.toFaDigits(o.broadcast_attempts));
            }
            if (s <= 0) {
                notes.push('در حال تعیین‌تکلیف…');
            }
            $('#broadcastAttemptsNote').text(notes.join(' · '));
            $('#broadcastSeconds').text(CN.toFaDigits(s));

            var ring = document.getElementById('broadcastRing');
            if (ring) {
                ring.setAttribute('stroke-dasharray', String(RING_C));
                ring.setAttribute('stroke-dashoffset', String(RING_C * (1 - Math.min(1, s / BROADCAST_TOTAL))));
            }
            var timer = document.getElementById('broadcastTimer');
            if (timer) { timer.classList.toggle('danger', s <= 10); }

            startPolling();
        } else if (queued) {
            $('#queuedAtNote').text(o.queued_at_fa ? ('در صف از ' + o.queued_at_fa) : '');
            startPolling();
        } else if (['accepted', 'paid', 'in_progress', 'needs_info'].indexOf(o.status) !== -1) {
            // فاز ۱۱ — تا پایان چرخهٔ کار، صفحه زنده می‌ماند
            startPolling();
        } else {
            stopPolling();
            stopTicking();
        }
    }

    /* ---------- فاز ۱۲ — جایگذاری پرداخت: فاکتور داخل چت یا کارت جدا ---------- */
    function renderPayment() {
        if (!order) { return; }
        var o = order;

        var payAccepted = o.status === 'accepted' && !o.is_paid;           // جریان جدید: اتصال → پرداخت
        var payLegacy = o.status === 'pending_payment' && !o.is_paid;      // سفارش‌های قدیمی
        var inChat = payAccepted && chatCardVisible;                        // چت در دسترس → فاکتور داخل چت

        /* کارت جدا: فقط legacy یا حالت نادرِ accepted بدون چت */
        var showStandalone = payLegacy || (payAccepted && !chatCardVisible);
        $('#paymentCard').toggleClass('hidden', !showStandalone);
        $('#payConnectNote').toggleClass('hidden', !(showStandalone && payAccepted));
        if (showStandalone) {
            $('#payTotal').text(CN.faMoneyUnit(o.total_amount));
            var u = CN.user();
            $('#walletBalanceHint').text('(موجودی: ' + CN.faMoney((u && u.wallet_balance) || 0) + ')');
        }

        /* فاکتور داخل چت */
        var inv = $('#chatInvoice');
        var showPaidState = o.status === 'paid' && chatCardVisible;
        inv.toggleClass('hidden', !inChat && !showPaidState);

        if (inChat || showPaidState) {
            $('#invService').text(o.service ? o.service.name : 'سفارش ' + o.order_number);
            $('#invAmount').text(CN.faMoneyUnit(o.total_amount));
            $('#invPaidAmount').text(CN.faMoneyUnit(o.total_amount));
            var uw = CN.user();
            $('#invWalletHint').text('(موجودی: ' + CN.faMoney((uw && uw.wallet_balance) || 0) + ')');

            var paid = o.is_paid || showPaidState;
            inv.toggleClass('paid', !!paid);
            $('#invPaidAt').text(o.paid_at_fa || '');
            if (!paid) {
                $('#invState').text('در انتظار پرداخت');
                $('#invNote').text('برای شروع کار اپراتور، پرداخت را تکمیل کنید.');
            }
        }
    }

    /* فاز ۱۲ — اطلاع از دسترس‌پذیری کارت گفتگو (order-chat.js) */
    document.addEventListener('chat:visibility', function (e) {
        var v = !!(e && e.detail && e.detail.visible);
        if (v !== chatCardVisible) {
            chatCardVisible = v;
            renderPayment();
        }
    });

    /* ---------- پرداخت آنلاین (مشترک بین کارت جدا و فاکتور چت) ---------- */
    function payOnline($btn) {
        CN.btnLoading($btn, true, 'در حال اتصال به درگاه…');
        $('#payError').removeClass('show');
        $('#invError').removeClass('show');

        CN.api('/orders/' + orderId + '/pay', {
            method: 'POST',
            data: { method: 'online' },
            success: function (resp) {
                // مسیر نسبی → سازگار با گیت‌وی پیش‌نمایش و دامنهٔ واقعی
                var url = resp.payment_path
                    ? CN.withPort(resp.payment_path)
                    : (resp.payment_url || '');
                if (url) {
                    CN.toast(resp.message || 'انتقال به درگاه…', 'info', 1800);
                    window.setTimeout(function () {
                        window.location.href = url;
                    }, 500);
                } else {
                    CN.btnLoading($btn, false);
                }
            },
            error: function (xhr, message) {
                CN.btnLoading($btn, false);
                $('#payError').text(message).addClass('show');
                $('#invError').text(message).addClass('show');
            }
        });
    }

    /* ---------- پرداخت کیف پول (مشترک) ---------- */
    function payWallet($btn) {
        $('#payError').removeClass('show');
        $('#invError').removeClass('show');

        CN.confirm({
            title: 'پرداخت از کیف پول',
            desc: 'مبلغ ' + CN.faMoneyUnit(order ? order.total_amount : 0) + ' از موجودی کیف پول شما کسر می‌شود.',
            okText: 'پرداخت'
        }, function () {
            CN.btnLoading($btn, true, 'در حال پرداخت…');

            CN.api('/orders/' + orderId + '/pay', {
                method: 'POST',
                data: { method: 'wallet' },
                success: function (resp) {
                    CN.btnLoading($btn, false);
                    CN.toast(resp.message || 'پرداخت انجام شد.', 'success');
                    CN.refreshChrome();
                    load();
                },
                error: function (xhr, message) {
                    CN.btnLoading($btn, false);
                    $('#payError').text(message).addClass('show');
                    $('#invError').text(message).addClass('show');
                }
            });
        });
    }

    $('#payOnlineBtn').on('click', function () { payOnline($('#payOnlineBtn')); });
    $('#payWalletBtn').on('click', function () { payWallet($('#payWalletBtn')); });

    /* فاکتور داخل چت */
    $('#invPayOnline').on('click', function () { payOnline($('#invPayOnline')); });
    $('#invPayWallet').on('click', function () { payWallet($('#invPayWallet')); });

    /* ---------- لغو ---------- */
    $('#cancelOrderBtn').on('click', function () {
        CN.confirm({
            icon: '🗑',
            title: 'لغو درخواست',
            desc: 'آیا از لغو این سفارش مطمئن هستید؟ این عمل قابل بازگشت نیست.',
            okText: 'بله، لغو کن',
            danger: true
        }, function () {
            CN.api('/orders/' + orderId + '/cancel', {
                method: 'POST',
                data: { reason: 'لغو توسط مشتری از اپ' },
                success: function (resp) {
                    CN.toast(resp.message || 'درخواست لغو شد.', 'success');
                    load();
                }
            });
        });
    });

    load();
})(jQuery);
