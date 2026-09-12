{{-- گزارش آخرین پاکسازی دوره‌ای (فاز ۱۱) — از settings system.cleanup.last --}}
@php($report = $last['removed'] ?? [])

<div class="sy-report">
    <div class="sy-report-line"><span>کد OTP منقضی حذف‌شده</span><b>{{ fa_digits($report['otp_codes'] ?? 0) }}</b></div>
    <div class="sy-report-line"><span>اعلان خوانده‌شدهٔ قدیمی</span><b>{{ fa_digits($report['notifications_read'] ?? 0) }}</b></div>
    <div class="sy-report-line"><span>اعلان خوانده‌نشدهٔ قدیمی</span><b>{{ fa_digits($report['notifications_unread'] ?? 0) }}</b></div>
    <div class="sy-report-line"><span>لاگ پیامک قدیمی</span><b>{{ fa_digits($report['sms_logs'] ?? 0) }}</b></div>
    <div class="sy-report-line"><span>لاگ فعالیت قدیمی</span><b>{{ fa_digits($report['audit_logs'] ?? 0) }}</b></div>
    <div class="sy-report-line"><span>بایگانی لاگ لاراول</span><b>{{ ($report['log_archived'] ?? 0) ? 'بایگانی شد' : 'لازم نشد' }}</b></div>

    @if ($last)
        <div class="sy-report-meta">
            {{--
                آخرین اجرا: تاریخ — نگهداشت اعمال‌شده
            --}}
            آخرین اجرا: {{ fa_date($last['ran_at'] ?? null, 'Y/m/d H:i') }}
            @if (($last['scope'] ?? 'all') !== 'all')
                — دامنه: <b>{{ ['sms_logs' => 'فقط لاگ پیامک', 'audit_logs' => 'فقط لاگ فعالیت', 'notifications' => 'فقط اعلان‌ها', 'otp' => 'فقط OTP', 'logs' => 'فقط لاگ لاراول'][$last['scope']] ?? $last['scope'] }}</b>
            @endif
            — نگهداشت: اعلان خوانده‌شدهٔ {{ fa_digits($last['retention']['notifications_read'] ?? '-') }} روز /
            پیامک {{ fa_digits($last['retention']['sms_logs'] ?? '-') }} روز /
            فعالیت {{ fa_digits($last['retention']['audit_logs'] ?? '-') }} روز
        </div>
    @else
        <div class="sy-report-meta">هنوز پاکسازی اجرا نشده است.</div>
    @endif
</div>
