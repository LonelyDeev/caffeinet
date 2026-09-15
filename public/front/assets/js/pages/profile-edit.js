/* اپ مشتری — ویرایش اطلاعات پروفایل (v24 — از صفحهٔ پروفایل جدا شد) */
/* v39 — تاریخ تولد با سه لیست کشویی سال/ماه/روز شمسی (بدون دیت‌پیکر) */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireAuth()) { return; }

    var provincesLoaded = false;
    var selectedProvinceId = null;
    var isNewUser = false;

    /* اولین ورود (?new=1) → بنر خوش‌آمد */
    try {
        isNewUser = new URLSearchParams(window.location.search).get('new') === '1';
    } catch (e) { isNewUser = false; }
    $('#pfWelcomeBanner').toggleClass('hidden', !isNewUser);

    /* ---------- v39 — انتخابگر تاریخ تولد (سه لیست کشویی) ---------- */
    var BIRTH_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /* بازهٔ سنین مجاز — از data-attribute تگ اسکریپت (CSP-safe؛ بدون inline) */
    var birthRange = (function () {
        var el = document.currentScript || document.querySelector('script[src*="profile-edit"]');
        var min = parseInt(el && el.getAttribute('data-birth-min'), 10);
        var max = parseInt(el && el.getAttribute('data-birth-max'), 10);
        return {
            minAge: (min >= 1 && min < 119) ? min : 10,
            maxAge: (max > 1 && max <= 120) ? max : 100
        };
    })();

    /* v40 — کد ملی: الزامی بودن (استعلام فینوتک فعال است؟) از data-attribute */
    var nidRequired = (function () {
        var el = document.currentScript || document.querySelector('script[src*="profile-edit"]');
        return !!(el && el.getAttribute('data-nid-required') === '1');
    })();

    /** چک‌سام کد ملی ۱۰ رقمی ایران */
    function isValidNationalId(raw) {
        var code = CN.toEnDigits(String(raw || '')).trim();
        if (!/^\d{10}$/.test(code)) { return false; }
        if (/^(\d)\1{9}$/.test(code)) { return false; } // تمام ارقام یکسان
        var sum = 0;
        for (var i = 0; i < 9; i++) { sum += parseInt(code[i], 10) * (10 - i); }
        var rem = sum % 11;
        var check = parseInt(code[9], 10);
        return (rem < 2) ? (check === rem) : (check === 11 - rem);
    }

    /** سال جاری شمسی */
    function currentJalaliYear() {
        if (window.CNJdp && CNJdp.toJalaali) {
            var n = new Date();
            var j = CNJdp.toJalaali(n.getFullYear(), n.getMonth() + 1, n.getDate());
            if (j && j.jy) { return j.jy; }
        }
        return Math.floor((new Date().getFullYear() + 621) ); // تقریب
    }

    /** تعداد روزهای یک ماه شمسی (کبیسهٔ اسفند لحاظ می‌شود) */
    function jalaliMonthDays(jy, jm) {
        if (!jy || !jm) { return 31; }
        if (window.CNJdp && CNJdp.monthLength) { return CNJdp.monthLength(jy, jm); }
        if (jm <= 6) { return 31; }
        if (jm <= 11) { return 30; }
        return 29;
    }

    function fillBirthYears(selected) {
        var jyNow = currentJalaliYear();
        var minYear = jyNow - birthRange.maxAge; // قدیمی‌ترین سال مجاز
        var maxYear = jyNow - birthRange.minAge; // جدیدترین سال مجاز

        var opts = '<option value="">انتخاب سال…</option>';
        for (var y = maxYear; y >= minYear; y--) {
            opts += '<option value="' + y + '"' + (selected === y ? ' selected' : '') + '>' + CN.toFaDigits(y) + '</option>';
        }
        $('#pBirthYear').html(opts);
    }

    function fillBirthMonths(selected) {
        var opts = '<option value="">انتخاب ماه…</option>';
        BIRTH_MONTHS.forEach(function (name, i) {
            var m = i + 1;
            opts += '<option value="' + m + '"' + (selected === m ? ' selected' : '') + '>' + name + '</option>';
        });
        $('#pBirthMonth').html(opts);
    }

    function fillBirthDays(selected) {
        var y = parseInt(String($('#pBirthYear').val() || ''), 10) || 0;
        var m = parseInt(String($('#pBirthMonth').val() || ''), 10) || 0;
        var days = (y && m) ? jalaliMonthDays(y, m) : 31;

        var opts = '<option value="">انتخاب روز…</option>';
        for (var d = 1; d <= days; d++) {
            opts += '<option value="' + d + '"' + (selected === d ? ' selected' : '') + '>' + CN.toFaDigits(d) + '</option>';
        }
        $('#pBirthDay').html(opts);
    }

    /** مقدار نهایی Y/M/D (ارقام انگلیسی) یا '' */
    function birthValue() {
        var y = String($('#pBirthYear').val() || '');
        var m = String($('#pBirthMonth').val() || '');
        var d = String($('#pBirthDay').val() || '');
        if (!y || !m || !d) { return ''; }
        return y + '/' + (m.length < 2 ? '0' + m : m) + '/' + (d.length < 2 ? '0' + d : d);
    }

    /** «۱۳۷۰/۰۵/۱۲» یا «1370/5/12» → انتخاب سه لیست */
    function setBirthFromFa(fa) {
        var raw = CN.toEnDigits(String(fa || '')).trim();
        var m = /^(\d{3,4})[\/.\-](\d{1,2})[\/.\-](\d{1,2})$/.exec(raw);
        if (!m) { return; }

        var y = parseInt(m[1], 10);
        var mo = parseInt(m[2], 10);
        var d = parseInt(m[3], 10);

        fillBirthYears(y);
        fillBirthMonths(mo);
        fillBirthDays(d);

        // اگر مقدار ذخیره‌شده خارج از بازهٔ مجاز است، بازه را گسترش می‌دهیم تا دیده شود
        if (String($('#pBirthYear').val() || '') !== String(y)) {
            $('#pBirthYear').prepend('<option value="' + y + '" selected>' + CN.toFaDigits(y) + '</option>');
        }
        if (parseInt(String($('#pBirthMonth').val() || '0'), 10) !== mo) {
            $('#pBirthMonth').val(mo);
        }
        if (parseInt(String($('#pBirthDay').val() || '0'), 10) !== d) {
            $('#pBirthDay').prepend('<option value="' + d + '" selected>' + CN.toFaDigits(d) + '</option>');
        }
    }

    fillBirthYears();
    fillBirthMonths();
    fillBirthDays();

    $('#pBirthYear, #pBirthMonth').on('change', function () {
        // با تغییر سال/ماه، روزها بازسازی می‌شود (۳۱/۳۰/۲۹ کبیسه)
        fillBirthDays(parseInt(String($('#pBirthDay').val() || ''), 10) || null);
        $('#pBirthdate').val(birthValue());
        $('#pBirthdate').removeClass('invalid');
        $('#pBirthdateError').removeClass('show').text('');
    });
    $('#pBirthDay').on('change', function () {
        $('#pBirthdate').val(birthValue());
        $('#pBirthdate').removeClass('invalid');
        $('#pBirthdateError').removeClass('show').text('');
    });

    /* ---------- بارگذاری دادهٔ فرم (v40 — خطا → بنر + تلاش مجدد) ---------- */
    /* باگ گزارش‌شده: «فرم اطلاعات لود نمی‌شود و چندبار رفرش لازم است».
       ریشه: درخواست /me بدون error/timeout بود؛ اگر شبکه قطع یا کند بود
       فرم برای همیشه خالی می‌ماند. اکنون پس از تلاش مجدد خودکارِ CN.api،
       بنر خطا + دکمهٔ «تلاش مجدد» می‌آید. */
    function loadMe() {
        $('#profileLoadError').addClass('hidden');

        CN.api('/me', {
            timeout: 15000,
            retries: 2,
            success: function (resp) {
                var u = resp.user || {};

                try { window.localStorage.setItem('cn_user', JSON.stringify(u)); } catch (e) { /* noop */ }
                CN.updateAvatar(u);

                $('#pName').val(u.name || '');
                $('#pFamily').val(u.family || '');
                $('#pNationalId').val(u.national_id || '');
                $('#pNidVerifiedBadge').toggleClass('hidden', !u.national_id_verified_at);
                if (u.gender) {
                    $('input[name="gender"][value="' + u.gender + '"]').prop('checked', true);
                }
                if (u.birthdate_fa) {
                    setBirthFromFa(u.birthdate_fa);
                }

                if (u.province && u.province.id) {
                    selectedProvinceId = u.province.id;
                    loadProvinces(function () {
                        $('#pProvince').val(u.province.id).trigger('change');
                    });
                } else {
                    loadProvinces();
                }
            },
            error: function (xhr, message) {
                $('#profileLoadErrorMsg').text(message || 'ارتباط با سرور برقرار نشد؛ اینترنت خود را بررسی کنید.');
                $('#profileLoadError').removeClass('hidden');
            }
        });
    }

    $(document).on('click', '#profileLoadRetry', function () { loadMe(); });

    loadMe();

    /* ---------- جغرافیا (آبشاری) ---------- */
    function loadProvinces(after) {
        if (provincesLoaded) {
            if (after) { after(); }
            return;
        }

        CN.api('/geo/provinces', {
            timeout: 15000,
            retries: 2,
            success: function (resp) {
                provincesLoaded = true;
                var opts = '<option value="">انتخاب استان…</option>';
                (resp.data || []).forEach(function (p) {
                    opts += '<option value="' + p.id + '">' + CN.esc(p.name) + '</option>';
                });
                $('#pProvince').html(opts).prop('disabled', false);
                if (after) { after(); }
            },
            error: function () {
                $('#pProvince').html('<option value="">بارگذاری استان‌ها ناموفق بود</option>').prop('disabled', false);
                $('#geoLoadError').removeClass('hidden');
            }
        });
    }

    $('#pProvince').on('change', function () {
        var pid = $(this).val();
        if (!pid) {
            $('#pCity').prop('disabled', true).html('<option value="">ابتدا استان را انتخاب کنید</option>');
            return;
        }
        selectedProvinceId = pid;

        $('#pCity').prop('disabled', true).html('<option value="">در حال بارگذاری…</option>');

        CN.api('/geo/cities/' + pid, {
            timeout: 15000,
            retries: 2,
            success: function (resp) {
                var opts = '<option value="">انتخاب شهر…</option>';
                (resp.data || []).forEach(function (c) {
                    opts += '<option value="' + c.id + '">' + CN.esc(c.name) + '</option>';
                });
                var $city = $('#pCity').html(opts).prop('disabled', false);

                // اگر شهر قبلاً ذخیره شده و همین استان است
                if (selectedProvinceId === pid) {
                    var cached = CN.user();
                    if (cached && cached.city && cached.city.id && cached.province && +cached.province.id === +pid) {
                        $city.val(cached.city.id);
                    }
                }
            }
        });
    });

    /* ---------- اعتبارسنجی زندهٔ فرم ---------- */
    $('#pNationalId').on('input', function () {
        $(this).removeClass('invalid');
        $('#pNationalIdError').removeClass('show').text('');
        $('#pNidVerifiedBadge').addClass('hidden'); // با ویرایش، وضعیت تأیید باید دوباره بررسی شود
    });
    $('#pName, #pFamily').on('input', function () {
        $(this).removeClass('invalid');
        $('#' + this.id + 'Error').removeClass('show').text('');
    });
    $('#pProvince, #pCity').on('change', function () {
        $(this).removeClass('invalid');
        $('#' + this.id + 'Error').removeClass('show').text('');
    });
    $('#genderGroup input').on('change', function () {
        $('#genderError').removeClass('show').text('');
    });

    /* ---------- ذخیره ---------- */
    $('#profileForm').on('submit', function (e) {
        e.preventDefault();
        CN.clearFieldErrors('#profileForm');

        var name = $('#pName').val().trim();
        var family = $('#pFamily').val().trim();
        var gender = $('input[name="gender"]:checked').val() || '';
        var provinceId = $('#pProvince').val() || '';
        var cityId = $('#pCity').val() || '';
        var birthdate = birthValue();
        var nationalId = CN.toEnDigits($('#pNationalId').val() || '').trim();

        var valid = true;

        if (name.length < 2) { CN.fieldError('pName', 'نام را وارد کنید (حداقل ۲ حرف).'); valid = false; }
        if (family.length < 2) { CN.fieldError('pFamily', 'نام‌خانوادگی را وارد کنید (حداقل ۲ حرف).'); valid = false; }
        if (!gender) { CN.fieldError('gender', 'جنسیت را انتخاب کنید.'); valid = false; }

        /* v40 — کد ملی */
        if (nationalId) {
            if (!/^\d{10}$/.test(nationalId)) {
                CN.fieldError('pNationalId', 'کد ملی باید دقیقاً ۱۰ رقم باشد.'); valid = false;
            } else if (!isValidNationalId(nationalId)) {
                CN.fieldError('pNationalId', 'کد ملی واردشده معتبر نیست؛ رقم آخر (رقم کنترل) نمی‌خورد.'); valid = false;
            }
        } else if (nidRequired) {
            CN.fieldError('pNationalId', 'کد ملی برای احراز هویت الزامی است.'); valid = false;
        }
        if (!provinceId) { CN.fieldError('pProvince', 'استان را انتخاب کنید.'); valid = false; }
        if (!cityId) { CN.fieldError('pCity', 'شهر را انتخاب کنید.'); valid = false; }
        if (!birthdate) {
            CN.fieldError('pBirthdate', 'سال، ماه و روز تولدتان را انتخاب کنید.');
            valid = false;
        } else {
            // روز انتخابی نباید از طول واقعی ماه بیشتر باشد (کبیسه)
            var by = parseInt(birthdate.split('/')[0], 10);
            var bm = parseInt(birthdate.split('/')[1], 10);
            var bd = parseInt(birthdate.split('/')[2], 10);
            if (bd > jalaliMonthDays(by, bm)) {
                CN.fieldError('pBirthdate', 'روز انتخابی با ماه سازگار نیست؛ دوباره انتخاب کنید.');
                valid = false;
            }
        }

        if (!valid) {
            CN.toast('لطفاً فیلدهای الزامی را کامل کنید.', 'error');
            return;
        }

        CN.btnLoading($('#saveProfileBtn'), true, 'در حال ذخیره…');

        CN.api('/profile/complete', {
            method: 'POST',
            data: {
                name: name,
                family: family,
                gender: gender,
                province_id: +provinceId,
                city_id: +cityId,
                birthdate: birthdate,
                national_id: nationalId || null
            },
            success: function (resp) {
                CN.btnLoading($('#saveProfileBtn'), false);
                CN.updateAvatar(resp.user);

                // به‌روزرسانی کش کاربر → گارد requireCompleteProfile از همین لحظه پاس می‌شود
                try { window.localStorage.setItem('cn_user', JSON.stringify(resp.user)); } catch (e) { /* noop */ }

                CN.toast(resp.message || 'اطلاعات با موفقیت ذخیره شد.', 'success');

                var redirectTo;
                if (isNewUser) {
                    // اولین ورود → شروع استفاده از اپ
                    redirectTo = '/app/home';
                } else {
                    // ویرایش عادی → بازگشت به نمای پروفایل (اطلاعات به‌روز)
                    redirectTo = '/app/profile';
                }
                window.setTimeout(function () {
                    window.location.replace(CN.withPort(redirectTo));
                }, isNewUser ? 900 : 700);
            },
            error: function (xhr, message) {
                CN.btnLoading($('#saveProfileBtn'), false);
                var errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};

                var map = {
                    name: 'pName', family: 'pFamily', gender: 'gender',
                    province_id: 'pProvince', city_id: 'pCity', birthdate: 'pBirthdate',
                    national_id: 'pNationalId'
                };
                Object.keys(errors).forEach(function (key) {
                    var el = map[key];
                    if (el && errors[key] && errors[key].length) {
                        CN.fieldError(el, errors[key][0]);
                    }
                });
                CN.toast(message, 'error');
            }
        });
    });
})(jQuery);
