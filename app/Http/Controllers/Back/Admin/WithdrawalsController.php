<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Withdrawal;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\WalletService;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WithdrawalsController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
        protected NotificationService $notifications,
    ) {
    }

    public function index(): View
    {
        return view('back.admin.withdrawals.index');
    }

    /** لیست برداشت‌ها (AJAX + فیلتر وضعیت + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $query = Withdrawal::query()
            ->with(['wallet.holder', 'requester:id,name,family,email', 'reviewer:id,name,family']);

        if ($status = (string) $request->query('status')) {
            if (in_array($status, ['pending', 'approved', 'rejected', 'paid'], true)) {
                $query->where('status', $status);
            }
        }

        $paginator = $query->latest('id')->paginate(25);

        $rows = $paginator->through(function (Withdrawal $w) {
            $holder = $w->wallet?->holder;

            return [
                'id' => $w->id,
                'holder' => $holder?->name ?? '—',
                'holder_type' => $holder instanceof Organization ? 'سازمان' : 'کافی‌نت',
                'requester' => $w->requester?->full_name ?? '—',
                'amount' => (float) $w->amount,
                'wallet_balance' => (float) $w->wallet?->balance ?? 0.0,
                'status' => $w->status,
                'note' => $w->note,
                'reviewer' => $w->reviewer?->full_name,
                'requested_at' => $w->created_at ? jdate($w->created_at)->format('Y/m/d H:i') : '—',
                'reviewed_at' => $w->reviewed_at ? jdate($w->reviewed_at)->format('Y/m/d H:i') : null,
            ];
        });

        return response()->json($rows);
    }

    /**
     * تعیین‌تکلیف درخواست برداشت (AJAX)
     * pay    → تأیید و پرداخت (مبلغ از قبل هنگام درخواست بلوکه شده)
     * reject → رد و بازگشت مبلغ به کیف پول
     */
    public function review(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:pay,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'این درخواست قبلاً تعیین‌تکلیف شده است.',
            ], 422);
        }

        $holder = $withdrawal->wallet?->holder;

        if (! $holder) {
            return response()->json(['message' => 'دارنده کیف پول یافت نشد.'], 422);
        }

        $paid = $data['action'] === 'pay';
        $old = ['status' => $withdrawal->status];

        DB::transaction(function () use ($withdrawal, $request, $data, $holder, $paid) {
            if ($paid) {
                $withdrawal->update([
                    'status' => 'paid',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'note' => $data['note'] ?? $withdrawal->note,
                ]);
            } else {
                // بازگشت مبلغ بلوکه‌شده به کیف پول
                $this->wallets->credit(
                    $holder,
                    (float) $withdrawal->amount,
                    'withdrawal',
                    $withdrawal->id,
                    'بازگشت مبلغ برداشت ردشده به کیف پول',
                    ['withdrawal_id' => $withdrawal->id, 'rejected' => true],
                );

                $withdrawal->update([
                    'status' => 'rejected',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'note' => $data['note'] ?? $withdrawal->note,
                ]);
            }
        });

        AuditLogger::log('withdrawal.'.($paid ? 'paid' : 'rejected'), $withdrawal->refresh(), $old,
            ['status' => $paid ? 'paid' : 'rejected', 'note' => $data['note'] ?? null, 'amount' => (float) $withdrawal->amount],
            ($paid ? 'پرداخت برداشت' : 'رد برداشت و بازگشت به کیف پول').": {$withdrawal->amount} تومان — ".($holder->name ?? ''));

        // اعلان به درخواست‌کننده (فاز ۱۰)
        $this->notifications->tryNotify(
            $withdrawal->requester,
            'withdrawal',
            $paid ? 'برداشت پرداخت شد' : 'برداشت رد شد',
            $paid
                ? 'درخواست برداشت '.fa_money((float) $withdrawal->amount).' شما پرداخت شد.'
                : 'درخواست برداشت '.fa_money((float) $withdrawal->amount).' شما رد شد و مبلغ به کیف پول بازگشت.',
            $holder instanceof Organization
                ? ['url' => '/organization/withdrawals', 'ref' => ['withdrawal_id' => $withdrawal->id]]
                : (isset($holder->id) ? ['url' => '/coffeenet/'.$holder->id.'/withdrawals', 'ref' => ['withdrawal_id' => $withdrawal->id]] : []),
        );

        return response()->json([
            'message' => $paid
                ? 'برداشت «'.$withdrawal->amount.'» تومان پرداخت‌شده علامت‌گذاری شد.'
                : 'برداشت رد شد و مبلغ به کیف پول بازگشت.',
            'status' => $paid ? 'paid' : 'rejected',
        ]);
    }
}
