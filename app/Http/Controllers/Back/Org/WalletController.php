<?php

namespace App\Http\Controllers\Back\Org;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        $wallet = $org->wallet()->firstOrCreate([], ['balance' => 0]);

        $summary = [
            'total_credit' => (float) Transaction::where('wallet_id', $wallet->id)
                ->where('type', TransactionType::Credit)->sum('amount'),
            'total_debit' => (float) Transaction::where('wallet_id', $wallet->id)
                ->where('type', TransactionType::Debit)->sum('amount'),
            'rewards' => (float) Transaction::where('wallet_id', $wallet->id)
                ->where('ref_type', 'reward')->sum('amount'),
        ];

        return view('back.org.wallet.index', [
            'organization' => $org,
            'wallet' => $wallet,
            'summary' => $summary,
        ]);
    }

    /** تراکنش‌های کیف پول (AJAX + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        $wallet = $org->wallet()->firstOrCreate([], ['balance' => 0]);

        $type = (string) $request->query('type');
        $query = Transaction::where('wallet_id', $wallet->id)
            ->orderByDesc('id');

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
