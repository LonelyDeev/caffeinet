<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Transaction;
use App\Services\Finance\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * کیف پول کافی‌نت (فاز ۸) — موجودی + گردش + درآمد کمیسیون سفارش‌ها.
 */
class WalletController extends Controller
{
    public function index(Request $request, Coffeenet $coffeenet): View
    {
        $wallet = $coffeenet->wallet()->firstOrCreate([], ['balance' => 0]);

        $summary = [
            'total_credit' => (float) Transaction::where('wallet_id', $wallet->id)
                ->where('type', TransactionType::Credit)->sum('amount'),
            'total_debit' => (float) Transaction::where('wallet_id', $wallet->id)
                ->where('type', TransactionType::Debit)->sum('amount'),
            'commission' => (float) Transaction::where('wallet_id', $wallet->id)
                ->where('type', TransactionType::Credit)
                ->where('ref_type', 'order')->sum('amount'),
        ];

        return view('back.coffeenet.wallet.index', [
            'coffeenet' => $coffeenet,
            'wallet' => $wallet,
            'summary' => $summary,
        ]);
    }

    /** تراکنش‌های کیف پول (AJAX + صفحه‌بندی + فیلتر نوع) */
    public function data(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $wallet = $coffeenet->wallet()->firstOrCreate([], ['balance' => 0]);

        $type = (string) $request->query('type');
        $query = Transaction::where('wallet_id', $wallet->id)->orderByDesc('id');

        if (in_array($type, ['credit', 'debit'], true)) {
            $query->where('type', $type);
        }

        $paginator = $query->paginate(20);

        $refLabels = [
            'reward' => 'پاداش معرفی',
            'withdrawal' => 'برداشت',
            'order' => 'کمیسیون سفارش',
            'payment' => 'پرداخت',
            'salary' => 'حقوق',
        ];

        $rows = $paginator->through(fn (Transaction $t) => [
            'id' => $t->id,
            'type' => $t->type->value,
            'type_label' => $t->type === TransactionType::Credit ? 'واریز' : 'برداشت',
            'amount' => (float) $t->amount,
            'balance_after' => (float) $t->balance_after,
            'ref' => $t->ref_type ? ($refLabels[$t->ref_type] ?? $t->ref_type) : '—',
            'description' => $t->description ?? '—',
            'date' => $t->created_at ? jdate($t->created_at)->format('Y/m/d — H:i') : '—',
        ]);

        return response()->json($rows);
    }
}
