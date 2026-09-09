<?php

namespace App\Http\Controllers\Back\Org;

use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Transaction;
use App\Models\Withdrawal;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $org = $request->attributes->get('current_organization');
        $wallet = $org->wallet;

        $stats = [
            'wallet_balance' => (float) $wallet?->balance ?? 0.0,
            'total_rewards' => (float) Transaction::query()
                ->where('ref_type', 'reward')
                ->whereHas('wallet', fn ($w) => $w->where('holder_type', \App\Models\Organization::class)
                    ->where('holder_id', $org->id))
                ->sum('amount'),
            'coffeenets_total' => $org->coffeenets()->count(),
            'coffeenets_approved' => $org->coffeenets()->where('status', 'approved')->count(),
            'coffeenets_pending' => $org->coffeenets()->where('status', 'pending')->count(),
            'withdrawals_pending' => $wallet ? Withdrawal::where('wallet_id', $wallet->id)->where('status', 'pending')->count() : 0,
            'withdrawals_total' => $wallet ? (float) Withdrawal::where('wallet_id', $wallet->id)->where('status', 'paid')->sum('amount') : 0.0,
        ];

        $recentCoffeenets = $org->coffeenets()->with('province:id,name')->latest('id')->limit(5)->get();

        // فاز ۹ — نمای تحلیلی ۳۰ روز اخیر (کافی‌نت‌های زیرمجموعه + واریزی کیف سازمان)
        $analytics = app(AnalyticsService::class)->forScope(['organization_id' => $org->id]);
        $from = now()->subDays(29)->startOfDay();
        $to = now()->endOfDay();

        $chartData = [
            'credits' => $wallet ? $analytics->walletCreditsDaily($wallet->id, $from, $to) : [],
            'coffeenets' => $analytics->coffeenetPerformance($from, $to, 8),
        ];

        return view('back.org.dashboard', [
            'organization' => $org,
            'stats' => $stats,
            'recentCoffeenets' => $recentCoffeenets,
            'chartData' => $chartData,
        ]);
    }
}
