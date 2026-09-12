<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Withdrawal;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\WalletException;
use App\Services\Finance\WalletService;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * برداشت از کیف پول کافی‌نت (فاز ۸) — همان جریان سازمان: بلوکهٔ فوری + تعیین‌تکلیف ادمین.
 */
class WithdrawalsController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
        protected NotificationService $notifications,
    ) {
    }

    public function index(Request $request, Coffeenet $coffeenet): View
    {
        return view('back.coffeenet.withdrawals.index', [
            'coffeenet' => $coffeenet,
            'balance' => $this->wallets->balance($coffeenet),
            'pending' => Withdrawal::query()
                ->whereHas('wallet', fn ($w) => $w->where('holder_type', Coffeenet::class)->where('holder_id', $coffeenet->id))
                ->where('status', 'pending')
                ->count(),
        ]);
    }

    /** لیست برداشت‌های کافی‌نت (AJAX + فیلتر وضعیت) */
    public function data(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $query = Withdrawal::query()
            ->whereHas('wallet', fn ($w) => $w->where('holder_type', Coffeenet::class)->where('holder_id', $coffeenet->id))
            ->with('reviewer:id,name,family');

        if ($status = (string) $request->query('status')) {
            if (in_array($status, ['pending', 'approved', 'rejected', 'paid'], true)) {
                $query->where('status', $status);
            }
        }

        $paginator = $query->latest('id')->paginate(20);

        $labels = [
            'pending' => ['در انتظار بررسی', 'amber'],
            'approved' => ['تأییدشده', 'sky'],
            'paid' => ['پرداخت‌شده', 'emerald'],
            'rejected' => ['ردشده', 'rose'],
        ];

        $rows = $paginator->through(fn (Withdrawal $w) => [
            'id' => $w->id,
            'amount' => (float) $w->amount,
            'status' => $w->status,
            'status_label' => $labels[$w->status][0] ?? $w->status,
            'status_color' => $labels[$w->status][1] ?? 'stone',
            'note' => $w->note,
            'reviewer' => $w->reviewer?->full_name,
            'requested_at' => $w->created_at ? jdate($w->created_at)->format('Y/m/d — H:i') : '—',
            'reviewed_at' => $w->reviewed_at ? jdate($w->reviewed_at)->format('Y/m/d — H:i') : null,
        ]);

        return response()->json($rows);
    }

    /**
     * ثبت درخواست برداشت (AJAX) — مبلغ بلافاصله بلوکه می‌شود.
     */
    public function store(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'amount.required' => 'مبلغ برداشت الزامی است.',
            'amount.numeric' => 'مبلغ باید عدد باشد.',
            'amount.min' => 'حداقل مبلغ برداشت ۱٬۰۰۰ تومان است.',
        ]);

        $amount = floor((float) $data['amount']);
        $balance = $this->wallets->balance($coffeenet);

        if ($amount > $balance) {
            return response()->json([
                'message' => 'موجودی کیف پول کافی نیست. (موجودی فعلی: '.fa_money($balance).')',
                'errors' => ['amount' => ['مبلغ بیشتر از موجودی است.']],
            ], 422);
        }

        try {
            $withdrawal = DB::transaction(function () use ($coffeenet, $amount, $data, $request) {
                // بلوکه مبلغ (بدهکار با ارجاع به برداشت — رکورد بعداً ساخته می‌شود)
                $this->wallets->debit(
                    $coffeenet,
                    $amount,
                    'withdrawal',
                    null,
                    'درخواست برداشت از کیف پول کافی‌نت',
                    ['pending' => true],
                );

                return Withdrawal::create([
                    'wallet_id' => $coffeenet->wallet()->firstOrCreate([], ['balance' => 0])->id,
                    'amount' => $amount,
                    'status' => 'pending',
                    'requested_by' => $request->user()->id,
                    'note' => $data['note'] ?? null,
                ]);
            });
        } catch (WalletException) {
            return response()->json(['message' => 'موجودی کیف پول کافی نیست.'], 422);
        }

        AuditLogger::log('withdrawal.requested', $withdrawal, null,
            ['amount' => $amount, 'note' => $data['note'] ?? null],
            "درخواست برداشت {$amount} تومان از کیف پول کافی‌نت «{$coffeenet->name}»");

        // اعلان به مدیران کل (فاز ۱۰) + پوش دستگاه در صورت آفلاین بودن (v25)
        $this->notifications->notifyAdminsEvent('withdrawal.requested_admin', [
            'name' => 'کافی‌نت «'.$coffeenet->name.'»',
            'amount' => fa_money($amount),
        ], ['url' => '/admin/withdrawals', 'ref' => ['withdrawal_id' => $withdrawal->id]]);

        return response()->json([
            'message' => 'درخواست برداشت ثبت شد و پس از بررسی مدیریت کل پرداخت می‌شود. مبلغ تا تعیین‌تکلیف از کیف پول بلوکه است.',
        ]);
    }
}
