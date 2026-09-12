<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Services\Sms\NotifySmsService;
use Illuminate\Console\Command;
use Throwable;

/**
 * v25 — یادآوری «درخواست بی‌پذیرش».
 *
 * سفارش‌هایی که هنوز در وضعیت Broadcasting/Queued مانده‌اند و مدت
 * «sms.notify.unaccepted_minutes» (پیش‌فرض ۱۵ دقیقه) از ثبت‌شان گذشته
 * و کسی قبول نکرده:
 *   ۱) اعلان درون‌برنامه‌ای به همهٔ مدیران کل
 *   ۲) نوتیف دستگاه (FCM) به مدیرانی که آنلاین نیستند — چون اعلان
 *      از مسیر NotificationService می‌گذرد، خودکار انجام می‌شود
 *   ۳) پیامک به مدیرانِ آفلاین — فقط اگر sms.notify.unactivated فعال باشد
 *
 * هر سفارش فقط یک‌بار خبر می‌رود (orders.unaccepted_notified_at) تا
 * اسپم نشود.
 *
 * php artisan orders:notify-unaccepted  (هر ۵ دقیقه زمان‌بندی‌شده)
 */
class NotifyUnacceptedOrders extends Command
{
    protected $signature = 'orders:notify-unaccepted {--force : حتی اگر تنظیم پیامک خاموش است، اعلان/پوش انجام شود}';

    protected $description = 'یادآوری درخواست‌های بی‌پذیرش به مدیران (اعلان + پوش دستگاه + پیامک در صورت آفلاین بودن)';

    public function handle(SettingsService $settings, NotifySmsService $notifySms): int
    {
        $minutes = max(5, (int) $settings->get('sms.notify.unaccepted_minutes', 15));
        $smsEnabled = (bool) $settings->get('sms.notify.unaccepted', false);
        $forced = (bool) $this->option('force');

        // اگر نه پیامک فعال است و نه پوش فایربیس — و اجباری هم نیست، کاری نکن
        $pushProvider = (string) $settings->get('notification.push.provider', 'off');

        if (! $forced && ! $smsEnabled && $pushProvider !== 'firebase') {
            return self::SUCCESS;
        }

        $orders = Order::query()
            ->whereIn('status', [OrderStatus::Broadcasting->value, OrderStatus::Queued->value])
            ->whereNull('unaccepted_notified_at')
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        if ($orders->isEmpty()) {
            return self::SUCCESS;
        }

        $admins = User::query()
            ->role('super_admin')
            ->where('is_active', true)
            ->get();

        if ($admins->isEmpty()) {
            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($orders as $order) {
            try {
                // ۱+۲) اعلان درون‌برنامه‌ای + پوش دستگاه مدیران آفلاین (خودکار)
                app(\App\Services\Notifications\NotificationService::class)->notifyAdminsEvent(
                    'order.unaccepted_admin',
                    [
                        'order' => $order->order_number,
                        'minutes' => (string) $minutes,
                    ],
                    ['url' => '/admin/orders/'.$order->id.'/view', 'ref' => ['order_id' => $order->id, 'order_number' => $order->order_number]],
                );

                // ۳) پیامک به مدیران آفلاین (اگر تنظیم فعال باشد)
                if ($smsEnabled) {
                    $customerName = $order->customer?->full_name ?? '—';

                    foreach ($admins as $admin) {
                        $notifySms->unacceptedRequest($admin, [
                            'order_number' => $order->order_number,
                            'customer' => $customerName,
                            'minutes' => (string) $minutes,
                        ]);
                    }
                }

                // تیک «یادآوری ارسال‌شده» — فقط بعد از موفقیت اعلان
                $order->forceFill(['unaccepted_notified_at' => now()])->save();
                $notified++;
            } catch (Throwable $e) {
                $this->warn('خطا در اطلاع‌رسانی سفارش '.$order->order_number.': '.$e->getMessage());
            }
        }

        if ($notified > 0) {
            $this->info("یادآوری {$notified} درخواست بی‌پذیرش به مدیران ارسال شد (آستانه: {$minutes} دقیقه).");
        }

        return self::SUCCESS;
    }
}
