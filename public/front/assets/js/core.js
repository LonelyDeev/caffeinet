/* ============================================================
   کافی‌نت آنلاین — هسته اپ مشتری (jQuery)
   بدون وابستگی به Node/بیلد — فایل مستقل لینک‌شده به صفحات
   ============================================================ */
/* global jQuery */
window.CN = (function ($) {
    'use strict';

    /* ---------- پیکربندی گیت‌وی پیش‌نمایش ---------- */
    var PORT_PARAM = 'XTransformPort';
    var port = '';
    try {
        port = new URLSearchParams(window.location.search).get(PORT_PARAM) || '';
    } catch (e) { port = ''; }

    /** افزودن پارامتر گیت‌وی به مسیر داخلی */
    function withPort(url) {
        if (!port) { return url; }
        if (url.indexOf(PORT_PARAM + '=') !== -1) { return url; }
        return url + (url.indexOf('?') === -1 ? '?' : '&') + PORT_PARAM + '=' + port;
    }

    /** مسیر API (همیشه با پارامتر گیت‌وی) */
    function apiUrl(path) {
        return withPort('/api/v1' + path);
    }

    /* ---------- نشست (توکن Sanctum) ---------- */
    var TOKEN_KEY = 'cn_token';
    var USER_KEY = 'cn_user';

    function token() {
        try { return window.localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; }
    }

    function user() {
        try { return JSON.parse(window.localStorage.getItem(USER_KEY) || 'null'); } catch (e) { return null; }
    }

    function setSession(newToken, newUser) {
        try {
            window.localStorage.setItem(TOKEN_KEY, newToken);
            window.localStorage.setItem(USER_KEY, JSON.stringify(newUser));
        } catch (e) { /* حافظه محدود */ }
    }

    function clearSession() {
        try {
            window.localStorage.removeItem(TOKEN_KEY);
            window.localStorage.removeItem(USER_KEY);
        } catch (e) { /* noop */ }
    }

    /** گارد صفحات محافظت‌شده */
    function requireAuth() {
        if (!token()) {
            window.location.replace(withPort('/app/auth'));
            return false;
        }
        return true;
    }

    /* ---------- لایه API ---------- */
    function extractMessage(xhr) {
        var data = null;
        try {
            data = typeof xhr.responseText === 'string' ? JSON.parse(xhr.responseText) : xhr.responseText;
        } catch (e) { data = null; }

        if (data && data.message) {
            if (data.errors) {
                for (var key in data.errors) {
                    if (Object.prototype.hasOwnProperty.call(data.errors, key)) {
                        var list = data.errors[key];
                        if (list && list.length) { return list[0]; }
                    }
                }
            }
            return data.message;
        }
        return 'خطای غیرمنتظره؛ دوباره تلاش کنید.';
    }

    /**
     * فراخوانی API.
     * opts: { method, data(json), formData, success(resp), error(xhr, message) }
     */
    function api(path, opts) {
        opts = opts || {};

        var conf = {
            url: apiUrl(path),
            method: opts.method || 'GET',
            headers: { 'Accept': 'application/json' },
            dataType: 'json'
        };

        if (token()) {
            conf.headers['Authorization'] = 'Bearer ' + token();
        }

        if (opts.formData) {
            conf.data = opts.formData;
            conf.processData = false;
            conf.contentType = false;
        } else if (typeof opts.data !== 'undefined') {
            conf.data = JSON.stringify(opts.data);
            conf.contentType = 'application/json';
        }

        conf.success = function (resp) {
            if (opts.success) { opts.success(resp); }
        };

        conf.error = function (xhr) {
            var message = extractMessage(xhr);

            if (xhr.status === 401) {
                clearSession();
                toast('نشست شما منقضی شده؛ دوباره وارد شوید.', 'error');
                window.setTimeout(function () {
                    window.location.replace(withPort('/app/auth'));
                }, 1100);
                return;
            }

            if (opts.error) {
                opts.error(xhr, message);
            } else if (message) {
                toast(message, 'error');
            }
        };

        if (opts.complete) {
            conf.complete = function (xhr) {
                if (xhr && xhr.status === 401 && opts.error === undefined) { return; }
                opts.complete(xhr);
            };
        }

        $.ajax(conf);
    }

    /* ---------- قالب‌بندی فارسی ---------- */
    var FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    function toFaDigits(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[0-9]/g, function (d) {
            return FA_DIGITS[+d];
        });
    }

    function toEnDigits(value) {
        var fa = '۰۱۲۳۴۵۶۷۸۹';
        var ar = '٠١٢٣٤٥٦٧٨٩';
        return String(value || '').replace(/[۰-۹٠-٩]/g, function (d) {
            var i = fa.indexOf(d);
            if (i === -1) { i = ar.indexOf(d); }
            return String(i);
        });
    }

    /** مبلغ با جداکننده هزارگان فارسی (بدون واحد) */
    function faMoney(amount) {
        var n = Math.round(Number(amount || 0));
        var sign = n < 0 ? '−' : '';
        n = Math.abs(n);
        var withSep = String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return sign + toFaDigits(withSep);
    }

    /** مبلغ کامل با واحد تومان */
    function faMoneyUnit(amount) {
        return faMoney(amount) + ' تومان';
    }

    /** escape HTML برای ایمنی رندر */
    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /** نرمال‌سازی موبایل (ارقام فارسی/عربی، +98) */
    function normalizeMobile(mobile) {
        var m = toEnDigits(String(mobile || '')).trim();
        m = m.replace(/^\+/, '');
        if (m.indexOf('0098') === 0) { m = '0' + m.slice(4); }
        else if (m.indexOf('98') === 0 && m.length === 12) { m = '0' + m.slice(2); }
        return m;
    }

    /* ---------- توست ---------- */
    var ICONS = { success: '✓', error: '✕', info: 'ℹ' };

    function toast(message, type, duration) {
        type = type || 'info';
        duration = duration || 3600;

        var $wrap = $('#toastWrap');
        if (!$wrap.length) { return; }

        var $t = $('<div class="toast toast-' + type + '" role="status"><span class="t-icon">' + (ICONS[type] || 'ℹ') + '</span><span></span></div>');
        $t.find('span:last').text(message);
        $wrap.append($t);

        window.setTimeout(function () {
            $t.addClass('out');
            window.setTimeout(function () { $t.remove(); }, 350);
        }, duration);
    }

    /* ---------- دکمه در حال بارگذاری ---------- */
    function btnLoading($btn, loading, loadingText) {
        if (!$btn || !$btn.length) { return; }
        if (loading) {
            if (!$btn.data('cn-html')) { $btn.data('cn-html', $btn.html()); }
            $btn.prop('disabled', true).css('opacity', 0.65);
            $btn.html('<span class="spinner"></span>' + (loadingText ? ' ' + esc(loadingText) : ''));
        } else {
            $btn.prop('disabled', false).css('opacity', '');
            var original = $btn.data('cn-html');
            if (original) { $btn.html(original); }
        }
    }

    /* ---------- خطای فیلد ---------- */
    /** یافتن عنصر خطای متناظر: #err_name / #ferr_name / #nameError / #name */
    function errorEl(name) {
        var selectors = ['#err_' + name, '#ferr_' + name, '#' + name + 'Error', '#' + name];
        for (var i = 0; i < selectors.length; i++) {
            var $el = $(selectors[i]);
            if ($el.length && $el.hasClass('field-error')) { return $el; }
        }
        return null;
    }

    function fieldError(name, message) {
        var $err = errorEl(name);
        if ($err) {
            $err.text(message || '').addClass('show');
            var $input = $err.closest('.form-group').find('.field, .check-row, .check-grid');
            $input.addClass('invalid');
        }
    }

    function clearFieldErrors(scope) {
        var $root = scope ? $(scope) : $(document);
        $root.find('.field-error').removeClass('show').text('');
        $root.find('.field').removeClass('invalid');
    }

    /** نگاشت خطاهای سرور (کلید = نام فنی فیلد) روی فرم */
    function applyErrors(errors, $scope) {
        errors = errors || {};
        Object.keys(errors).forEach(function (key) {
            var list = errors[key];
            var message = list && list.length ? list[0] : String(list);
            if (key === 'form_data' || key === 'files' || key === 'documents' || key === 'service') {
                fieldError('files', message);
                toast(message, 'error');
            } else {
                fieldError(key, message);
            }
        });
    }

    /* ---------- مودال تأیید ---------- */
    var modalHtml =
        '<div class="modal-backdrop" role="dialog" aria-modal="true">' +
        '  <div class="modal">' +
        '    <div class="m-icon">⚠</div>' +
        '    <h3 class="m-title"></h3>' +
        '    <p class="m-desc"></p>' +
        '    <div class="m-actions">' +
        '      <button type="button" class="btn btn-ghost m-cancel">انصراف</button>' +
        '      <button type="button" class="btn btn-primary m-ok">تأیید</button>' +
        '    </div>' +
        '  </div>' +
        '</div>';

    /**
     * CN.confirm({title, desc, okText, danger}, onOk)
     */
    function confirm(opts, onOk) {
        opts = opts || {};
        var $modal = $(modalHtml);
        $modal.find('.m-icon').text(opts.icon || '⚠');
        $modal.find('.m-title').text(opts.title || 'تأیید عملیات');
        $modal.find('.m-desc').text(opts.desc || '');
        $modal.find('.m-ok').text(opts.okText || 'تأیید');
        if (opts.danger) {
            $modal.find('.m-ok').removeClass('btn-primary').addClass('btn-danger');
        }
        $('body').append($modal);

        $modal.find('.m-cancel').on('click', function () { $modal.remove(); });
        $modal.find('.m-ok').on('click', function () {
            $modal.remove();
            if (onOk) { onOk(); }
        });
        $modal.on('click', function (e) {
            if (e.target === $modal[0]) { $modal.remove(); }
        });
    }

    /* ---------- هدر مشترک ---------- */
    function updateHeader(balance) {
        var $b = $('#headerBalance');
        if ($b.length) {
            $b.text(faMoneyUnit(balance === undefined ? 0 : balance));
        }
    }

    function updateAvatar(u) {
        var $a = $('#headerAvatar, #profileAvatar');
        if (!$a.length || !u) { return; }
        var initials;
        if (u.name && u.family) {
            initials = (u.name.trim().charAt(0) || '؟') + (u.family.trim().charAt(0) || '');
        } else {
            initials = '؟';
        }
        $a.text(initials);
        if ($a.attr('title') !== undefined) { $a.attr('title', u.full_name || ''); }
    }

    /** بروزرسانی هدر: موجودی + آواتار */
    function refreshChrome() {
        if (!token()) { return; }
        var u = user();
        if (u) { updateAvatar(u); }
        api('/wallet', {
            success: function (resp) {
                updateHeader(resp.balance);
                var cached = user();
                if (cached) {
                    cached.wallet_balance = resp.balance;
                    try { window.localStorage.setItem(USER_KEY, JSON.stringify(cached)); } catch (e) { /* noop */ }
                }
            }
        });
    }

    /* ---------- شمارش معکوس ---------- */
    function countdown($target, $btn, seconds, onEnd) {
        var remain = seconds;
        $btn.prop('disabled', true);

        function tick() {
            if (remain <= 0) {
                $target.text('');
                $btn.prop('disabled', false);
                if (onEnd) { onEnd(); }
                return;
            }
            $target.text('ارسال مجدد تا ' + toFaDigits(remain) + ' ثانیه…');
            remain--;
            window.setTimeout(tick, 1000);
        }
        tick();
    }

    /* ---------- debounce ---------- */
    function debounce(fn, wait) {
        var t = null;
        return function () {
            var args = arguments;
            var self = this;
            window.clearTimeout(t);
            t = window.setTimeout(function () { fn.apply(self, args); }, wait);
        };
    }

    /* ---------- نقشه وضعیت سفارش → کلاس بج ---------- */
    var STATUS_BADGE = {
        pending_payment: 'badge-amber',
        paid: 'badge-green',
        broadcasting: 'badge-teal',
        accepted: 'badge-green',
        in_progress: 'badge-teal',
        needs_info: 'badge-orange',
        delivered: 'badge-teal',
        completed: 'badge-green',
        queued: 'badge-stone',
        cancelled: 'badge-rose',
        refunded: 'badge-rose'
    };

    function statusBadge(status, label) {
        var cls = STATUS_BADGE[status] || 'badge-stone';
        return '<span class="badge ' + cls + '">' + esc(label || status) + '</span>';
    }

    /* ---------- تم شب/روز (حالت تاریک) ---------- */
    /* کلاس dark روی <html> توسط theme-boot.js (head) ست می‌شود (ضد-فلش).
       کلید localStorage «caffeinet-theme» مشترک با پنل‌های مدیریتی است. */
    var THEME_KEY = 'caffeinet-theme';
    var themeAnimTimer = null;

    function currentTheme() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    }

    function syncThemeButtons() {
        var mode = currentTheme();
        $('[data-theme-toggle]').each(function () {
            this.setAttribute('aria-pressed', mode === 'dark' ? 'true' : 'false');
            this.setAttribute('title', mode === 'dark' ? 'حالت روز' : 'حالت شب');
        });
        /* نمودارها/کامپوننت‌های سراسری از این رویداد باخبر شوند */
        $(document).trigger('ui:theme', mode);
    }

    function setTheme(mode, options) {
        options = options || {};
        var $html = $(document.documentElement);
        var isDark = mode === 'dark';
        if (isDark === $html.hasClass('dark')) { syncThemeButtons(); return; }

        /* انیمیشن نرم فقط هنگام تعویض (نه لود اولیه) */
        if (options.animate !== false) {
            $html.addClass('theme-anim');
            window.clearTimeout(themeAnimTimer);
            themeAnimTimer = window.setTimeout(function () { $html.removeClass('theme-anim'); }, 480);
        }

        $html.toggleClass('dark', isDark);
        if (options.persist !== false) {
            try { window.localStorage.setItem(THEME_KEY, mode); } catch (e) { /* noop */ }
        }
        syncThemeButtons();
    }

    /* اتصال دکمه‌های سوییچ (delegate — برای محتوای داینامیک هم کار می‌کند) */
    $(document).on('click', '[data-theme-toggle]', function () {
        setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    });

    /* ---------- خروج ---------- */
    function logout() {
        api('/logout', {
            method: 'POST',
            complete: function () {
                clearSession();
                window.location.replace(withPort('/app/auth'));
            }
        });
    }

    /* ---------- عمومی ---------- */
    $(function () {
        refreshChrome();
        syncThemeButtons();
    });

    return {
        withPort: withPort,
        apiUrl: apiUrl,
        token: token,
        user: user,
        setSession: setSession,
        clearSession: clearSession,
        requireAuth: requireAuth,
        api: api,
        toast: toast,
        btnLoading: btnLoading,
        fieldError: fieldError,
        clearFieldErrors: clearFieldErrors,
        applyErrors: applyErrors,
        confirm: confirm,
        updateHeader: updateHeader,
        updateAvatar: updateAvatar,
        refreshChrome: refreshChrome,
        countdown: countdown,
        debounce: debounce,
        statusBadge: statusBadge,
        theme: {
            get: currentTheme,
            set: setTheme,
            toggle: function () { setTheme(currentTheme() === 'dark' ? 'light' : 'dark'); }
        },
        logout: logout,
        faMoney: faMoney,
        faMoneyUnit: faMoneyUnit,
        toFaDigits: toFaDigits,
        toEnDigits: toEnDigits,
        esc: esc,
        normalizeMobile: normalizeMobile
    };
})(jQuery);
