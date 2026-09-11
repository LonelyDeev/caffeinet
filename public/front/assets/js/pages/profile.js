/* اپ مشتری — پروفایل (بازطراحی فاز ۲۳: هرو + آمار + فرم بخش‌بندی‌شده) */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireAuth()) { return; }

    var provincesLoaded = false;
    var selectedProvinceId = null;

    /* ---------- بارگذاری پروفایل + آمار ---------- */
    CN.api('/me', {
        success: function (resp) {
            var u = resp.user || {};
            var stats = resp.orders_stats || {};

            try { window.localStorage.setItem('cn_user', JSON.stringify(u)); } catch (e) { /* noop */ }
            CN.updateAvatar(u);
            CN.updateHeader(u.wallet_balance);

            /* هرو */
            $('#profileAvatar').text(initials(u));
            $('#profileName').text(u.full_name || 'کاربر مهمان');
            $('#profileMobile').text(u.mobile || '—');
            $('#profileSinceText').text(u.member_since_fa ? 'عضو از ' + u.member_since_fa : 'عضو جدید');
            $('#profileCompleteBadge').toggleClass('hidden', !u.profile_completed);
            $('#profileBalance').text(u.wallet_balance !== undefined && u.wallet_balance !== null
                ? CN.faMoneyUnit(u.wallet_balance) : '—');

            /* آمار سفارش‌ها */
            $('#statTotal').text(CN.toFaDigits(stats.total || 0));
            $('#statActive').text(CN.toFaDigits(stats.active || 0));
            $('#statCompleted').text(CN.toFaDigits(stats.completed || 0));
            $('#statCancelled').text(CN.toFaDigits(stats.cancelled || 0));

            /* فرم */
            $('#pName').val(u.name || '');
            $('#pFamily').val(u.family || '');
            if (u.gender) {
                $('input[name="gender"][value="' + u.gender + '"]').prop('checked', true);
            }
            $('#pBirthdate').val(u.birthdate_fa || '');

            if (u.province && u.province.id) {
                selectedProvinceId = u.province.id;
                loadProvinces(function () {
                    $('#pProvince').val(u.province.id).trigger('change');
                });
            } else {
                loadProvinces();
            }
        }
    });

    function initials(u) {
        if (u && u.name && u.family) {
            return (u.name.trim().charAt(0) || '؟') + (u.family.trim().charAt(0) || '');
        }
        return '؟';
    }

    /* ---------- جغرافیا (آبشاری) ---------- */
    function loadProvinces(after) {
        if (provincesLoaded) {
            if (after) { after(); }
            return;
        }

        CN.api('/geo/provinces', {
            success: function (resp) {
                provincesLoaded = true;
                var opts = '<option value="">انتخاب استان…</option>';
                (resp.data || []).forEach(function (p) {
                    opts += '<option value="' + p.id + '">' + CN.esc(p.name) + '</option>';
                });
                $('#pProvince').html(opts).prop('disabled', false);
                if (after) { after(); }
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
    $('#pName, #pFamily, #pBirthdate').on('input', function () {
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
        var birthdate = CN.toEnDigits($('#pBirthdate').val()).trim();

        var valid = true;

        if (name.length < 2) { CN.fieldError('pName', 'نام را وارد کنید (حداقل ۲ حرف).'); valid = false; }
        if (family.length < 2) { CN.fieldError('pFamily', 'نام‌خانوادگی را وارد کنید (حداقل ۲ حرف).'); valid = false; }
        if (!gender) { CN.fieldError('gender', 'جنسیت را انتخاب کنید.'); valid = false; }
        if (!provinceId) { CN.fieldError('pProvince', 'استان را انتخاب کنید.'); valid = false; }
        if (!cityId) { CN.fieldError('pCity', 'شهر را انتخاب کنید.'); valid = false; }
        if (!birthdate) { CN.fieldError('pBirthdate', 'تاریخ تولد را وارد کنید.'); valid = false; }

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
                birthdate: birthdate
            },
            success: function (resp) {
                CN.btnLoading($('#saveProfileBtn'), false);
                CN.updateAvatar(resp.user);
                $('#profileAvatar').text(initials(resp.user));
                $('#profileName').text(resp.user.full_name || '');
                $('#profileCompleteBadge').removeClass('hidden');
                CN.toast(resp.message || 'ذخیره شد.', 'success');

                // اگر از مسیر ورود آمده (پروفایل ناقص) → خانه
                try {
                    if (new URLSearchParams(window.location.search).get('new') === '1') {
                        window.setTimeout(function () {
                            window.location.replace(CN.withPort('/app/home'));
                        }, 700);
                    }
                } catch (e) { /* noop */ }
            },
            error: function (xhr, message) {
                CN.btnLoading($('#saveProfileBtn'), false);
                var errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};

                var map = {
                    name: 'pName', family: 'pFamily', gender: 'gender',
                    province_id: 'pProvince', city_id: 'pCity', birthdate: 'pBirthdate'
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

    /* ---------- خروج ---------- */
    $('#logoutBtn').on('click', function () {
        CN.confirm({
            icon: '🚪',
            title: 'خروج از حساب',
            desc: 'برای ورود مجدد به کد پیامکی نیاز دارید.',
            okText: 'خروج',
            danger: true
        }, CN.logout);
    });
})(jQuery);
