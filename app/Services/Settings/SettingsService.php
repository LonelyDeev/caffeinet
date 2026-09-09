<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * سرویس تنظیمات داینامیک — کش‌شده و تایپ‌دار.
 * نمونه: app('settings')->get('sms.provider')
 */
class SettingsService
{
    protected const CACHE_KEY = 'settings.all';

    /** دریافت مقدار تنظیم با تایپ صحیح */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        $row = $all[$key] ?? null;

        return $row === null ? $default : $row;
    }

    /** ذخیره مقدار (ایجاد یا بروزرسانی) */
    public function set(string $key, mixed $value): bool
    {
        $row = Setting::where('key', $key)->first();

        if (! $row) {
            return false; // کلیدهای ناشناس فقط از طریق seeder تعریف می‌شوند
        }

        $row->value = match ($row->cast) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
        $row->save();

        $this->flush();

        return true;
    }

    /** به‌روزرسانی گروهی (فقط کلیدهای موجود) */
    public function updateMany(array $pairs): int
    {
        $count = 0;
        foreach ($pairs as $key => $value) {
            if ($this->set($key, $value)) {
                $count++;
            }
        }

        return $count;
    }

    /** همه تنظیمات به‌صورت key => typed value (کش‌شده) */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            return Setting::query()
                ->get()
                ->mapWithKeys(fn (Setting $row) => [$row->key => $row->typed()])
                ->all();
        });
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
