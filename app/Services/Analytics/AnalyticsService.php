<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Models\Coffeenet;
use App\Models\CommissionPayout;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * سرویس تحلیل داده — فاز ۹ (نمودارها و گزارش تحلیلی).
 *
 * یک سرویس مشترک برای هر ۴ پنل — دامنهٔ دید با «scope» تعیین می‌شود:
 *   global (ادمین)          → []
 *   کافی‌نت                  → ['coffeenet_id' => X]
 *   سازمان                  → ['organization_id' => Y] (همهٔ کافی‌نت‌های زیرمجموعه)
 *   اپراتور                  → ['operator_id' => Z] (+ coffeenet_id برای دید محدود)
 *
 * همهٔ بازه‌ها روی created_at سفارش (زمان «ثبت» سفارش) اعمال می‌شوند؛
 * تسویه‌ها و درآمد اپراتور بر اساس زمان payout.
 */
class AnalyticsService
{
    /** @var array<string,int|null> */
    protected array $scope = [];

    /** فعال‌سازی دامنهٔ دید — مقدار null یعنی بدون محدودیت */
    public function forScope(array $scope): static
    {
        $this->scope = [
            'coffeenet_id' => $scope['coffeenet_id'] ?? null,
            'organization_id' => $scope['organization_id'] ?? null,
            'operator_id' => $scope['operator_id'] ?? null,
        ];

        return $this;
    }

    /* ================== کوئری‌های پایه ================== */

    public function ordersQuery(): Builder
    {
        return Order::query()
            ->when($this->scope['coffeenet_id'], fn (Builder $q, int $v) => $q->where('orders.coffeenet_id', $v))
            ->when($this->scope['operator_id'], fn (Builder $q, int $v) => $q->where('orders.operator_id', $v))
            ->when($this->scope['organization_id'], function (Builder $q, int $v) {
                $q->whereHas('coffeenet', fn (Builder $c) => $c->where('organization_id', $v));
            });
    }

    public function payoutsQuery(): Builder
    {
        return CommissionPayout::query()
            ->when($this->scope['coffeenet_id'], fn (Builder $q, int $v) => $q->whereHas('order', fn (Builder $o) => $o->where('coffeenet_id', $v)))
            ->when($this->scope['organization_id'], function (Builder $q, int $v) {
                $q->whereHas('order.coffeenet', fn (Builder $c) => $c->where('organization_id', $v));
            })
            // سهم اپراتور روی خودِ دارنده فیلتر می‌شود (نه روی سفارش)
            ->when($this->scope['operator_id'], fn (Builder $q, int $v) => $q->where('role', 'operator')
                ->where('holder_type', User::class)->where('holder_id', $v));
    }

    /** اعمال بازهٔ تاریخ روی کوئری (public — کنترلر خروجی CSV هم استفاده می‌کند) */
    public function rangeWhere(Builder $query, string $column, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereBetween($column, [
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
        ]);
    }

    /* ================== سری روزانه ================== */

