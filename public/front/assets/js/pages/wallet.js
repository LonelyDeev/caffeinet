/* اپ مشتری — کیف پول */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireCompleteProfile()) { return; }

    var state = { page: 1, hasMore: false, loading: false };

    function load() {
        if (state.loading) { return; }
        state.loading = true;

        var apiPath = '/wallet' + (state.page > 1 ? '?page=' + state.page : '');

        if (state.page === 1) {
            $('#txList').html('<div class="skeleton" style="height:60px"></div><div class="skeleton" style="height:60px"></div>');
        } else {
            $('#txMoreLoader').removeClass('hidden');
        }

        CN.api(apiPath, {
            success: function (resp) {
                state.loading = false;

                if (state.page === 1) {
                    $('#txList').empty();
                }
                $('#txMoreLoader').addClass('hidden');

                $('#walletBalance').text(CN.faMoney(resp.balance || 0));
                var u = CN.user();
                if (u) {
                    u.wallet_balance = resp.balance || 0;
                    try { window.localStorage.setItem('cn_user', JSON.stringify(u)); } catch (e) { /* noop */ }
                }
                $('#walletMobile').text('شماره حساب: ' + (CN.toFaDigits((u && u.mobile) || '')));
                $('#txCount').text(resp.transactions ? CN.toFaDigits(resp.transactions.total || 0) + ' تراکنش' : '');

                var txs = (resp.transactions && resp.transactions.data) || [];
                var html = '';
                txs.forEach(function (t) {
                    html += txRow(t);
                });
                $('#txList').append(html);

                state.hasMore = !!(resp.transactions && resp.transactions.next_page_url);
                $('#txLoadMore').toggleClass('hidden', !state.hasMore);

                var empty = !txs.length && state.page === 1;
                $('#txEmpty').toggleClass('hidden', !empty);
                $('#txList').toggleClass('hidden', empty);
            },
            error: function () {
                state.loading = false;
                $('#txMoreLoader').addClass('hidden');
            }
        });
    }

    function txRow(t) {
        var isCredit = t.type === 'credit';

        return '<div class="tx-row">' +
            '<span class="tx-icon ' + (isCredit ? 'credit' : 'debit') + '">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            (isCredit
                ? '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>'
                : '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>') +
            '</svg></span>' +
            '<span class="tx-body">' +
            '<span class="tx-title">' + CN.esc(t.description || (isCredit ? 'واریز' : 'برداشت')) + '</span>' +
            '<span class="tx-time">' + CN.esc(t.created_at_fa || '') + ' · موجودی پس از تراکنش: ' + CN.faMoney(t.balance_after) + '</span>' +
            '</span>' +
            '<span class="tx-amount ' + (isCredit ? 'credit' : 'debit') + '">' + (isCredit ? '+' : '−') + CN.faMoney(t.amount) + '</span>' +
            '</div>';
    }

    $('#txLoadMore').on('click', function () {
        if (!state.hasMore || state.loading) { return; }
        state.page++;
        load();
    });

    /* ---------- شارژ کیف پول از درگاه ---------- */
    var selectedAmount = 0;

    /* پیش‌نمایش مبلغ انتخابی با جداکنندهٔ هزارگان (۳رقمی) — v24 */
    function updateAmountPreview() {
        var $p = $('#chargePreview');
        if (selectedAmount > 0) {
            $p.html('<span class="cap-label">مبلغ انتخابی</span><strong class="num">' + CN.faMoney(selectedAmount) + '</strong><span class="cap-unit">تومان</span>').removeClass('hidden');
        } else {
            $p.addClass('hidden').empty();
        }
    }

    function resetAmountUI() {
        selectedAmount = 0;
        $('#quickAmounts .charge-amt').removeClass('active');
        $('#chargeCustom').val('');
        updateAmountPreview();
    }

    function openSheet() {
        $('#chargeOverlay').addClass('show').attr('aria-hidden', 'false');
        $('#chargeSheet').addClass('open');
        hideChargeError();
        updateAmountPreview();
    }

    function closeSheet() {
        $('#chargeOverlay').removeClass('show').attr('aria-hidden', 'true');
        $('#chargeSheet').removeClass('open');
    }

    function showChargeError(msg) {
        $('#chargeError').text(msg).show();
    }

    function hideChargeError() {
        $('#chargeError').hide();
    }

    $('#chargeBtn').on('click', openSheet);
    $('#chargeClose').on('click', closeSheet);
    $('#chargeOverlay').on('click', closeSheet);

    $('#quickAmounts').on('click', '.charge-amt', function () {
        $('#quickAmounts .charge-amt').removeClass('active');
        $(this).addClass('active');
        selectedAmount = +$(this).data('amount') || 0;
        $('#chargeCustom').val('');
        updateAmountPreview();
        hideChargeError();
    });

    $('#chargeCustom').on('input', function () {
        var raw = CN.toEnDigits($(this).val()).replace(/[^\d]/g, '');
        if (raw) {
            $('#quickAmounts .charge-amt').removeClass('active');
            selectedAmount = parseInt(raw, 10) || 0;
        } else if (!$('#quickAmounts .charge-amt.active').length) {
            /* ورودی خالی و چیپی هم انتخاب نیست → مقدار صفر */
            selectedAmount = 0;
        }
        updateAmountPreview();
        hideChargeError();
    });

    $('#chargeSubmit').on('click', function () {
        var $btn = $(this);

        if (!selectedAmount || selectedAmount < 10000) {
            showChargeError('مبلغ را انتخاب یا وارد کنید (حداقل ۱۰,۰۰۰ تومان).');
            return;
        }
        if (selectedAmount > 50000000) {
            showChargeError('حداکثر مبلغ شارژ ۵۰,۰۰۰,۰۰۰ تومان است.');
            return;
        }

        hideChargeError();
        CN.btnLoading($btn, true, 'در حال اتصال به درگاه…');

        CN.api('/wallet/charge', {
            method: 'POST',
            data: { amount: selectedAmount },
            success: function (resp) {
                // مسیر نسبی → سازگار با گیت‌وی پیش‌نمایش و دامنهٔ واقعی
                var url = resp.payment_path
                    ? CN.withPort(resp.payment_path)
                    : (resp.payment_url || '');
                if (url) {
                    window.location.href = url;
                } else {
                    CN.btnLoading($btn, false);
                    showChargeError('خطا در ایجاد تراکنش؛ دوباره تلاش کنید.');
                }
            },
            error: function (xhr, message) {
                CN.btnLoading($btn, false);
                showChargeError(message || 'خطا در شروع شارژ؛ دوباره تلاش کنید.');
            }
        });
    });

    /* ---------- پیام نتیجهٔ بازگشت از درگاه (?charged=1 / ?payment=failed) ---------- */
    try {
        var query = Object.fromEntries(new URLSearchParams(window.location.search));
        if (query.charged === '1') {
            CN.toast('کیف پول شما با موفقیت شارژ شد ✓', 'success', 3200);
            history.replaceState(null, '', CN.withPort('/app/wallet'));
        } else if (query.payment === 'failed') {
            CN.toast('پرداخت شارژ ناموفق بود؛ دوباره تلاش کنید.', 'error', 3200);
            history.replaceState(null, '', CN.withPort('/app/wallet'));
        }
    } catch (e) { /* noop */ }

    load();
})(jQuery);
