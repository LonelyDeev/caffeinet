/* اپ مشتری — پروفایل (نمای کاربر — v24: فرم ویرایش به صفحهٔ جدا منتقل شد) */
/* global CN, jQuery */
(function ($) {
    'use strict';

    if (!CN.requireAuth()) { return; }

    /* ---------- بارگذاری پروفایل + آمار ---------- */
    CN.api('/me', {
        success: function (resp) {
            var u = resp.user || {};
            var stats = resp.orders_stats || {};

            try { window.localStorage.setItem('cn_user', JSON.stringify(u)); } catch (e) { /* noop */ }
            CN.updateAvatar(u);

            /* هرو */
            $('#profileAvatar').text(initials(u));
            $('#profileName').text(u.full_name || 'کاربر مهمان');
            $('#profileMobile').text(u.mobile || '—');
            $('#profileSinceText').text(u.member_since_fa ? 'عضو از ' + u.member_since_fa : 'عضو جدید');
            $('#profileCompleteBadge').toggleClass('hidden', !u.profile_completed);
            $('#profileBalance').text(u.wallet_balance !== undefined && u.wallet_balance !== null
                ? CN.faMoneyUnit(u.wallet_balance) : '—');

            /* هشدار پروفایل ناقص + پرچم لینک ویرایش */
            $('#pfIncompleteBanner').toggleClass('hidden', !!u.profile_completed);
            $('#pfEditFlag').toggleClass('hidden', !!u.profile_completed);
            $('#pfEditHint').text(u.profile_completed ? 'مشاهده و اصلاح اطلاعات شخصی' : 'برای استفاده از خدمات، تکمیل کنید');

            /* آمار سفارش‌ها */
            $('#statTotal').text(CN.toFaDigits(stats.total || 0));
            $('#statActive').text(CN.toFaDigits(stats.active || 0));
            $('#statCompleted').text(CN.toFaDigits(stats.completed || 0));
            $('#statCancelled').text(CN.toFaDigits(stats.cancelled || 0));
        }
    });

    function initials(u) {
        if (u && u.name && u.family) {
            return (u.name.trim().charAt(0) || '؟') + (u.family.trim().charAt(0) || '');
        }
        return '؟';
    }

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