    /**
     * روند روزانه: سفارش ثبت‌شده / پرداخت‌شده / حجم پرداخت / مشمول کمیسیون.
     *
     * @return array<int,array{date:string,label:string,orders:int,paid:int,volume:float,commissionable:float}>
     */
    public function daily(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->rangeWhere($this->ordersQuery(), 'orders.created_at', $from, $to)
            ->selectRaw("date(orders.created_at) as d, COUNT(*) as cnt,
                SUM(CASE WHEN orders.paid_at IS NOT NULL AND orders.status NOT IN ('cancelled','refunded') THEN 1 ELSE 0 END) as paid_cnt,
                SUM(CASE WHEN orders.paid_at IS NOT NULL AND orders.status NOT IN ('cancelled','refunded') THEN orders.price + orders.expenses ELSE 0 END) as volume,
                SUM(CASE WHEN orders.paid_at IS NOT NULL AND orders.status NOT IN ('cancelled','refunded') THEN orders.commissionable_amount ELSE 0 END) as comm")
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $series = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to->copy()->endOfDay())) {
            $key = $cursor->toDateString();
            $r = $rows->get($key);

            $series[] = [
                'date' => $key,
                'label' => jdate($cursor)->format('m/d'),
                'orders' => (int) ($r->cnt ?? 0),
                'paid' => (int) ($r->paid_cnt ?? 0),
                'volume' => (float) ($r->volume ?? 0),
                'commissionable' => (float) ($r->comm ?? 0),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /* ================== تفکیک وضعیت ================== */

    /**
     * @return array<int,array{status:string,label:string,count:int,color:string}>
     */
    public function statusBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        $counts = $this->rangeWhere($this->ordersQuery(), 'orders.created_at', $from, $to)
            ->selectRaw('orders.status, COUNT(*) as cnt')
            ->groupBy('orders.status')
            ->pluck('cnt', 'status');

        $result = [];
        foreach (OrderStatus::cases() as $case) {
            $count = (int) ($counts[$case->value] ?? 0);
            if ($count === 0) {
                continue;
            }

            $result[] = [
                'status' => $case->value,
                'label' => $case->label(),
                'count' => $count,
                'color' => match ($case) {
                    OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Broadcasting, OrderStatus::Queued => 'amber',
                    OrderStatus::Accepted => 'teal',
                    OrderStatus::InProgress => 'copper',
                    OrderStatus::NeedsInfo => 'orange',
                    OrderStatus::Delivered => 'sky',
                    OrderStatus::Completed => 'emerald',
                    OrderStatus::Cancelled, OrderStatus::Refunded => 'rose',
                },
            ];
        }

        return $result;
    }

    /* ================== رتبه‌بندی‌ها ================== */

    /**
     * خدمات پرتقاضا — هر سفارش ثبت‌شده در بازه.
     *
     * @return array<int,array{id:int,name:string,count:int,volume:float}>
     */
    public function topServices(CarbonInterface $from, CarbonInterface $to, int $limit = 8): array
    {
        return $this->rangeWhere($this->ordersQuery(), 'orders.created_at', $from, $to)
            ->join('services', 'services.id', '=', 'orders.service_id')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get(['services.id', 'services.name', DB::raw('COUNT(*) as cnt'),
                DB::raw("SUM(CASE WHEN orders.paid_at IS NOT NULL AND orders.status NOT IN ('cancelled','refunded') THEN orders.price + orders.expenses ELSE 0 END) as volume")])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'count' => (int) $r->cnt,
                'volume' => (float) $r->volume,
            ])
            ->all();
    }

    /**
     * عملکرد اپراتورها — تعداد سفارش/تحویل + درآمد تسویه‌شده.
     *
     * @return array<int,array{id:int,name:string,count:int,delivered:int,earnings:float}>
     */
    public function topOperators(CarbonInterface $from, CarbonInterface $to, int $limit = 8): array
    {
        $orders = $this->rangeWhere($this->ordersQuery(), 'orders.created_at', $from, $to)
            ->whereNotNull('orders.operator_id')
            ->join('users', 'users.id', '=', 'orders.operator_id')
            ->groupBy('users.id', 'users.name', 'users.family')
            ->get(['users.id', 'users.name', 'users.family', DB::raw('COUNT(*) as cnt'),
                DB::raw("SUM(CASE WHEN orders.status IN ('delivered','completed') THEN 1 ELSE 0 END) as delivered")])
            ->keyBy('id');

        $earnings = $this->rangeWhere($this->payoutsQuery(), 'commission_payouts.created_at', $from, $to)
            ->where('role', 'operator')
            ->where('holder_type', User::class)
            ->groupBy('holder_id')
            ->selectRaw('holder_id, SUM(amount) as total')
            ->pluck('total', 'holder_id');

        $result = $orders->map(function ($r) use ($earnings) {
            return [
                'id' => (int) $r->id,
                'name' => trim(($r->name ?? '').' '.($r->family ?? '')) ?: '—',
                'count' => (int) $r->cnt,
                'delivered' => (int) $r->delivered,
                'earnings' => (float) ($earnings[$r->id] ?? 0),
            ];
        })->values();

        // اپراتورهایی که فقط درآمد دارند ولی سفارشی در بازه ندارند (نادر)
        foreach ($earnings as $operatorId => $total) {
            if (! $orders->has($operatorId) && $total > 0) {
                $user = User::find($operatorId);
                if ($user) {
                    $result->push([
                        'id' => (int) $operatorId,
                        'name' => trim($user->name.' '.$user->family) ?: '—',
                        'count' => 0,
                        'delivered' => 0,
                        'earnings' => (float) $total,
                    ]);
                }
            }
        }

        return $result->sortByDesc('delivered')->take($limit)->values()->all();
    }

    /**
     * مشتریان برتر — تعداد سفارش + مبلغ پرداخت‌شده.
     *
     * @return array<int,array{id:int,name:string,count:int,spent:float}>
     */
    public function topCustomers(CarbonInterface $from, CarbonInterface $to, int $limit = 8): array
    {
        return $this->rangeWhere($this->ordersQuery(), 'orders.created_at', $from, $to)
            ->join('users', 'users.id', '=', 'orders.customer_id')
            ->groupBy('users.id', 'users.name', 'users.family')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get(['users.id', 'users.name', 'users.family', DB::raw('COUNT(*) as cnt'),
                DB::raw("SUM(CASE WHEN orders.paid_at IS NOT NULL AND orders.status NOT IN ('cancelled','refunded') THEN orders.price + orders.expenses ELSE 0 END) as spent")])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => trim(($r->name ?? '').' '.($r->family ?? '')) ?: '—',
                'count' => (int) $r->cnt,
                'spent' => (float) $r->spent,
            ])
            ->all();
    }

    /**
     * عملکرد کافی‌نت‌ها — سفارش/حجم + کمیسیون تسویه‌شده.
     *
     * @return array<int,array{id:int,name:string,count:int,volume:float,commission:float}>
     */
    public function coffeenetPerformance(CarbonInterface $from, CarbonInterface $to, int $limit = 10): array
    {
        $orders = $this->rangeWhere($this->ordersQuery(), 'orders.created_at', $from, $to)
            ->join('coffeenets', 'coffeenets.id', '=', 'orders.coffeenet_id')
            ->groupBy('coffeenets.id', 'coffeenets.name')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get(['coffeenets.id', 'coffeenets.name', DB::raw('COUNT(*) as cnt'),
                DB::raw("SUM(CASE WHEN orders.paid_at IS NOT NULL AND orders.status NOT IN ('cancelled','refunded') THEN orders.price + orders.expenses ELSE 0 END) as volume")])
            ->keyBy('id');

        $commissions = $this->rangeWhere($this->payoutsQuery(), 'commission_payouts.created_at', $from, $to)
            ->where('role', 'coffeenet')
            ->where('holder_type', Coffeenet::class)
            ->groupBy('holder_id')
            ->selectRaw('holder_id, SUM(amount) as total')
            ->pluck('total', 'holder_id');

        return $orders->map(fn ($r) => [
            'id' => (int) $r->id,
            'name' => $r->name,
            'count' => (int) $r->cnt,
            'volume' => (float) $r->volume,
            'commission' => (float) ($commissions[$r->id] ?? 0),
        ])->values()->all();
    }

    /* ================== خلاصهٔ بازه ================== */

    /**
     * @return array{orders:int,paid:int,volume:float,commissionable:float,settled:float,operator_earnings:float,cancelled:int,customers:int,avg_paid:float}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = $this->rangeWhere($this->ordersQuery(), 'orders.created_at', $from, $to);

        $paidVolume = (clone $base)->whereNotNull('paid_at')
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Refunded->value]);

        $paidCount = (clone $paidVolume)->count();
        $volume = (float) (clone $paidVolume)->selectRaw('SUM(price + expenses)')->value('SUM(price + expenses)') ?: 0;

        $settled = $this->rangeWhere($this->payoutsQuery(), 'commission_payouts.created_at', $from, $to);

        return [
            'orders' => (clone $base)->count(),
            'paid' => $paidCount,
            'volume' => $volume,
            'commissionable' => (float) (clone $paidVolume)->sum('commissionable_amount'),
            'settled' => (float) (clone $settled)->sum('amount'),
            'operator_earnings' => (float) (clone $settled)->where('role', 'operator')->sum('amount'),
            'cancelled' => (clone $base)->where('status', OrderStatus::Cancelled->value)->count(),
            'customers' => (clone $base)->distinct('customer_id')->count('customer_id'),
            'avg_paid' => $paidCount > 0 ? round($volume / $paidCount) : 0.0,
        ];
    }

    /* ================== سری‌های ویژهٔ پنل‌ها ================== */

    /**
     * روند روزانهٔ اپراتور: تحویل‌ها + درآمد تسویه (برای داشبورد اپراتور).
     *
     * @return array<int,array{date:string,label:string,delivered:int,earnings:float}>
     */
    public function operatorDaily(int $operatorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $delivered = Order::query()
            ->where('operator_id', $operatorId)
            ->whereIn('status', [OrderStatus::Delivered->value, OrderStatus::Completed->value])
            ->whereBetween('delivered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('date(delivered_at) as d, COUNT(*) as cnt')
            ->groupBy('d')
            ->pluck('cnt', 'd');

        $earnings = CommissionPayout::query()
            ->where('role', 'operator')
            ->where('holder_type', User::class)
            ->where('holder_id', $operatorId)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('date(created_at) as d, SUM(amount) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to->copy()->endOfDay())) {
            $key = $cursor->toDateString();

            $series[] = [
                'date' => $key,
                'label' => jdate($cursor)->format('m/d'),
                'delivered' => (int) ($delivered[$key] ?? 0),
                'earnings' => (float) ($earnings[$key] ?? 0),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * روند روزانهٔ واریزی کیف پول (کمیسیون/پاداش) — برای داشبورد سازمان و کافی‌نت.
     *
     * @return array<int,array{date:string,label:string,credit:float}>
     */
    public function walletCreditsDaily(int $walletId, CarbonInterface $from, CarbonInterface $to): array
    {
        $credits = Transaction::query()
            ->where('wallet_id', $walletId)
            ->where('type', 'credit')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('date(created_at) as d, SUM(amount) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to->copy()->endOfDay())) {
            $key = $cursor->toDateString();

            $series[] = [
                'date' => $key,
                'label' => jdate($cursor)->format('m/d'),
                'credit' => (float) ($credits[$key] ?? 0),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /* ================== بازه‌های آماده (شمسی) ================== */

    /**
     * تبدیل پارامترهای فیلتر به بازهٔ carbon — پریست یا از/تا.
     *
     * @return array{0:Carbon,1:Carbon,2:string}
     */
    public static function resolveRange(?string $preset, ?string $from, ?string $to): array
    {
        if ($from && $to && strtotime($from) && strtotime($to)) {
            $f = Carbon::parse($from)->startOfDay();
            $t = Carbon::parse($to)->endOfDay();

            if ($f->gt($t)) {
                [$f, $t] = [$t->copy()->startOfDay(), $f->copy()->endOfDay()];
            }

            // سقف بازهٔ سفارشی: ۳۶۶ روز
            if ($f->copy()->addDays(366)->lt($t)) {
                $t = $f->copy()->addDays(365);
            }

            return [$f, $t, 'custom'];
        }

        return match ($preset) {
            '7' => [now()->subDays(6)->startOfDay(), now()->endOfDay(), '7'],
            '90' => [now()->subDays(89)->startOfDay(), now()->endOfDay(), '90'],
            'month' => [
                jdate(now())->getFirstDayOfMonth()->toCarbon()->startOfDay(),
                now()->endOfDay(),
                'month',
            ],
            'last_month' => self::lastJalaliMonth(),
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay(), '30'],
        };
    }

    /** @return array{0:Carbon,1:Carbon,2:string} */
    protected static function lastJalaliMonth(): array
    {
        $start = jdate(now())->subMonths(1)->getFirstDayOfMonth()->toCarbon()->startOfDay();
        $end = jdate(now())->getFirstDayOfMonth()->toCarbon()->subDay()->endOfDay();

        return [$start, $end, 'last_month'];
    }
}
