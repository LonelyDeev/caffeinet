<?php

namespace App\Http\Controllers\Back\Operator;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use App\Models\Transaction;
use App\Enums\TransactionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * درآمد و کیف پول اپراتور (فاز ۸) — موجودی + سابقهٔ تسویهٔ سهم سفارش‌ها.
 * کیف پول اپراتور سراسری است (مربوط به خودِ کاربر)؛ درآمد همهٔ کافی‌نت‌های محل کار در آن جمع می‌شود.
 */
class EarningsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrCreate([], ['balance' => 0]);

        $payouts = CommissionPayout::query()
            ->where('role', 'operator')
            ->where('holder_type', \App\Models\User::class)
            ->where('holder_id', $user->id);

        $startOfMonth = now()->startOfMonth();

        return view('back.operator.earnings.index', [
            'wallet' => $wallet,
            'summary' => [
                'total' => (float) (clone $payouts)->sum('amount'),
                'orders' => (int) (clone $payouts)->distinct('order_id')->count('order_id'),
                'month' => (float) (clone $payouts)->where('created_at', '>=', $startOfMonth)->sum('amount'),
                'last_payout' => (clone $payouts)->latest('id')->first(),
            ],
        ]);
    }

    /** سابقهٔ تسویه‌های سهم اپراتور (AJAX + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CommissionPayout::query()
            ->where('role', 'operator')
            ->where('holder_type', \App\Models\User::class)
            ->where('holder_id', $user->id)
            ->with(['order:id,order_number,coffeenet_id,service_id', 'order.service:id,name']);

        $paginator = $query->orderByDesc('id')->paginate(20);

        $rows = $paginator->through(fn (CommissionPayout $p) => [
            'id' => $p->id,
            'order_number' => $p->order?->order_number ?? '—',
            'service' => $p->order?->service?->name ?? '—',
            'coffeenet' => $p->snapshot['coffeenet']['name'] ?? '—',
            'amount' => (float) $p->amount,
            'date' => $p->created_at ? jdate($p->created_at)->format('Y/m/d — H:i') : '—',
        ]);

        return response()->json($rows);
    }

    /** لاگ تراکنش‌های کیف پول اپراتور (درخواست بازخوردی — AJAX + صفحه‌بندی + فیلتر نوع) */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrCreate([], ['balance' => 0]);

        $type = (string) $request->query('type');
        $query = Transaction::where('wallet_id', $wallet->id)->orderByDesc('id');

        if (in_array($type, ['credit', 'debit'], true)) {
            $query->where('type', $type);
        }

        $paginator = $query->paginate(20);

        $refLabels = [
            'reward' => 'پاداش معرفی',
            'withdrawal' => 'برداشت',
            'order' => 'سهم سفارش',
            'payment' => 'پرداخت',
            'salary' => 'حقوق',
            'wallet_charge' => 'شارژ کیف پول',
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
