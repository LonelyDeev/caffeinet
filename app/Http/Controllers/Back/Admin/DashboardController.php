<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Coffeenet;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Analytics\AnalyticsService;
use App\Services\Orders\OrderAssignmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, OrderAssignmentService $assignment): View
    {
        // فاز ۶ — انقضای تنبل پخش پیش از شمارش
        $assignment->expireStale();

        $user = $request->user();

        $stats = [
            'users_total' => User::count(),
            'users_new_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'organizations_total' => Organization::count(),
            'organizations_pending' => Organization::where('status', 'pending')->count(),
            'coffeenets_total' => Coffeenet::count(),
            'coffeenets_pending' => Coffeenet::where('status', 'pending')->count(),
            'services_active' => Service::where('is_active', true)->count(),
            'transactions_today' => (int) Transaction::where('type', 'credit')
                ->whereDate('created_at', today())->sum('amount'),
            'orders_broadcasting' => Order::where('status', 'broadcasting')->count(),
            'orders_queued' => Order::where('status', 'queued')->count(),
            'orders_active' => Order::whereIn('status', ['accepted', 'in_progress', 'needs_info'])->count(),
        ];

        $pendingItems = [
            [
                'title' => 'سفارش در صف تعیین‌تکلیف',
                'count' => $stats['orders_queued'],
                'url' => route('admin.orders.index', ['status' => 'queued']),
                'icon' => 'orders',
            ],
            [
                'title' => 'سازمان در انتظار بررسی',
                'count' => $stats['organizations_pending'],
                'url' => route('admin.organizations.index', ['status' => 'pending']),
                'icon' => 'building',
            ],
            [
                'title' => 'کافی‌نت در انتظار تأیید',
                'count' => $stats['coffeenets_pending'],
                'url' => route('admin.coffeenets.index', ['status' => 'pending']),
                'icon' => 'store',
            ],
            [
                'title' => 'درخواست برداشت در انتظار پرداخت',
                'count' => Withdrawal::where('status', 'pending')->count(),
                'url' => route('admin.withdrawals.index', ['status' => 'pending']),
                'icon' => 'wallet',
            ],
        ];

        // v36 — کاربران آنلاین (همان آستانهٔ «آفلاین» تنظیمات که پوش سیستمی
        // هم از آن استفاده می‌کند؛ مشتری‌ها و کارمندان جدا)
        $onlineSince = now()->subSeconds(offline_threshold_seconds());

        $roleLabels = [
            'super_admin' => 'مدیر کل',
            'admin' => 'مدیر دستیار',
            'org_manager' => 'مدیر سازمان',
            'coffeenet_manager' => 'مدیر کافی‌نت',
            'operator' => 'اپراتور',
        ];

        $onlineCustomers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'customer'))
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $onlineSince)
            ->orderByDesc('last_seen_at')
            ->limit(12)
            ->get(['id', 'name', 'family', 'mobile', 'last_seen_at']);

        $onlineStaff = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn(
                'name',
                ['super_admin', 'admin', 'org_manager', 'coffeenet_manager', 'operator'],
            ))
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $onlineSince)
            ->orderByDesc('last_seen_at')
            ->limit(12)
            ->get(['id', 'name', 'family', 'mobile', 'last_seen_at'])
            ->map(function (User $u) use ($roleLabels) {
                $u->position_label = $roleLabels[$u->getRoleNames()->first()] ?? 'کارمند';

                return $u;
            });

        $onlineUsers = [
            'customers' => $onlineCustomers,
            'staff' => $onlineStaff,
        ];

        $recentAudits = AuditLog::with('user')
            ->latest('id')
            ->limit(6)
            ->get();

        // فاز ۹ — نمای تحلیلی ۱۴ روز اخیر (سراسری)
        $analytics = app(AnalyticsService::class)->forScope([]);
        $from = now()->subDays(13)->startOfDay();
        $to = now()->endOfDay();

        $chartData = [
            'daily' => $analytics->daily($from, $to),
            'status' => $analytics->statusBreakdown($from, $to),
            'paid_total' => (clone $analytics->ordersQuery())
                ->whereBetween('created_at', [$from, $to])
                ->whereNotNull('paid_at')
                ->whereNotIn('status', [\App\Enums\OrderStatus::Cancelled->value, \App\Enums\OrderStatus::Refunded->value])
                ->count(),
        ];

        return view('back.admin.dashboard', [
            'user' => $user,
            'stats' => $stats,
            'pendingItems' => $pendingItems,
            'onlineUsers' => $onlineUsers,
            'recentAudits' => $recentAudits,
            'chartData' => $chartData,
        ]);
    }
}
