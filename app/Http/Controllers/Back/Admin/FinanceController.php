<?php

namespace App\Http\Controllers\Back\Admin;

use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\CommissionPayout;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Finance\SettlementService;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * گزارش مالی جامع (فاز ۸) — تصویر کلان درآمد/کمیسیون/کیف پول‌های شبکه.
 */
class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        // حجم موفق: مجموع مبلغ سفارش‌های پرداخت‌شدهٔ حذف‌نشده
        $paidBase = \App\Models\Order::query()
            ->whereNotNull('paid_at')
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Refunded->value]);

        $commissionable = (clone $paidBase)->sum('commissionable_amount');
        $grossVolume = (clone $paidBase)->selectRaw('SUM(price + expenses)')->value('SUM(price + expenses)');

        $roleSums = CommissionPayout::query()
            ->selectRaw('role, SUM(amount) as total')
            ->groupBy('role')
            ->get()
            ->keyBy('role');

        $walletSums = Wallet::query()
            ->selectRaw('holder_type, COUNT(*) as cnt, SUM(balance) as total')
            ->groupBy('holder_type')
            ->get()
            ->keyBy('holder_type');

        $pendingWithdrawals = Withdrawal::query()->where('status', 'pending');

        // روند ۶ ماه اخیر (شمسی)
        $months = [];
        $now = now();
        for ($i = 5; $i >= 0; $i--) {
            $m = $now->copy()->startOfMonth()->subMonths($i);
            $from = $m->toDateTimeString();
            $to = $m->copy()->endOfMonth()->toDateTimeString();

            $paidCount = \App\Models\Order::query()
                ->whereBetween('paid_at', [$from, $to])
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Refunded->value])
                ->count();

            $settled = CommissionPayout::query()
                ->whereBetween('created_at', [$from, $to]);

            $months[] = [
                'label' => $m->format('%B %Y'),
                'label_short' => $m->format('%B'),
                'paid_orders' => $paidCount,
                'commissionable' => (float) \App\Models\Order::query()
                    ->whereBetween('paid_at', [$from, $to])
                    ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Refunded->value])
                    ->sum('commissionable_amount'),
                'platform' => (float) (clone $settled)->where('role', 'platform')->sum('amount'),
                'organization' => (float) (clone $settled)->where('role', 'organization')->sum('amount'),
                'coffeenet' => (float) (clone $settled)->where('role', 'coffeenet')->sum('amount'),
                'operator' => (float) (clone $settled)->where('role', 'operator')->sum('amount'),
            ];
        }

        return view('back.admin.finance.index', [
            'summary' => [
                'gross_volume' => (float) ($grossVolume ?? 0),
                'commissionable' => (float) $commissionable,
                'settled_total' => (float) CommissionPayout::sum('amount'),
                'platform' => (float) ($roleSums['platform']->total ?? 0),
                'organization' => (float) ($roleSums['organization']->total ?? 0),
                'coffeenet' => (float) ($roleSums['coffeenet']->total ?? 0),
                'operator' => (float) ($roleSums['operator']->total ?? 0),
                'platform_wallet_balance' => app(SettlementService::class)->platformUser()->wallet()->firstOrCreate([], ['balance' => 0])->balance,
            ],
            'wallets' => [
                'organizations' => ['count' => (int) ($walletSums[Organization::class]->cnt ?? 0), 'balance' => (float) ($walletSums[Organization::class]->total ?? 0)],
                'coffeenets' => ['count' => (int) ($walletSums[Coffeenet::class]->cnt ?? 0), 'balance' => (float) ($walletSums[Coffeenet::class]->total ?? 0)],
            ],
            'withdrawals' => [
                'pending_count' => (int) (clone $pendingWithdrawals)->count(),
                'pending_amount' => (float) (clone $pendingWithdrawals)->sum('amount'),
                'paid_amount' => (float) Withdrawal::where('status', 'paid')->sum('amount'),
            ],
            'months' => $months,
        ]);
    }

    /** همهٔ تراکنش‌های کیف پولی شبکه (AJAX + فیلتر دارنده/نوع + جستجو + بازهٔ تاریخ) */
    public function data(Request $request): JsonResponse
    {
        $rows = $this->filteredTransactions($request);

        $paginator = $rows->orderByDesc('id')->paginate(20);

        $refLabels = [
            'reward' => 'پاداش معرفی',
            'withdrawal' => 'برداشت',
            'order' => 'کمیسیون سفارش',
            'payment' => 'پرداخت',
            'salary' => 'حقوق',
        ];

        $rows = $paginator->through(function (Transaction $t) {
            $holderType = $t->wallet?->holder_type;
            $holderLabel = match ($holderType) {
                Organization::class => 'سازمان',
                Coffeenet::class => 'کافی‌نت',
                \App\Models\User::class => 'کاربر',
                default => '—',
            };

            return [
                'id' => $t->id,
                'type' => $t->type->value,
                'type_label' => $t->type === TransactionType::Credit ? 'واریز' : 'برداشت',
                'amount' => (float) $t->amount,
                'balance_after' => (float) $t->balance_after,
                'holder_label' => $holderLabel,
                'ref' => $t->ref_type ? ($refLabels[$t->ref_type] ?? $t->ref_type) : '—',
                'description' => $t->description ?? '—',
                'date' => $t->created_at ? jdate($t->created_at)->format('Y/m/d — H:i') : '—',
            ];
        });

        return response()->json($rows);
    }

    /** خروجی CSV همهٔ تراکنش‌ها با همان فیلترهای جدول (فاز ۹) */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filteredTransactions($request)
            ->with('wallet:id,holder_type,holder_id')
            ->orderByDesc('id')
            ->limit(10000)
            ->get();

        $refLabels = [
            'reward' => 'پاداش معرفی',
            'withdrawal' => 'برداشت',
            'order' => 'کمیسیون سفارش',
            'payment' => 'پرداخت',
            'salary' => 'حقوق',
        ];

        $stamp = now()->format('Ymd-His');

        return Csv::download("finance-transactions-{$stamp}.csv", [
            'شناسه', 'نوع', 'دارنده', 'مبلغ (تومان)', 'مانده پس از (تومان)', 'مرجع', 'توضیح', 'تاریخ شمسی',
        ], function () use ($rows, $refLabels) {
            foreach ($rows as $t) {
                $holderLabel = match ($t->wallet?->holder_type) {
                    Organization::class => 'سازمان',
                    Coffeenet::class => 'کافی‌نت',
                    \App\Models\User::class => 'کاربر',
                    default => '—',
                };

                yield [
                    $t->id,
                    $t->type === TransactionType::Credit ? 'واریز' : 'برداشت',
                    $holderLabel,
                    (float) $t->amount,
                    (float) $t->balance_after,
                    $t->ref_type ? ($refLabels[$t->ref_type] ?? $t->ref_type) : '—',
                    $t->description ?? '—',
                    $t->created_at ? jdate($t->created_at)->format('Y/m/d H:i') : '—',
                ];
            }
        });
    }

    /** کوئری مشترک فیلترهای تراکنش (جدول + خروجی CSV) */
    private function filteredTransactions(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Transaction::query()
            ->with('wallet:id,holder_type,holder_id');

        if ($holderType = (string) $request->query('holder')) {
            if (in_array($holderType, [Organization::class, Coffeenet::class, \App\Models\User::class], true)) {
                $query->whereHas('wallet', fn ($w) => $w->where('holder_type', $holderType));
            }
        }

        $type = (string) $request->query('type');
        if (in_array($type, ['credit', 'debit'], true)) {
            $query->where('type', $type);
        }

        if ($q = trim((string) $request->query('q'))) {
            $query->where('description', 'like', "%{$q}%");
        }

        if ($from = (string) $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = (string) $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }
}
