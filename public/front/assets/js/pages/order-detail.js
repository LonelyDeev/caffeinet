/* اپ مشتری — جزئیات سفارش + پرداخت */
/* فاز ۳۲ — برازش ارتفاع پوستهٔ گفتگو با viewport واقعی (حالت نصب PWA) */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireCompleteProfile()) { return; }

    /* ---------- v32 — برازش ارتفاع چت تمام‌صفحه ----------
       در برخی گوشی‌ها در «حالت نصب‌شده» (PWA standalone) مقدار 100dvh بزرگ‌تر
       از پنجرهٔ واقعی گزارش می‌شود → نوار ارسال زیر صفحه می‌رود و body اسکرول
       می‌گیرد. ارتفاع را با innerHeight/visualViewport (سازگار با کیبورد مجازی)
       دقیق تنظیم می‌کنیم؛ 100dvh فقط fallback بدون-JS می‌ماند. */
    (function fitChatShell() {
        var shell = document.querySelector('.app-shell.chat-shell');
        if (!shell) { return; }

        var lastH = 0;

        function fit() {
            var h = window.innerHeight;
            var vv = window.visualViewport;

            // کیبورد مجازی: visualViewport کوچک‌تر می‌شود → نوار ارسال بالای کیبورد
            // (زمان زومِ scale≠1 مداخله نمی‌کنیم تا رفتار پینچ‌زوم طبیعی بماند)
            if (vv && Math.abs(vv.scale - 1) < 0.02) {
                h = Math.min(h, Math.round(vv.height));
            }

            if (h > 0 && h !== lastH) {
                lastH = h;
                shell.style.height = h + 'px';
                shell.style.minHeight = h + 'px';
            }
        }

        fit();
        window.addEventListener('resize', fit);
        window.addEventListener('orientationchange', function () { setTimeout(fit, 250); });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', fit);
        }
    })();

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

        /* v39 — ثانیه‌شمار به تصمیم مدیر خاموش است → فقط poll وضعیت کافی است */
        if ($('#broadcastTimer').hasClass('hidden')) { return; }

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

        /* کارت لغو — تا قبل از پرداخت (v31: بین «وضعیت‌های پیش از اتصال» و شیت اطلاعات جابه‌جا می‌شود) */
        placeCancel(o);

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

    /* ---------- نظرسنجی سفارش (پس از تحویل/تکمیل) — v33 کامل ---------- */

    var surveyValue = 0;
    var surveyOpValue = 0;
    var surveySubmitting = false;
    var surveyOptionsCache = null; // گزینه‌های دلایل (از API)
    var surveySelected = {}; // id → true
    var RATING_HINTS = {
        1: 'خیلی ضعیف بود 😞',
        2: 'ضعیف بود 🙁',
        3: 'متوسط بود 🙂',
        4: 'خوب بود 😊',
        5: 'عالی بود! 🤩'
    };

    function ratingOptions() {
        if (surveyOptionsCache !== null) {
            return $.Deferred().resolve(surveyOptionsCache);
        }
        var dfd = $.Deferred();
        CN.api('/rating-options', {
            success: function (resp) {
                surveyOptionsCache = (resp && resp.data) || [];
                dfd.resolve(surveyOptionsCache);
            },
            error: function () {
                surveyOptionsCache = [];
                dfd.resolve([]);
            }
        });
        return dfd;
    }

    function renderSurvey(o) {
        var done = ['delivered', 'completed'].indexOf(o.status) !== -1;
        var rated = !!(o.rating && o.rating.rating);

        $('#surveyCard').toggleClass('hidden', !done);
        if (!done) { return; }

        var hasOperator = !!(o.operator && o.operator.id);
        $('#surveyOpBox').toggleClass('hidden', !hasOperator);
        if (!hasOperator) { surveyOpValue = 0; }

        if (rated) {
            $('#surveyFormBox').addClass('hidden');
            $('#surveyDoneBox').removeClass('hidden');

            var stars = '';
            for (var i = 1; i <= 5; i++) {
                stars += '<svg class="s-done' + (i <= o.rating.rating ? '' : ' s-off') + '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg>';
            }
            $('#surveyDoneStars').html(stars);

            // امتیاز اپراتور
            if (o.rating.operator_rating) {
                var opStars = '';
                for (var j = 1; j <= 5; j++) {
                    opStars += '<svg class="s-done' + (j <= o.rating.operator_rating ? '' : ' s-off') + '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.26L21.5 9.27l-5 4.87 1.18 6.88L12 17.77l-5.68 3.25 1.18-6.88-5-4.87 6.6-3.01Z"/></svg>';
                }
                $('#surveyDoneOpStars').html('<span class="s-done-label">اپراتور</span>' + opStars).removeClass('hidden');
            } else {
                $('#surveyDoneOpStars').addClass('hidden').html('');
            }

            // دلایل انتخابی (اسنپ‌شات)
            var opts = o.rating.options || [];
            $('#surveyDoneOptions').html(opts.length
                ? opts.map(function (op) {
                    return '<span class="s-done-chip' + (op.type === 'neg' ? ' s-done-chip--neg' : '') + '">' + escapeHtmlFa(op.title) + '</span>';
                }).join('')
                : '');

            $('#surveyDoneComment').text(o.rating.comment ? '«' + o.rating.comment + '»' : (o.rating.rated_at_fa ? 'ثبت‌شده در ' + o.rating.rated_at_fa : ''));
        } else {
            $('#surveyFormBox').removeClass('hidden');
            $('#surveyDoneBox').addClass('hidden');
            $('#surveyIntro').text(o.status === 'delivered'
                ? 'سفارش شما تحویل شد! از تجربه‌تان چه امتیازی می‌دهید؟'
                : 'سفارش شما تکمیل شد! از تجربه‌تان چه امتیازی می‌دهید؟');

            // گزینه‌های دلایل را از قبل بارگذاری کن تا با اولین تیک آماده باشد
            ratingOptions();
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
        updateSurveyOptions();
        updateSurveySubmit();
    }

    function setSurveyOpStars(value) {
        surveyOpValue = value;
        $('#surveyOpStars .s-star').each(function () {
            var v = parseInt(this.dataset.value, 10) || 0;
            var on = v <= value;
            $(this).toggleClass('on', on);
            this.setAttribute('aria-checked', on && v === value ? 'true' : 'false');
        });
        updateSurveySubmit();
    }

    /* دلایل متناسب با امتیاز: ۴/۵ → نقاط قوت، ۱/۲ → نقاط ضعف، ۳ → هر دو */
    function surveyWantedTypes() {
        if (surveyValue >= 4) { return ['pos']; }
        if (surveyValue > 0 && surveyValue <= 2) { return ['neg']; }
        if (surveyValue === 3) { return ['pos', 'neg']; }
        return [];
    }

    function updateSurveyOptions() {
        var box = $('#surveyOptionsBox');
        var types = surveyWantedTypes();

        if (!types.length) {
            box.addClass('hidden');
            surveySelected = {};
            renderSurveyOptionsList([]);
            return;
        }

        ratingOptions().done(function (options) {
            var filtered = (options || []).filter(function (o) {
                return types.indexOf(o.type) !== -1;
            });

            // انتخاب‌های خارج از نوع (مثلاً بعد از تغییر ستاره) پاک شود
            var keep = {};
            filtered.forEach(function (o) { if (surveySelected[o.id]) { keep[o.id] = true; } });
            surveySelected = keep;

            box.removeClass('hidden');
            $('#surveyOptionsTitle').text(
                surveyValue >= 4 ? 'چه چیزهایی خوب بود؟ (اختیاری)'
                    : (surveyValue <= 2 ? 'چه چیزهایی ضعیف بود؟ (اختیاری)'
                        : 'چه چیزهایی را بیشتر دوست داشتید یا نبود؟ (اختیاری)')
            );
            renderSurveyOptionsList(filtered);
        });
    }

    function renderSurveyOptionsList(options) {
        var box = $('#surveyOptions');
        if (!options.length) {
            box.html('<p class="tiny text-faint text-center" style="padding:6px 0">گزینه‌ای برای این امتیاز ثبت نشده است.</p>');
            return;
        }
        box.html(options.map(function (o) {
            var checked = !!surveySelected[o.id];
            return '<button type="button" class="s-opt' + (checked ? ' on' : '') + (o.type === 'neg' ? ' s-opt--neg' : '') + '" data-id="' + o.id + '" role="checkbox" aria-checked="' + (checked ? 'true' : 'false') + '">' +
                '<span class="s-opt-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>' +
                '<span class="s-opt-title">' + escapeHtmlFa(o.title) + '</span>' +
                '</button>';
        }).join(''));
    }

    $('#surveyOptions').on('click', '.s-opt', function () {
        var id = parseInt(this.dataset.id, 10) || 0;
        if (!id) { return; }
        if (surveySelected[id]) {
            delete surveySelected[id];
        } else {
            surveySelected[id] = true;
        }
        $(this).toggleClass('on', !!surveySelected[id]);
        this.setAttribute('aria-checked', surveySelected[id] ? 'true' : 'false');
    });

    function updateSurveySubmit() {
        $('#surveySubmitBtn').prop('disabled', surveyValue === 0);
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

    $('#surveyOpStars').on('click', '.s-star', function () {
        // کلیک دوباره روی همان ستاره = حذف امتیاز اپراتور (اختیاری)
        var v = parseInt(this.dataset.value, 10) || 0;
        setSurveyOpStars(surveyOpValue === v ? 0 : v);
    });

    $('#surveyOpStars .s-star').on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            var v = parseInt(this.dataset.value, 10) || 0;
            setSurveyOpStars(surveyOpValue === v ? 0 : v);
        }
    });

    $('#surveySubmitBtn').on('click', function () {
        if (!surveyValue || surveySubmitting) { return; }

        surveySubmitting = true;
        var $btn = $(this);
        $btn.prop('disabled', true).text('در حال ثبت…');
        $('#surveyError').text('');

        var selectedIds = Object.keys(surveySelected).map(function (k) { return parseInt(k, 10); });
        var payload = {
            rating: surveyValue,
            comment: ($('#surveyComment').val() || '').trim() || null
        };
        if (surveyOpValue > 0) { payload.operator_rating = surveyOpValue; }
        if (selectedIds.length) { payload.options = selectedIds; }

        CN.api('/orders/' + orderId + '/rating', {
            method: 'POST',
            data: payload,
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

    function escapeHtmlFa(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- فاز ۶/۱۱/۱۲+: کارت‌های ارسال/صف — اتصال داخل چت نمایش داده می‌شود ---------- */

    /* v40 — راه‌های ارتباطی مشتری (نمایش پس از پایان مهلت پخش بدون پذیرش)
       مدل جدید: «تماس تلفنی» چک‌باکس مستقل است (تیکش قابل برداشتن) و «چت»
       انتخاب یگانه از پیام‌رسان‌ها؛ ثبت نهایی با دکمهٔ «ثبت انتخاب من». */
    var CHAT_PREFS = [
        { value: 'app_chat', label: 'چت داخل برنامه', icon: '💬', desc: 'گفتگو در همین برنامه' },
        { value: 'telegram', label: 'تلگرام', icon: '✈️', desc: 'پیام از طریق تلگرام' },
        { value: 'whatsapp', label: 'واتس‌اپ', icon: '🟢', desc: 'پیام از طریق واتس‌اپ' },
        { value: 'bale', label: 'بله', icon: '🔵', desc: 'پیام از طریق بله' },
        { value: 'eitaa', label: 'ایتا', icon: '📨', desc: 'پیام از طریق ایتا' },
        { value: 'any', label: 'فرقی ندارد', icon: '🤝', desc: 'هر راهی که راحت‌تر است' }
    ];

    var savedPreference = null; // مقدار ثبت‌شدهٔ کاربر (رشتهٔ ترکیبی مثل «call,telegram»)

    /** تجزیهٔ «call,telegram» → {call:true, chat:'telegram'} */
    function parsePreference(raw) {
        var out = { call: false, chat: null };
        String(raw || '').split(',').forEach(function (token) {
            token = token.trim();
            if (!token) { return; }
            if (token === 'call') { out.call = true; return; }
            for (var i = 0; i < CHAT_PREFS.length; i++) {
                if (CHAT_PREFS[i].value === token) { out.chat = token; return; }
            }
        });
        return out;
    }

    function chatLabel(value) {
        for (var i = 0; i < CHAT_PREFS.length; i++) {
            if (CHAT_PREFS[i].value === value) { return CHAT_PREFS[i]; }
        }
        return null;
    }

    /** آیا دکمهٔ ثبت باید فعال باشد؟ (تماس تیک‌خورده یا چت انتخاب‌شده) */
    function cprefSelection() {
        return {
            call: $('#cprefCallChk').prop('checked'),
            chat: $('#contactPrefGrid .cpref-item.active').data('pref') || null
        };
    }

    function updateCprefSaveBtn() {
        var sel = cprefSelection();
        var changed = !savedPreference
            || parsePreference(savedPreference).call !== sel.call
            || parsePreference(savedPreference).chat !== sel.chat;
        $('#contactPrefSave').prop('disabled', !(sel.call || sel.chat) || !changed);
        $('#contactPrefError').removeClass('show').text('');
    }

    function renderContactPrefs(selected) {
        var $box = $('#contactPrefBox');
        var $grid = $('#contactPrefGrid');
        var $saved = $('#contactPrefSaved');

        if (selected) { savedPreference = selected; }

        var parsed = parsePreference(savedPreference);
        var selectedChat = parsed.chat;

        /* بدون انتخاب ثبت‌شده → تماس تلفنی به‌صورت پیش‌فرض تیک‌خورده است
           (کاربر می‌تواند تیکش را بردارد — درخواست مالک v40) */
        var callChecked = savedPreference ? parsed.call : true;
        $('#cprefCallChk').prop('checked', callChecked);
        $('#cprefCallChk').closest('.cpref-call').toggleClass('is-checked', callChecked);

        var html = '';
        CHAT_PREFS.forEach(function (p) {
            var active = selectedChat === p.value;
            html += '<button type="button" class="cpref-item' + (active ? ' active' : '') + '" data-pref="' + p.value + '"' +
                ' role="radio" aria-checked="' + (active ? 'true' : 'false') + '" title="' + CN.esc(p.desc) + '">' +
                '<span class="cpref-item-ico" aria-hidden="true">' + p.icon + '</span>' +
                '<span class="cpref-item-label">' + p.label + '</span>' +
                (active ? '<span class="cpref-check" aria-hidden="true">✓</span>' : '') +
                '</button>';
        });
        $grid.html(html);

        if (savedPreference) {
            var parts = [];
            if (parsed.call) { parts.push('📞 تماس تلفنی'); }
            if (parsed.chat) {
                var meta = chatLabel(parsed.chat);
                parts.push((meta ? meta.icon + ' ' + meta.label : parsed.chat));
            }
            $saved.removeClass('hidden').text('✓ راه ارتباطی شما: ' + parts.join(' + ') + ' — کارشناسان ما از همین راه با شما در تماس می‌شوند.');
        } else {
            $saved.addClass('hidden').text('');
        }

        $box.removeAttr('hidden');
        updateCprefSaveBtn();
    }

    /* تیک تماس تلفنی */
    $(document).off('change', '#cprefCallChk').on('change', '#cprefCallChk', function () {
        $(this).closest('.cpref-call').toggleClass('is-checked', $(this).prop('checked'));
        updateCprefSaveBtn();
    });

    /* انتخاب یکی از راه‌های چت (رادیو) */
    $(document).off('click', '#contactPrefGrid .cpref-item').on('click', '#contactPrefGrid .cpref-item', function () {
        $('#contactPrefGrid .cpref-item').removeClass('active').attr('aria-checked', 'false');
        $('#contactPrefGrid .cpref-item .cpref-check').remove();
        $(this).addClass('active').attr('aria-checked', 'true').append('<span class="cpref-check" aria-hidden="true">✓</span>');
        updateCprefSaveBtn();
    });

    /* ثبت انتخاب */
    $(document).off('click', '#contactPrefSave').on('click', '#contactPrefSave', function () {
        var sel = cprefSelection();

        if (!sel.call && !sel.chat) {
            $('#contactPrefError').addClass('show').text('حداقل «تماس تلفنی» یا یکی از راه‌های چت را انتخاب کنید.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('در حال ثبت…');

        CN.api('/orders/' + orderId + '/contact-preference', {
            method: 'POST',
            data: { call: sel.call, chat: sel.chat },
            success: function (resp) {
                $btn.prop('disabled', false).text('ثبت انتخاب من');
                savedPreference = resp.preference || null;
                renderContactPrefs(savedPreference);
                CN.toast(resp.message || 'انتخاب شما ثبت شد.', 'success');
            },
            error: function (xhr, message) {
                $btn.prop('disabled', false).text('ثبت انتخاب من');
                var errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                if (errors.chat && errors.chat.length) {
                    $('#contactPrefError').addClass('show').text(errors.chat[0]);
                } else if (message) {
                    CN.toast(message, 'error');
                }
            }
        });
    });

    function renderAssignment(o) {
        var broadcasting = o.status === 'broadcasting';
        var queued = o.status === 'queued';

        $('#broadcastCard').toggleClass('hidden', !broadcasting);
        $('#queuedCard').toggleClass('hidden', !queued);

        /* v39 — متن‌ها و ثانیه‌شمار از تصمیم مدیر (تنظیمات سفارش‌ها) */
        var timerEnabled = o.broadcast_timer_enabled !== false; // پیش‌فرض: روشن
        $('#broadcastTimer').toggleClass('hidden', !timerEnabled);
        if (o.broadcast_text) {
            $('#broadcastDesc').html(CN.esc(o.broadcast_text).replace(/\n/g, '<br>'));
        }
        if (o.queued_text) {
            $('#queuedDesc').html(CN.esc(o.queued_text).replace(/\n/g, '<br>'));
        }

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

            /* v39 — انتخاب راه ارتباطی (فقط وقتی مهلت تمام شده و اپراتوری قبول نکرده) */
            renderContactPrefs(o.contact_preference || null);

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

    /* ---------- v31 — جایگذاری کارت لغو ----------
       وضعیت‌های پیش از اتصال (پخش/صف/پرداخت legacy): زیر کارت وضعیت داخل ناحیهٔ پیام‌ها؛
       گفتگوی فعال (accepted): پایین شیت «اطلاعات سفارش». DOM با appendTo جابه‌جا می‌شود
       تا شنونده‌های مستقیم دکمه حفظ شوند. */
    function placeCancel(o) {
        var cancelable = ['pending_payment', 'broadcasting', 'queued', 'accepted'].indexOf(o.status) !== -1 && !o.is_paid;
        var $card = $('#cancelCard');

        $card.toggleClass('hidden', !cancelable);
        if (!cancelable) { return; }

        var preChat = ['pending_payment', 'broadcasting', 'queued'].indexOf(o.status) !== -1;
        var $target = preChat ? $('#stateActions') : $('#chatinfoActions');
        if ($target.length && !$card.parent().is($target)) {
            $card.appendTo($target);
        }
    }

    /* ---------- v31 — شیت «اطلاعات سفارش» (روند/خلاصه/مدارک/تاریخچه پرداخت) ---------- */
    function openInfoSheet() {
        $('#chatinfoBackdrop').addClass('show').attr('aria-hidden', 'false');
        $('#chatinfoSheet').addClass('open');
        $('#orderInfoBtn').attr('aria-expanded', 'true');
    }

    function closeInfoSheet() {
        $('#chatinfoBackdrop').removeClass('show').attr('aria-hidden', 'true');
        $('#chatinfoSheet').removeClass('open');
        $('#orderInfoBtn').attr('aria-expanded', 'false');
    }

    $('#orderInfoBtn').on('click', openInfoSheet);
    $('#chatinfoClose').on('click', closeInfoSheet);
    $('#chatinfoBackdrop').on('click', closeInfoSheet);

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

    /* ---------- لغو (با دلیل اجباری — فاز ۲۳) ---------- */
    var cancelSubmitting = false;
    var MIN_REASON = 5;

    function openCancelSheet() {
        closeInfoSheet(); /* v31 — شیت اطلاعات بسته شود تا دو شیت روی هم نیفتند */

        /* ریست وضعیت شیت */
        $('#cancelReasonInput').val('').removeClass('invalid');
        $('#cancelReasonInputError').removeClass('show').text('');
        $('#cancelReasonChips .chip').removeClass('active');
        $('#cancelConfirmBtn').prop('disabled', true);
        cancelSubmitting = false;

        $('#cancelBackdrop').addClass('show').attr('aria-hidden', 'false');
        $('#cancelSheet').addClass('open');
    }

    function closeCancelSheet() {
        $('#cancelBackdrop').removeClass('show').attr('aria-hidden', 'true');
        $('#cancelSheet').removeClass('open');
    }

    function reasonValid() {
        var v = ($('#cancelReasonInput').val() || '').trim();
        return v.length >= MIN_REASON;
    }

    function refreshCancelState(showError) {
        var ok = reasonValid();
        $('#cancelConfirmBtn').prop('disabled', !ok || cancelSubmitting);

        if (!ok && showError) {
            var v = ($('#cancelReasonInput').val() || '').trim();
            CN.fieldError('cancelReasonInput', v
                ? ('دلیل لغو باید حداقل ' + CN.toFaDigits(MIN_REASON) + ' نویسه باشد.')
                : 'انتخاب یا نوشتن دلیل لغو الزامی است.');
        }
        return ok;
    }

    $('#cancelOrderBtn').on('click', openCancelSheet);
    $('#cancelSheetClose').on('click', closeCancelSheet);
    $('#cancelGiveupBtn').on('click', closeCancelSheet);
    $('#cancelBackdrop').on('click', closeCancelSheet);

    /* چیپ دلیل: انتخاب → متن داخل textarea (قابل ویرایش) */
    $('#cancelReasonChips').on('click', '.chip', function () {
        var $chip = $(this);
        var wasActive = $chip.hasClass('active');

        $('#cancelReasonChips .chip').removeClass('active');
        if (wasActive) {
            /* کلیک دوباره = برداشتن انتخاب */
            $('#cancelReasonInput').val('');
        } else {
            $chip.addClass('active');
            $('#cancelReasonInput').val($chip.data('reason') || '');
        }
        $('#cancelReasonInput').removeClass('invalid');
        $('#cancelReasonInputError').removeClass('show').text('');
        refreshCancelState(false);
    });

    $('#cancelReasonInput').on('input', function () {
        /* ویرایش دستی → انتخاب چیپ برداشته می‌شود */
        var chipText = ($('#cancelReasonChips .chip.active').data('reason') || '');
        if (chipText && $(this).val() !== chipText) {
            $('#cancelReasonChips .chip').removeClass('active');
        }
        refreshCancelState(false);
    });

    $('#cancelSheet').on('submit', function (e) { e.preventDefault(); });

    $('#cancelConfirmBtn').on('click', function () {
        if (cancelSubmitting || !refreshCancelState(true)) { return; }

        var reason = ($('#cancelReasonInput').val() || '').trim();
        cancelSubmitting = true;
        CN.btnLoading($('#cancelConfirmBtn'), true, 'در حال لغو…');

        CN.api('/orders/' + orderId + '/cancel', {
            method: 'POST',
            data: { reason: reason },
            success: function (resp) {
                cancelSubmitting = false;
                CN.btnLoading($('#cancelConfirmBtn'), false);
                closeCancelSheet();
                CN.toast(resp.message || 'درخواست لغو شد.', 'success');
                load();
            },
            error: function (xhr, message) {
                cancelSubmitting = false;
                CN.btnLoading($('#cancelConfirmBtn'), false);
                /* خطای فیلد reason روی textarea؛ بقیه روی توست */
                var errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                if (errors.reason && errors.reason.length) {
                    CN.fieldError('cancelReasonInput', errors.reason[0]);
                } else {
                    CN.toast(message || 'لغو سفارش ناموفق بود.', 'error');
                }
            }
        });
    });

    /* بستن شیت‌ها با Escape */
    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        if ($('#cancelSheet').hasClass('open')) { closeCancelSheet(); }
        else if ($('#chatinfoSheet').hasClass('open')) { closeInfoSheet(); }
    });

    load();
})(jQuery);
