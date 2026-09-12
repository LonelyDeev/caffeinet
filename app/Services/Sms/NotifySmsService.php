<?php

namespace App\Services\Sms;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Throwable;

/**
 * پیامک‌های رویدادیِ اطلاع‌رسانی (v25) — همه با توقف‌پذیر بودن از
 * تنظیمات (تب پیامک → «رویدادهای اطلاع‌رسانی پیامکی»):
 *
 *  • sms.notify.transfer_offline   → انتقال درخواست به کافی‌نت/اپراتورِ آفلاین
 *  • sms.notify.salary             → واریز حقوق/کمیسیون به کیف پول
 *  • sms.notify.unaccepted         → درخواست بی‌پذیرش + مدیر آفلاین
 *  • sms.notify.ticket_reply       → پاسخ پشتیبانی به مشتری (پیش‌فرض روشن — رفتار قبلی)
 *
 * قالب‌ها از sms_templates (کلیدهای notify.*) با fallback متنی؛
 * هر متد fail-safe است و جریان اصلی را هرگز نمی‌شکند.
 */
class NotifySmsService
{
    public function __construct(
        protected SmsManager $sms,
        protected SettingsService $settings,
    ) {}

    private function enabled(string $key): bool
    {
        try {
            return (bool) $this->settings->get($key, false);
        } catch (Throwable) {
            return false;
        }
    }

    /* ------------------------------------------------------------------ */
    /* ۱) انتقال درخواست به کافی‌نت/اپراتور آفلاین                          */
    /* ------------------------------------------------------------------ */

    /**
     * وقتی مدیر کل درخواستی را به کافی‌نتی/اپراتوری «انتقال» می‌دهد که
     * آنلاین نیست، پیامک می‌رود تا متوجه شود (فقط اگر تنظیم فعال باشد).
     */
    public function orderTransferredOffline(User $target, array $vars): void
    {
        if (! $this->enabled('sms.notify.transfer_offline')) {
            return;
        }

        if ($target->isOnline()) {
            return; // آنلاین است — زنگ پنلش خودش خبر می‌دهد
        }

        $mobile = $target->mobile;

        if (! $mobile) {
            return;
        }

        try {
            $this->sms->sendTemplate(
                $mobile,
                'notify.order_transferred',
                $vars,
                'سفارش '.$vars['order_number'].' از طریق مدیریت به شما ('.$vars['role_name'].') واگذار شد — کافی‌نت آنلاین',
            );
        } catch (Throwable) {
            // پیامک هرگز عملیات اصلی را نمی‌شکند
        }
    }

    /* ------------------------------------------------------------------ */
    /* ۲) واریز حقوق / کمیسیون به کیف پول                                   */
    /* ------------------------------------------------------------------ */

    /** واریز درآمد/کمیسیون (تسویه سفارش) — به ذی‌نفعِ آنلاین یا آفلاین */
    public function salaryDeposited(User $target, string $amountFa, string $context): void
    {
        if (! $this->enabled('sms.notify.salary')) {
            return;
        }

        $mobile = $target->mobile;

        if (! $mobile) {
            return;
        }

        try {
            $this->sms->sendTemplate(
                $mobile,
                'notify.salary_paid',
                ['amount' => $amountFa, 'context' => $context],
                'واریز حقوق: مبلغ '.$amountFa.' به کیف پول شما واریز شد ('.$context.') — کافی‌نت آنلاین',
            );
        } catch (Throwable) {
            // fail-safe
        }
    }

    /* ------------------------------------------------------------------ */
    /* ۳) درخواست بی‌پذیرش + مدیر آفلاین                                     */
    /* ------------------------------------------------------------------ */

    /**
     * وقتی درخواستی مدتی است پذیرفته نشده و مدیر آفلاین است، پیامک
     * یادآوری می‌رود (دستور orders:notify-unaccepted).
     */
    public function unacceptedRequest(User $admin, array $vars): void
    {
        if (! $this->enabled('sms.notify.unaccepted')) {
            return;
        }

        if ($admin->isOnline()) {
            return; // مدیر آنلاین است — زنگ پنل کافی است
        }

        $mobile = $admin->mobile;

        if (! $mobile) {
            return;
        }

        try {
            $this->sms->sendTemplate(
                $mobile,
                'notify.unaccepted_request',
                $vars,
                'درخواست '.$vars['order_number'].' مشتری '.$vars['customer'].' پس از '.$vars['minutes'].' دقیقه هنوز پذیرش نشده — بررسی کنید — کافی‌نت آنلاین',
            );
        } catch (Throwable) {
            // fail-safe
        }
    }

    /* ------------------------------------------------------------------ */
    /* ۴) پاسخ پشتیبانی به مشتری (گیت رفتار قبلی — پیش‌فرض روشن)            */
    /* ------------------------------------------------------------------ */

    public function ticketReplyEnabled(): bool
    {
        return $this->enabled('sms.notify.ticket_reply');
    }
}
