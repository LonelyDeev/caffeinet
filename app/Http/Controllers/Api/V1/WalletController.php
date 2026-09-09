<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TransactionResource;
use App\Services\Customer\PaymentGatewayService;
use App\Services\Finance\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * کیف پول مشتری — موجودی، گردش حساب و شارژ از درگاه.
 */
class WalletController extends Controller
{
    /** GET /api/v1/wallet */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $wallet = $user->wallet()->first();

        $paginator = $wallet
            ? $wallet->transactions()->orderByDesc('id')->paginate(15)
            : null;

        return response()->json([
            'balance' => app(WalletService::class)->balance($user),
            'transactions' => $paginator
                ? tap($paginator, fn ($p) => $p->getCollection()->transform(
                    fn ($t) => TransactionResource::make($t)->resolve()
                ))
                : null,
        ]);
    }

    /**
     * POST /api/v1/wallet/charge {amount}
     * آغاز شارژ کیف پول از درگاه — لینک امضاشده برمی‌گرداند.
     */
    public function charge(Request $request, PaymentGatewayService $payments): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10000', 'max:50000000'],
        ], [
            'amount.required' => 'مبلغ شارژ را وارد کنید.',
            'amount.numeric' => 'مبلغ باید عدد باشد.',
            'amount.min' => 'حداقل مبلغ شارژ ۱۰,۰۰۰ تومان است.',
            'amount.max' => 'حداکثر مبلغ شارژ ۵۰,۰۰۰,۰۰۰ تومان است.',
        ]);

        $amount = round((float) $data['amount'], 2);

        // فقط مشتریِ تکمیل‌شدهٔ پروفایل می‌تواند شارژ کند
        $user = $request->user();
        if (! $user->profile_completed) {
            throw ValidationException::withMessages([
                'profile' => ['ابتدا پروفایل خود را کامل کنید.'],
            ]);
        }

        $payment = $payments->startWalletCharge($user, $amount);

        return response()->json([
            'message' => 'در حال انتقال به درگاه پرداخت…',
            'payment_url' => $payments->paymentUrl($payment),
            'payment_path' => $payments->paymentPath($payment),
            'data' => [
                'payment_id' => $payment->id,
                'amount' => (float) $payment->amount,
            ],
        ]);
    }
}
