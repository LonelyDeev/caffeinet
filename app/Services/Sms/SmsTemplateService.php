<?php

namespace App\Services\Sms;

use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Cache;

/**
 * سرویس قالب‌های پیامک (فاز ۱۰).
 *
 * متن هر رویداد (OTP، پذیرش سفارش، تحویل، …) از پنل قابل ویرایش است.
 * متغیرها با قالب {name} در body تعریف می‌شوند و هنگام ارسال جایگزین می‌گردند.
 * اگر قالب غیرفعال/حذف شده باشد، compose() متن پیش‌فرض کد را برمی‌گرداند —
 * یعنی ویرایش قالب‌ها هرگز ارسال را نمی‌شکند.
 */
class SmsTemplateService
{
    protected const CACHE_PREFIX = 'sms_template.';

    /** رندر قالب با متغیرها؛ اگر قالب فعال نبود متن پیش‌فرض */
    public function compose(string $key, array $vars = [], ?string $fallback = null): ?string
    {
        $template = $this->find($key);

        if (! $template) {
            return $fallback;
        }

        $body = (string) $template->body;

        foreach ($vars as $name => $value) {
            $body = str_replace('{'.$name.'}', (string) $value, $body);
        }

        // متغیرهای تعریف‌نشده حذف شوند تا متن کثیف ارسال نشود
        $body = preg_replace('/\{[a-zA-Z0-9_.]+\}/u', '', $body);
        $body = trim(preg_replace("/[ \t]+/", ' ', $body));

        return $body !== '' ? $body : $fallback;
    }

    /** قالب فعال (کش ۶۰ ثانیه‌ای — فقط دادهٔ اسکالر؛ مدل در کش سریال نمی‌شود) */
    public function find(string $key): ?SmsTemplate
    {
        /** @var array|null $data */
        $data = Cache::remember(self::CACHE_PREFIX.$key, now()->addMinute(), function () use ($key) {
            $template = SmsTemplate::query()->where('key', $key)->where('is_active', true)->first();

            // فقط مقادیر ساده کش می‌شوند تا unserialize امن باشد
            // v10: pattern_code + variables باید کش شوند وگرنه ارسال پترنی
            // هرگز فعال نمی‌شود (باگ قدیمی — قالب بدون پترن دیده می‌شد)
            return $template ? $template->only(['key', 'title', 'body', 'variables', 'is_active', 'pattern_code']) : null;
        });

        if (! is_array($data) || empty($data['body'] ?? null)) {
            return null;
        }

        $template = new SmsTemplate($data);
        $template->exists = true;

        return $template;
    }

    /** باطل کردن کش یک قالب (بعد از ویرایش) */
    public function flush(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /** باطل کردن کل کش قالب‌ها */
    public function flushAll(): void
    {
        // کلیدهای کش نامشخص‌اند؛ الگو مشخص است — پاکسازی با تگ عملی نیست، پس کلیدهای شناخته‌شده + wildcard تلاش می‌شود
        foreach (SmsTemplate::query()->pluck('key') as $key) {
            $this->flush((string) $key);
        }
    }
}
