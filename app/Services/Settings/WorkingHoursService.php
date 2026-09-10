<?php

namespace App\Services\Settings;

use Carbon\Carbon;

/**
 * فاز ۱۵ — سرویس ساعت کاری.
 *
 * همه‌ی مقایسه‌ها در منطقه‌ی زمانی تهران انجام می‌شود (config/app.php روی UTC است).
 * از این سرویس برای:
 *   - API وضعیت ساعت کاری (GET /api/v1/work-hours)
 *   - گارد ثبت سفارش در OrdersController (فاز ۱۵)
 * استفاده می‌شود.
 */
class WorkingHoursService
{
    /** منطقه‌ی زمانی مرجع کسب‌وکار */
    public const TIMEZONE = 'Asia/Tehran';

    public function __construct(
        protected SettingsService $settings,
    ) {
    }

    /** آیا محدودیت ساعت کاری روشن است؟ */
    public function enabled(): bool
    {
        return (bool) $this->settings->get('workhours.enabled', false);
    }

    /**
     * وضعیت کامل ساعت کاری در یک لحظه.
     *
     * @return array{
     *   enabled: bool, open: bool, now: string, day_of_week: int,
     *   start: string, end: string, days: int[], day_today_open: bool,
     *   message: string
     * }
     */
    public function status(?Carbon $at = null): array
    {
        $now = ($at ?? Carbon::now(self::TIMEZONE))->timezone(self::TIMEZONE);

        $start = $this->normalizeTime($this->settings->get('workhours.start', '08:00'));
        $end = $this->normalizeTime($this->settings->get('workhours.end', '22:00'));

        $days = $this->days();

        $enabled = $this->enabled();
        $dayOpen = in_array((int) $now->format('w'), $days, true);
        $timeOpen = $this->timeInRange($now->format('H:i'), $start, $end);

        return [
            'enabled' => $enabled,
            'open' => ! $enabled || ($dayOpen && $timeOpen),
            'now' => $now->format('H:i'),
            'day_of_week' => (int) $now->format('w'),
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'day_today_open' => $dayOpen,
            'message' => (string) $this->settings->get('workhours.message', ''),
        ];
    }

    /** آیا همین حالا باز است؟ */
    public function isOpen(?Carbon $at = null): bool
    {
        return $this->status($at)['open'];
    }

    /** روزهای کاری به‌صورت آرایه int (شماره Carbon: 0=یکشنبه … 6=شنبه) */
    public function days(): array
    {
        $raw = (string) $this->settings->get('workhours.days', '6,0,1,2,3,4');

        $days = collect(explode(',', $raw))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d >= 0 && $d <= 6)
            ->unique()
            ->values()
            ->all();

        return $days ?: [0, 1, 2, 3, 4, 5, 6];
    }

    /**
     * مقایسه‌ی HH:MM با پشتیبانی از بازه‌ی شبانه (مثلاً ۱۸:۰۰ تا ۰۲:۰۰).
     */
    protected function timeInRange(string $now, string $start, string $end): bool
    {
        if ($start === $end) {
            return true; // بازه‌ی کامل شبانه‌روزی
        }

        if ($start < $end) {
            // بازه‌ی معمولی (مثلاً ۰۸:۰۰ تا ۲۲:۰۰)
            return $now >= $start && $now <= $end;
        }

        // بازه‌ی عبور از نیمه‌شب (مثلاً ۱۸:۰۰ تا ۰۲:۰۰)
        return $now >= $start || $now <= $end;
    }

    /** نرمال‌سازی ورودی HH:MM با مقادیر جایگزین */
    protected function normalizeTime(mixed $value, string $fallback = '08:00'): string
    {
        $v = trim(en_digits((string) $value));

        return preg_match('/^\d{1,2}:\d{2}$/', $v) ? sprintf('%02d:%02d', ...explode(':', $v)) : $fallback;
    }
}
