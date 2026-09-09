<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * روند کاری بازه‌ای (درخواست بازخوردی ۶-۱/۶-۲) — سری زمانی سفارش‌ها
 * با تفکیک روزانه/هفتگی/ماهانه/سالانه یا بازهٔ تاریخ دلخواه.
 *
 * اسکوپ:
 *  - operator → سفارش‌های اپراتور مشخص (operator_id + coffeenet_id)
 *  - coffeenet → همهٔ سفارش‌های کافی‌نت
 *
 * خروجی: آرایهٔ [label(شمسی), orders, done, volume] برای Chart.js.
 */
class WorkTrendService
{
    public const PRESETS = ['daily', 'weekly', 'monthly', 'yearly', 'custom'];

    /** بازهٔ زمانی بر اساس پریست — [from, to, resolution] */
    public static function resolveRange(?string $preset, ?string $from, ?string $to): array
    {
        $preset = in_array($preset, self::PRESETS, true) ? $preset : 'daily';

        return match ($preset) {
            'weekly' => [now()->subWeeks(11)->startOfWeek(), now()->endOfWeek(), 'week'],
            'monthly' => [now()->subMonths(11)->startOfMonth(), now()->endOfMonth(), 'month'],
            'yearly' => [now()->subYears(4)->startOfYear(), now()->endOfYear(), 'year'],
            'custom' => [
                Carbon::parse($from ?? now()->subDays(29))->startOfDay(),
                Carbon::parse($to ?? now())->endOfDay(),
                'auto',
            ],
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay(), 'day'],
        };
    }

    /**
     * سری روند — سفارش‌های اسکوپ‌شده در بازه.
     *
     * @param  array{operator_id?:int, coffeenet_id?:int, customer_id?:int}  $scope
     * @return array{preset:string, from:string, to:string, label:string, resolution:string, series:array<int, array{key:string,label:string,orders:int,done:int,volume:float}>}
     */
    public function series(string $preset, CarbonInterface $from, CarbonInterface $to, string $resolution, array $scope = []): array
    {
        $query = Order::query();
        if (! empty($scope['operator_id'])) {
            $query->where('operator_id', $scope['operator_id']);
        }
        if (! empty($scope['coffeenet_id'])) {
            $query->where('coffeenet_id', $scope['coffeenet_id']);
        }
        if (! empty($scope['customer_id'])) {
            $query->where('customer_id', $scope['customer_id']);
        }

        // تفکیک خودکار برای بازهٔ دلخواه (طول بازه → رزولوشن)
        if ($resolution === 'auto') {
            $days = $from->diffInDays($to) + 1;
            $resolution = $days <= 62 ? 'day' : ($days <= 168 ? 'week' : 'month');
        }

        [$groupExpr, $cursorStep, $labelFmt] = match ($resolution) {
            'week' => ["strftime('%Y-%W', orders.created_at)", 'week', 'Y/m/d'],
            'month' => ["strftime('%Y-%m', orders.created_at)", 'month', 'Y/m'],
            'year' => ["strftime('%Y', orders.created_at)", 'year', 'Y'],
            default => ["date(orders.created_at)", 'day', 'm/d'],
        };

        $doneStatuses = [OrderStatus::Delivered->value, OrderStatus::Completed->value];

        $rows = $query
            ->whereBetween('orders.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw("{$groupExpr} as bucket, COUNT(*) as cnt,
                SUM(CASE WHEN orders.status IN ('".implode("','", $doneStatuses)."') THEN 1 ELSE 0 END) as done_cnt,
                SUM(CASE WHEN orders.paid_at IS NOT NULL AND orders.status NOT IN ('cancelled','refunded') THEN orders.price ELSE 0 END) as volume")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        // پرکردن گپ‌ها با صفر — برش‌زنی بازه با cursor
        $series = [];
        $cursor = $this->startOfBucket($from->copy(), $resolution);
        $end = $to->copy()->endOfDay();

        while ($cursor->lte($end)) {
            $key = $this->bucketKey($cursor, $resolution);
            $r = $rows->get($key);

            $series[] = [
                'key' => $key,
                'label' => $this->bucketLabel($cursor, $resolution, $labelFmt),
                'orders' => (int) ($r->cnt ?? 0),
                'done' => (int) ($r->done_cnt ?? 0),
                'volume' => (float) ($r->volume ?? 0),
            ];

            match ($resolution) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                'year' => $cursor->addYear(),
                default => $cursor->addDay(),
            };
        }

        return [
            'preset' => $preset,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'label' => jdate($from)->format('Y/m/d').' تا '.jdate($to)->format('Y/m/d'),
            'resolution' => $resolution,
            'series' => $series,
        ];
    }

    /** شروع باکت cursor (تراز با ابتدای روز/هفته/ماه/سال) */
    protected function startOfBucket(Carbon $date, string $resolution): Carbon
    {
        return match ($resolution) {
            'week' => $date->startOfWeek(),
            'month' => $date->startOfMonth(),
            'year' => $date->startOfYear(),
            default => $date->startOfDay(),
        };
    }

    /** کلید باکت مطابق عبارت SQL (تقریب کافی برای match) */
    protected function bucketKey(Carbon $date, string $resolution): string
    {
        return match ($resolution) {
            'week' => $date->format('Y-').str_pad((string) $this->isoWeek($date), 2, '0', STR_PAD_LEFT),
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
            default => $date->toDateString(),
        };
    }

    /** هفتهٔ ISO مطابق strftime %W (هفته از دوشنبه) */
    protected function isoWeek(Carbon $date): int
    {
        // strftime %W: هفتهٔ سال با شروع دوشنبه، روز اولِ سال = هفتهٔ ۰۰
        $monday = $date->copy()->startOfWeek(Carbon::MONDAY);

        $firstDay = $date->copy()->startOfYear();
        $firstWeekMonday = $firstDay->copy()->startOfWeek(Carbon::MONDAY);
        if ($firstWeekMonday->gt($firstDay)) {
            $firstDay = $firstWeekMonday;
        }

        return (int) ($firstDay->diffInWeeks($monday) + 1);
    }

    /** برچسب شمسی باکت */
    protected function bucketLabel(Carbon $date, string $resolution, string $fmt): string
    {
        if ($resolution === 'week') {
            return jdate($date)->format('Y/m/d');
        }

        return jdate($date)->format($fmt);
    }
}
