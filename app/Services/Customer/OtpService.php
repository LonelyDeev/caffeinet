<?php

namespace App\Services\Customer;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Auth\LoginLogger;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Validation\ValidationException;

/**
 * سرویس OTP مشتری — درخواست کد، تأیید و ورود/ثبت‌نام.
 *
 * کدها هش‌شده ذخیره می‌شوند، TTL محدود دارند، حداکثر ۵ تلاش
 * و بازارسال هر ۹۰ ثانیه یک‌بار مجاز است.
 */
class OtpService
{
    public const TTL_MINUTES = 3;

    public const RESEND_SECONDS = 90;

    public const MAX_ATTEMPTS = 5;

    public function __construct(
        protected SmsManager $sms,
        protected SmsTemplateService $templates,
    ) {}

    /** نرمال‌سازی شماره موبایل (ارقام فارسی، +98، 0098) */
    public static function normalizeMobile(string $mobile): string
    {
        $mobile = en_digits(trim($mobile));
        $mobile = ltrim($mobile, '+');

        if (str_starts_with($mobile, '0098')) {
            $mobile = '0'.substr($mobile, 4);
        } elseif (str_starts_with($mobile, '98') && strlen($mobile) === 12) {
            $mobile = '0'.substr($mobile, 2);
        }

        return $mobile;
    }

    /**
     * درخواست کد ورود.
     *
     * @return array{ok: bool, expires_in: int, resend_in: int, dev_code: ?string}
     */
    public function request(string $mobile, string $ip, string $purpose = 'login'): array
    {
        $mobile = static::normalizeMobile($mobile);

        if (! preg_match('/^09\d{9}$/', $mobile)) {
            throw ValidationException::withMessages([
                'mobile' => ['شماره موبایل معتبر نیست؛ نمونه صحیح: ۰۹۱۲۳۴۵۶۷۸۹'],
            ]);
        }

        // محدودیت بازارسال
        $recent = OtpCode::query()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->orderByDesc('id')
            ->first();

        if ($recent && $recent->created_at && $recent->created_at->diffInSeconds(now()) < static::RESEND_SECONDS) {
            $wait = static::RESEND_SECONDS - $recent->created_at->diffInSeconds(now());

            throw ValidationException::withMessages([
                'mobile' => ['کد فعال‌سازی قبلی هنوز معتبر است؛ '.fa_digits($wait).' ثانیه دیگر تلاش کنید.'],
            ]);
        }

        // ابطال کدهای قبلی همین شماره
        OtpCode::query()->where('mobile', $mobile)->where('purpose', $purpose)->delete();

        $code = (string) random_int(10000, 99999);

        OtpCode::create([
            'mobile' => $mobile,
            'code_hash' => static::hash($mobile, $code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(static::TTL_MINUTES),
            'ip' => $ip,
            'created_at' => now(),
        ]);

        $sent = $this->sms->sendTemplate(
            $mobile,
            'otp_login',
            [
                'code' => $code,
                'minutes' => fa_digits((string) static::TTL_MINUTES),
            ],
            "کد ورود شما به «کافی‌نت آنلاین»: {$code}".PHP_EOL.'اعتبار کد: '.fa_digits((string) static::TTL_MINUTES).' دقیقه'
        );

        // در محیط توسعه با درایور لاگ، کد برای تست بازگردانده می‌شود
        $devCode = (! app()->isProduction() && $this->sms->driver()->name() === 'log') ? $code : null;

        return [
            'ok' => $sent['ok'],
            'expires_in' => static::TTL_MINUTES * 60,
            'resend_in' => static::RESEND_SECONDS,
            'dev_code' => $devCode,
        ];
    }

    /**
     * تأیید کد و ورود/ساخت کاربر مشتری.
     *
     * @return array{user: User, token: string}
     */
    public function verify(string $mobile, string $code, string $purpose = 'login'): array
    {
        $mobile = static::normalizeMobile($mobile);
        $code = en_digits(trim($code));

        /** @var OtpCode|null $otp */
        $otp = OtpCode::query()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->orderByDesc('id')
            ->first();

        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['کد منقضی شده یا درخواستی ثبت نشده است؛ دوباره کد بگیرید.'],
            ]);
        }

        if ($otp->attempts >= static::MAX_ATTEMPTS) {
            $otp->delete();

            throw ValidationException::withMessages([
                'code' => ['تعداد تلاش‌های ناموفق بیش از حد مجاز است؛ کد جدید بگیرید.'],
            ]);
        }

        if (! hash_equals(static::hash($mobile, $code), $otp->code_hash)) {
            $otp->forceFill(['attempts' => $otp->attempts + 1])->save();

            $remaining = static::MAX_ATTEMPTS - $otp->attempts;

            throw ValidationException::withMessages([
                'code' => ['کد تأیید نادرست است.'.($remaining > 0 ? ' '.fa_digits((string) $remaining).' تلاش باقی‌مانده.' : '')],
            ]);
        }

        // کد مصرف‌شده حذف می‌شود
        $otp->delete();

        /** @var User|null $user */
        $user = User::query()->where('mobile', $mobile)->first();

        if (! $user) {
            $user = User::create([
                'mobile' => $mobile,
                'mobile_verified_at' => now(),
                'profile_completed' => false,
                'is_active' => true,
            ]);
            $user->assignRole('customer');
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'mobile' => ['حساب شما غیرفعال شده است؛ با پشتیبانی تماس بگیرید.'],
            ]);
        }

        $user->forceFill([
            'mobile_verified_at' => $user->mobile_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        if (! $user->hasRole('customer')) {
            $user->assignRole('customer');
        }

        $token = $user->createToken('customer-app')->plainTextToken;

        // تاریخچهٔ ورود مشتری (پروفایل پنل مدیریت — درخواست بازخوردی)
        LoginLogger::log($user, 'login', 'customer');

        return ['user' => $user, 'token' => $token];
    }

    protected static function hash(string $mobile, string $code): string
    {
        return hash('sha256', $mobile.'|'.$code.'|'.config('app.key'));
    }
}
