/* اپ مشتری — صفحه ورود با OTP */
/* global CN, jQuery */
(function ($) {
    'use strict';

    // اگر قبلاً وارد شده → صفحه مناسب (v24: پروفایل ناقص → مستقیم ویرایش اطلاعات)
    if (CN.token() && CN.user()) {
        window.location.replace(CN.withPort(CN.user().profile_completed ? '/app/home' : '/app/profile/edit?new=1'));
        return;
    }

    var currentMobile = '';
    var resendSeconds = 90;

    var $stepMobile = $('#stepMobile');
    var $stepCode = $('#stepCode');
    var $mobileInput = $('#mobileInput');
    var $codeInput = $('#codeInput');

    function showStep(step) {
        if (step === 'code') {
            $stepMobile.addClass('hidden');
            $stepCode.removeClass('hidden');
            $('#codeTarget').text('به ' + CN.toFaDigits(currentMobile));
            window.setTimeout(function () { $codeInput.trigger('focus'); }, 120);
        } else {
            $stepCode.addClass('hidden');
            $stepMobile.removeClass('hidden');
            window.setTimeout(function () { $mobileInput.trigger('focus'); }, 120);
        }
    }

    /* ---------- گام ۱: درخواست کد ---------- */
    function requestOtp() {
        CN.clearFieldErrors($stepMobile);
        currentMobile = CN.normalizeMobile($mobileInput.val());

        if (!/^09\d{9}$/.test(currentMobile)) {
            CN.fieldError('mobileError', 'شماره موبایل معتبر نیست؛ نمونه: ۰۹۱۲۳۴۵۶۷۸۹');
            return;
        }
        $('#mobileError').addClass('show');

        CN.btnLoading($('#sendOtpBtn'), true, 'در حال ارسال…');

        CN.api('/otp/request', {
            method: 'POST',
            data: { mobile: currentMobile },
            success: function (resp) {
                CN.btnLoading($('#sendOtpBtn'), false);
                resendSeconds = resp.resend_in || 90;
                showStep('code');

                if (resp.dev_code) {
                    $('#devCodeValue').text(CN.toFaDigits(resp.dev_code));
                    $('#devCodeNote').removeClass('hidden');
                } else {
                    $('#devCodeNote').addClass('hidden');
                }

                CN.countdown($('#resendTimer'), $('#resendBtn'), resendSeconds);
                CN.toast('کد تأیید به شماره شما پیامک شد.', 'success');
            },
            error: function (xhr, message) {
                CN.btnLoading($('#sendOtpBtn'), false);
                CN.fieldError('mobileError', message);
            }
        });
    }

    /* ---------- گام ۲: تأیید ---------- */
    function verifyOtp() {
        CN.clearFieldErrors($stepCode);
        var code = CN.toEnDigits($codeInput.val()).trim();

        if (!/^\d{4,8}$/.test(code)) {
            CN.fieldError('codeError', 'کد تأیید را کامل و درست وارد کنید.');
            return;
        }

        CN.btnLoading($('#verifyBtn'), true, 'در حال بررسی…');

        CN.api('/otp/verify', {
            method: 'POST',
            data: { mobile: currentMobile, code: code },
            success: function (resp) {
                CN.setSession(resp.token, resp.user);
                CN.toast('خوش آمدید ' + (resp.user.name ? resp.user.name : '📖'), 'success');
                // v24: ثبت‌نام اولیه → مستقیم به ویرایش اطلاعات (تکمیل پروفایل الزامی است)
                window.location.replace(CN.withPort(resp.profile_completed ? '/app/home' : '/app/profile/edit?new=1'));
            },
            error: function (xhr, message) {
                CN.btnLoading($('#verifyBtn'), false);
                CN.fieldError('codeError', message);
            }
        });
    }

    /* ---------- رویدادها ---------- */
    $('#sendOtpBtn').on('click', requestOtp);
    $('#mobileInput').on('keydown', function (e) {
        if (e.key === 'Enter') { requestOtp(); }
    });

    $('#verifyBtn').on('click', verifyOtp);
    $codeInput.on('keydown', function (e) {
        if (e.key === 'Enter') { verifyOtp(); }
    });

    $('#resendBtn').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        CN.api('/otp/request', {
            method: 'POST',
            data: { mobile: currentMobile },
            success: function (resp) {
                CN.countdown($('#resendTimer'), $btn, resp.resend_in || 90);
                if (resp.dev_code) {
                    $('#devCodeValue').text(CN.toFaDigits(resp.dev_code));
                    $('#devCodeNote').removeClass('hidden');
                }
                CN.toast('کد جدید پیامک شد.', 'success');
            },
            error: function (xhr, message) {
                $btn.prop('disabled', false);
                CN.toast(message, 'error');
            }
        });
    });

    $('#backBtn').on('click', function () {
        $codeInput.val('');
        $('#devCodeNote').addClass('hidden');
        showStep('mobile');
    });

    // کلیک روی باکس کد توسعه → پر کردن خودکار (تست)
    $('#devCodeNote').on('click', function () {
        var v = $('#devCodeValue').text();
        $codeInput.val(CN.toEnDigits(v)).trigger('focus');
    });

    window.setTimeout(function () { $mobileInput.trigger('focus'); }, 250);
})(jQuery);
