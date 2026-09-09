<?php

namespace App\Services\Sms;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Throwable;

/**
 * پیامک حرکات مشتری (درخواست بازخوردی ۶-۶) — تک نقطهٔ truth برای ارسال
 * پیامک‌های رویدادمحور با متن پیش‌فرض امن.
 *
 * هر متد: قالب از مرکز پیامک (pattern + فعال/غیرفعال)، در صورت غیرفعالی
 * متن پیش‌فرض ارسال می‌شود؛ همهٔ فراخوانی‌ها fail-safe هستند.
 */
class CustomerSmsService
{
    public function __construct(
        protected SmsManager $sms,
        protected SettingsService $settings,
    ) {}

    protected function appName(): string
    {
        return (string) $this->settings->get('general.app_name', 'کافی‌نت آنلاین');
    }

    protected function send(?string $mobile, string $key, array $vars, ?string $fallback): void
    {
        if (! $mobile) {
            return;
        }

        try {
            $this->sms->sendTemplate($mobile, $key, $vars, $fallback);
        } catch (Throwable) {
            // پیامک هرگز جریان اصلی را نمی‌شکند
        }
    }

    protected function orderMobile(Order $order): ?string
    {
        try {
            return $order->customer()->value('mobile');
        } catch (Throwable) {
            return null;
        }
    }

    /** پرداخت موفق سفارش */
    public function orderPaid(Order $order, ?string $trace = null): void
    {
        $this->send(
            $this->orderMobile($order),
            'order.paid',
            [
                'app_name' => $this->appName(),
                'order_number' => $order->order_number,
                'trace' => $trace ?? ('ORD-'.$order->id),
            ],
            'پرداخت سفارش '.$order->order_number.' با موفقیت انجام شد. '.$this->appName()
        );
    }

    /** نیاز به اطلاعات تکمیلی */
    public function orderNeedsInfo(Order $order): void
    {
        $this->send(
            $this->orderMobile($order),
            'order.needs_info',
            [
                'order_number' => $order->order_number,
                'app_name' => $this->appName(),
            ],
            'برای ادامهٔ سفارش '.$order->order_number.' به اطلاعات تکمیلی نیاز است؛ از گفتگوی سفارش در اپ آن را ارسال کنید. '.$this->appName()
        );
    }

    /** شروع انجام سفارش توسط اپراتور */
    public function orderInProgress(Order $order, ?string $operator = null): void
    {
        $operator ??= $order->operator?->full_name ?? 'اپراتور';

        $this->send(
            $this->orderMobile($order),
            'order.in_progress',
            [
                'order_number' => $order->order_number,
                'operator' => $operator,
                'app_name' => $this->appName(),
            ],
            'کار روی سفارش '.$order->order_number.' آغاز شد. '.$this->appName()
        );
    }

    /** تحویل سفارش */
    public function orderDelivered(Order $order): void
    {
        $this->send(
            $this->orderMobile($order),
            'order.delivered',
            ['order_number' => $order->order_number],
            'سفارش '.$order->order_number.' شما آماده و تحویل شد؛ برای مشاهدهٔ نتیجه به اپ مراجعه کنید. '.$this->appName()
        );
    }

    /** تکمیل و تسویهٔ نهایی */
    public function orderCompleted(Order $order): void
    {
        $this->send(
            $this->orderMobile($order),
            'order.completed',
            [
                'order_number' => $order->order_number,
                'app_name' => $this->appName(),
            ],
            'سفارش '.$order->order_number.' تکمیل و تسویه شد. سپاس از اعتماد شما. '.$this->appName()
        );
    }

    /** لغو سفارش (مشتری یا ادمین) */
    public function orderCancelled(Order $order, ?string $reason = null): void
    {
        $reason = $reason ?: 'درخواست شما';

        $this->send(
            $this->orderMobile($order),
            'order.cancelled',
            [
                'order_number' => $order->order_number,
                'reason' => $reason,
            ],
            'سفارش '.$order->order_number.' شما لغو شد: '.$reason.' — وجه پرداختی طبق فرایند تسویه برگشت داده می‌شود. '.$this->appName()
        );
    }

    /** بازگشت وجه به کیف پول مشتری */
    public function orderRefunded(Order $order, float $amount): void
    {
        $this->send(
            $this->orderMobile($order),
            'order.refunded',
            [
                'order_number' => $order->order_number,
                'amount' => fa_digits(number_format($amount)),
                'app_name' => $this->appName(),
            ],
            'مبلغ سفارش '.$order->order_number.' به کیف پول شما برگشت داده شد. '.$this->appName()
        );
    }

    /** واریز به کیف پول مشتری */
    public function walletCharged(User $user, Transaction $tx): void
    {
        $wallet = $tx->wallet;

        $this->send(
            $user->mobile,
            'wallet.charged',
            [
                'amount' => fa_digits(number_format((float) $tx->amount)),
                'balance' => fa_digits(number_format((float) $wallet?->balance)),
                'app_name' => $this->appName(),
            ],
            'مبلغ '.fa_digits(number_format((float) $tx->amount)).' تومان به کیف پول شما واریز شد. '.$this->appName()
        );
    }

    /** پاسخ پشتیبانی به تیکت مشتری */
    public function ticketReplied(Ticket $ticket): void
    {
        $mobile = $ticket->user?->mobile;

        $this->send(
            $mobile,
            'ticket.replied',
            [
                'ticket_number' => $ticket->ticket_number,
                'app_name' => $this->appName(),
            ],
            'به تیکت '.$ticket->ticket_number.' شما پاسخ داده شد؛ برای مشاهده به اپ مراجعه کنید. '.$this->appName()
        );
    }
}
