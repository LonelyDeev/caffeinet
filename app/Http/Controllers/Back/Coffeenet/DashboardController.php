<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Order;
use App\Models\SalaryLog;
use App\Models\StaffAssignment;
use App\Services\Analytics\AnalyticsService;
use App\Services\Finance\WalletService;
use App\Services\Orders\OrderAssignmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
    ) {
    }

    /** داشبورد مدیر کافی‌نت */
    public function index(Request $request, Coffeenet $coffeenet): View
    {
        abort_unless($request->attributes->get('current_coffeenet')?->id === $coffeenet->id, 403);

        // فاز ۶ — انقضای تنبل پخش
        app(OrderAssignmentService::class)->expireStale();

        $currentPeriod = now()->format('Y-m');
        $lastPeriod = now()->subMonth()->format('Y-m');

        $staffQuery = $coffeenet->staffAssignments()->where('is_active', true);

        // سفارش‌های پخش‌شدهٔ در انتظار پذیرش این کافی‌نت
        $broadcastingIds = $coffeenet->broadcasts()
            ->whereHas('order', fn ($q) => $q->where('status', 'broadcasting'))
            ->pluck('order_id');

        $stats = [
            'staff_total' => (clone $staffQuery)->count(),
            'operators' => (clone $staffQuery)->where('position', 'operator')->count(),
            'managers' => (clone $staffQuery)->where('position', 'manager')->count(),
            'salary_current' => (float) SalaryLog::where('coffeenet_id', $coffeenet->id)->where('period', $currentPeriod)->sum('amount'),
            'salary_last' => (float) SalaryLog::where('coffeenet_id', $coffeenet->id)->where('period', $lastPeriod)->sum('amount'),
            'wallet_balance' => $this->wallets->balance($coffeenet),
            'orders_broadcasting' => $broadcastingIds->count(),
            'orders_active' => Order::where('coffeenet_id', $coffeenet->id)
                ->whereIn('status', ['accepted', 'in_progress', 'needs_info'])->count(),
            'orders_done' => Order::where('coffeenet_id', $coffeenet->id)
                ->whereIn('status', ['delivered', 'completed'])->count(),
        ];

        $recentStaff = $coffeenet->staffAssignments()
            ->with([
                'user:id,name,family,last_login_at',
                'salarySetting' => fn ($q) => $q->where('salary_settings.coffeenet_id', $coffeenet->id),
            ])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // توزیع مدل‌های حقوقی کارمندان فعال
        $salaryMix = $coffeenet->staffAssignments()
            ->where('is_active', true)
            ->with(['salarySetting' => fn ($q) => $q->where('salary_settings.coffeenet_id', $coffeenet->id)])
            ->get()
            ->filter(fn ($s) => $s->salarySetting?->type !== null)
            ->groupBy(fn ($s) => $s->salarySetting->type->value)
            ->map(fn ($group) => $group->count());

        // فاز ۹ — نمای تحلیلی ۱۴ روز اخیر (سفارش‌های این کافی‌نت + واریزی کیف)
        $analytics = app(AnalyticsService::class)->forScope(['coffeenet_id' => $coffeenet->id]);
        $from = now()->subDays(13)->startOfDay();
        $to = now()->endOfDay();

        $wallet = \App\Models\Wallet::query()
            ->where('holder_type', Coffeenet::class)
            ->where('holder_id', $coffeenet->id)
            ->first();

        $chartData = [
            'daily' => $analytics->daily($from, $to),
            'status' => $analytics->statusBreakdown($from, $to),
            'credits' => $wallet ? $analytics->walletCreditsDaily($wallet->id, $from, $to) : [],
        ];

        return view('back.coffeenet.dashboard', [
            'coffeenet' => $coffeenet,
            'stats' => $stats,
            'recentStaff' => $recentStaff,
            'salaryMix' => $salaryMix,
            'currentPeriodLabel' => jdate(now())->format('F Y'),
            'chartData' => $chartData,
        ]);
    }
}
