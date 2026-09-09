<?php

namespace App\Http\Controllers\Front\App;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Customer\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

/**
 * جریان پرداخت آنلاین (مسیر وب).
 *
 * اپ (وب/موبایل) با لینک امضاشده به /payment/start/{payment} می‌آید،
 * صفحه درگاه رندر می‌شود (در dev: درگاه تست local) و پاسخ به
 * /payment/callback برمی‌گردد؛ در انتها به صفحه سفارش یا کیف پول
 * (برای شارژ کیف) redirect می‌شود.
 */
class PaymentController extends Controller
{
    /** GET /payment/start/{payment}?signature=… */
    public function start(Request $request, Payment $payment, PaymentGatewayService $payments)
    {
        abort_unless($request->hasValidSignature(), 403, 'لینک پرداخت منقضی یا نامعتبر است.');

        // شارژ کیف پول — مقصد بازگشت صفحهٔ کیف است
        if ($payment->isForWallet()) {
            if ($payment->status === PaymentStatus::Success) {
                return redirect()->route('app.wallet', ['charged' => '1']);
            }

            if ($payment->status !== PaymentStatus::Pending) {
                return redirect()->route('app.wallet', ['payment' => 'failed']);
            }

            return Response::make($payments->renderGateway($payment), 200, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        // پرداخت سفارش
        if ($payment->status === PaymentStatus::Success) {
            return redirect()->route('app.orders.show', ['order' => $payment->order_id, 'paid' => '1']);
        }

        if ($payment->status !== PaymentStatus::Pending) {
            return redirect()->route('app.orders.show', ['order' => $payment->order_id, 'payment' => 'failed']);
        }

        $order = $payment->order;

        // فاز ۱۱ — سفارش قابل پرداخت: accepted (اپراتور متصل) یا legacy pending_payment
        $payable = ! $order->paid_at && in_array($order->status, [
            OrderStatus::PendingPayment,
            OrderStatus::Accepted,
        ], true);

        if (! $payable) {
            return redirect()->route('app.orders.show', ['order' => $order->id, 'paid' => '1']);
        }

        // رندر فرم درگاه (HTML کامل — شامل درگاه تست local یا فرم redirect زرین‌پال)
        return Response::make($payments->renderGateway($payment), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** POST|GET /payment/callback — بازگشت از درگاه */
    public function callback(Request $request, PaymentGatewayService $payments)
    {
        try {
            $payment = $payments->handleCallback($request);

            // مقصد بر اساس نوع پرداخت: شارژ کیف → کیف پول، سفارش → جزئیات سفارش
            return $payment->isForWallet()
                ? redirect()->route('app.wallet', ['charged' => '1'])
                : redirect()->route('app.orders.show', ['order' => $payment->order_id, 'paid' => '1']);
        } catch (ValidationException $e) {
            $transactionId = (string) en_digits((string) $request->input('transactionId', ''));

            $payment = Payment::query()
                ->where('ref_id', $transactionId)
                ->where('driver', '!=', 'wallet')
                ->orderByDesc('id')
                ->first();

            if ($payment && $payment->isForWallet()) {
                return redirect()->route('app.wallet', ['payment' => 'failed']);
            }

            return $payment
                ? redirect()->route('app.orders.show', ['order' => $payment->order_id, 'payment' => 'failed'])
                : redirect()->route('app.orders', ['payment' => 'failed']);
        }
    }
}
