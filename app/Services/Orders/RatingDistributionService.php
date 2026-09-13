<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Coffeenet;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * v33 — پخش هوشمند سفارش بر اساس امتیاز کافی‌نت‌ها.
 *
 * سناریوی سه‌حالته (از تنظیمات ← تب «نظرسنجی»):
 *
 *  ۱) filter (سخت‌گیرانه):
 *     فقط کافی‌netهای «واجد شرایط» سفارش را می‌گیرند:
 *       میانگین امتیاز ≥ حداقل امتیاز  +  تعداد نظرات ≥ حداقل رأی
 *     (کافی‌netهای بدون امتیاز معتبر طبق سیاست unrated). اگر هیچ‌کدام واجد
 *     شرایط نباشد، سفارش به صف تعیین‌تکلیف می‌رود (تخصیص دستی ادمین).
 *
 *  ۲) priority (نرم):
 *     همهٔ کافی‌netها سفارش را می‌گیرند، اما پخش/اعلان‌ها به‌ترتیب امتیاز
 *     انجام می‌شود — امتیاز بالاتر = زودتر خبردار، شانس بیشتر در پذیرش.
 *
 *  ۳) hybrid (پیش‌فرض و پیشنهادی):
 *     ابتدا مثل filter فقط واجد شرایط‌ها؛ اگر هیچ‌کدام نبود، به‌جای گیر
 *     کردن، به همهٔ کافی‌netها پخش می‌شود (سفارش هرگز بی‌صاحب نمی‌ماند).
 *
 * آمار هر کافی‌net (میانگین + تعداد رأی) روی نظرسنجی سفارش‌های تحویل‌شده/
 * تکمیل‌شدهٔ همان کافی‌net است و ۵ دقیقه کش می‌شود؛ با ثبت هر نظر جدید
 * کش فوراً باطل می‌شود (flush).
 */
class RatingDistributionService
{
    protected const CACHE_KEY = 'ratings.coffeenet_stats';
    protected const CACHE_TTL = 300; // ثانیه

    public function __construct(protected SettingsService $settings) {}

    /** پاک‌سازی کش آمار (بعد از ثبت نظر جدید) */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * آمار امتیاز کافی‌netها: [id => ['avg' => float, 'votes' => int]]
     * فقط سفارش‌های delivered/completed با امتیاز ثبت‌شده.
     */
    public function coffeenetStats(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(self::CACHE_TTL), function () {
            $rows = DB::table('order_ratings as r')
                ->join('orders as o', 'o.id', '=', 'r.order_id')
                ->whereNotNull('o.coffeenet_id')
                ->whereNull('o.deleted_at')
                ->whereIn('o.status', [OrderStatus::Delivered->value, OrderStatus::Completed->value])
                ->whereNotNull('r.rating')
                ->groupBy('o.coffeenet_id')
                ->selectRaw('o.coffeenet_id as cid, AVG(r.rating) as avg_rating, COUNT(*) as votes')
                ->get();

            return $rows->mapWithKeys(fn ($row) => [
                (int) $row->cid => ['avg' => round((float) $row->avg_rating, 2), 'votes' => (int) $row->votes],
            ])->all();
        });
    }

    /**
     * اعمال سیاست پخش هوشمند روی لیست هدف.
     *
     * @param  Collection<int, Coffeenet>  $targets
     * @return array{0: Collection<int, Coffeenet>, 1: ?string} [اسامی نهایی, یادداشت تاریخچه]
     */
    public function apply(Collection $targets): array
    {
        if (! $this->settings->get('ratings.routing_enabled', false)) {
            return [$targets, null]; // خاموش — رفتار قبلی بدون تغییر
        }

        $mode = (string) $this->settings->get('ratings.routing_mode', 'hybrid');
        $minRating = (int) $this->settings->get('ratings.routing_min_rating', 3);
        $minVotes = (int) $this->settings->get('ratings.routing_min_votes', 3);
        $unrated = (string) $this->settings->get('ratings.routing_unrated_policy', 'include');
        $stats = $this->coffeenetStats();

        $eligible = $targets->filter(function (Coffeenet $net) use ($stats, $minRating, $minVotes, $unrated) {
            $stat = $stats[$net->id] ?? null;

            // بدون امتیاز معتبر — طبق سیاست
            if (! $stat || $stat['votes'] < $minVotes) {
                return $unrated === 'include';
            }

            return $stat['avg'] >= $minRating;
        })->values();

        $note = 'پخش هوشمند فعال ('.$this->modeLabel($mode).') — حداقل امتیاز '.fa_digits((string) $minRating)
            .' با '.fa_digits((string) $minVotes).' رأی: '
            .fa_digits((string) $eligible->count()).' از '.fa_digits((string) $targets->count()).' کافی‌net واجد شرایط';

        if ($mode === 'priority') {
            // همه می‌مانند؛ فقط اولویت اعلان/پخش بر اساس امتیاز
            $sorted = $targets->sortByDesc(fn (Coffeenet $net) => ($stats[$net->id]['avg'] ?? -1))
                ->values();

            return [$sorted, $note];
        }

        // filter / hybrid
        if ($eligible->isNotEmpty()) {
            return [$eligible, $note];
        }

        if ($mode === 'filter') {
            // سخت‌گیرانه: هیچ واجد شرایطی نیست → لیست خالی → صف تعیین‌تکلیف (مسیر موجود)
            return [$eligible, $note.' — واجدی نبود؛ سفارش به صف تعیین‌تکلیف می‌رود'];
        }

        // hybrid: fallback به همه — سفارش هرگز بی‌صاحب نمی‌ماند
        return [$targets->values(), $note.' — واجدی نبود؛ به همهٔ کافی‌netها پخش شد (fallback)'];
    }

    protected function modeLabel(string $mode): string
    {
        return match ($mode) {
            'filter' => 'فیلتر سخت‌گیرانه',
            'priority' => 'اولویت‌بندی',
            default => 'هوشمند ترکیبی',
        };
    }

    /** خلاصهٔ وضعیت فعلی برای نمایش در تنظیمات/UI */
    public function summary(): array
    {
        $stats = $this->coffeenetStats();
        $minRating = (int) $this->settings->get('ratings.routing_min_rating', 3);
        $minVotes = (int) $this->settings->get('ratings.routing_min_votes', 3);

        $approved = Coffeenet::query()
            ->where('status', \App\Enums\CoffeenetStatus::Approved->value)
            ->count();

        $eligible = collect($stats)
            ->filter(fn ($s) => $s['votes'] >= $minVotes && $s['avg'] >= $minRating)
            ->count();

        return [
            'enabled' => (bool) $this->settings->get('ratings.routing_enabled', false),
            'mode' => (string) $this->settings->get('ratings.routing_mode', 'hybrid'),
            'approved' => $approved,
            'eligible' => $eligible,
            'avg_top' => $stats ? round(collect($stats)->max('avg'), 1) : null,
        ];
    }
}
