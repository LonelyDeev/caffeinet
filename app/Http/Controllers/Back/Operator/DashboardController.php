<?php

namespace App\Http\Controllers\Back\Operator;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StaffAssignment;
use App\Services\Analytics\AnalyticsService;
use App\Support\OperatorPermissions;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * داشبورد پنل اپراتور (فاز ۷ — زیرساخت).
 *
 * آمار بر اساس دسترسی‌های اپراتور (staff_assignments.permissions):
 *  - orders.view → دید همه سفارش‌های کافی‌نت
 *  - orders.view.own → فقط سفارش‌های خودش
 *  - dashboard.access → اجازه مشاهده داشبورد (در غیر این صورت پیام محدودیت)
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var StaffAssignment $assignment */
        $assignment = $request->attributes->get('operator_assignment');
        $coffeenet = $request->attributes->get('current_coffeenet');
        $user = $request->user();

        $permissions = OperatorPermissions::filter($assignment->permissions ?? []);
        $canAll = in_array('orders.view', $permissions, true);
        $canOwn = in_array('orders.view.own', $permissions, true);
        $canDashboard = in_array('dashboard.access', $permissions, true);

        // بازهٔ ماه جاری (شمسی) برای «تحویل‌شدهٔ این ماه»
        $monthStart = now()->startOfMonth();

        $myActive = Order::query()
            ->where('operator_id', $user->id)
            ->where('coffeenet_id', $coffeenet->id)
            ->whereIn('status', [OrderStatus::Accepted->value, OrderStatus::InProgress->value])
            ->count();

        $myNeedsInfo = Order::query()
            ->where('operator_id', $user->id)
            ->where('coffeenet_id', $coffeenet->id)
            ->where('status', OrderStatus::NeedsInfo->value)
            ->count();

        $myDeliveredMonth = Order::query()
            ->where('operator_id', $user->id)
            ->where('coffeenet_id', $coffeenet->id)
            ->whereIn('status', [OrderStatus::Delivered->value, OrderStatus::Completed->value])
            ->where('delivered_at', '>=', $monthStart)
            ->count();

        $myTotal = Order::query()
            ->where('operator_id', $user->id)
            ->where('coffeenet_id', $coffeenet->id)
            ->whereNot('status', OrderStatus::Cancelled->value)
            ->count();

        $netOpen = $canAll
            ? Order::query()
                ->where('coffeenet_id', $coffeenet->id)
                ->whereIn('status', [OrderStatus::Accepted->value, OrderStatus::InProgress->value, OrderStatus::NeedsInfo->value])
                ->count()
            : null;

        // آخرین سفارش‌ها در محدودهٔ مجاز
        $recent = collect();

        if ($canAll || $canOwn) {
            $recent = Order::query()
                ->where('coffeenet_id', $coffeenet->id)
                ->when(! $canAll, fn ($q) => $q->where('operator_id', $user->id))
                ->whereIn('status', [
                    OrderStatus::Accepted->value,
                    OrderStatus::InProgress->value,
                    OrderStatus::NeedsInfo->value,
                    OrderStatus::Delivered->value,
                    OrderStatus::Completed->value,
                ])
                ->with([
                    'service' => fn ($q) => $q->select(['id', 'name']),
                    'service.category' => fn ($q) => $q->select(['id', 'name', 'icon']),
                    'customer' => fn ($q) => $q->select(['id', 'name', 'family']),
                ])
                ->orderByDesc('id')
                ->limit(8)
                ->get();
        }

        $stats = [
            'my_active' => $myActive,
            'my_needs_info' => $myNeedsInfo,
            'my_delivered_month' => $myDeliveredMonth,
            'my_total' => $myTotal,
            'net_open' => $netOpen,
        ];

        // فاز ۹ — روند کارهای من (تحویل + درآمد) — ۱۴ روز اخیر
        $chartData = null;
        if ($canDashboard) {
            $analytics = app(AnalyticsService::class);
            $chartData = [
                'daily' => $analytics->operatorDaily($user->id, now()->subDays(13)->startOfDay(), now()->endOfDay()),
            ];
        }

        return view('back.operator.dashboard', [
            'coffeenet' => $coffeenet,
            'assignment' => $assignment,
            'permissions' => $permissions,
            'permissionLabels' => OperatorPermissions::CATALOG,
            'canAll' => $canAll,
            'canOwn' => $canOwn,
            'canViewOrders' => $canAll || $canOwn,
            'canDashboard' => $canDashboard,
            'stats' => $stats,
            'recent' => $recent,
            'monthLabel' => jdate(now())->format('F Y'),
            'chartData' => $chartData,
        ]);
    }
}
