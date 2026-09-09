<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

/**
 * تنظیمات پیش‌فرض سیستم — مطابق تصمیمات مالک:
 *   پخش: همه کافی‌نت‌ها بدون فیلتر (قابل تغییر از پنل)
 *   بعد از ۶۰ ثانیه: صف تعیین‌تکلیف دستی
 *   پیامک: فراز اس‌ام‌اس (در dev روی log)
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // عمومی
            ['group' => 'general', 'key' => 'general.app_name', 'value' => 'کافی‌نت آنلاین', 'cast' => 'string', 'label' => 'نام سیستم'],

            // پیامک
            ['group' => 'sms', 'key' => 'sms.provider', 'value' => 'log', 'cast' => 'string', 'label' => 'پرووایدر پیامک (log | kavenegar | fraasms)'],
            ['group' => 'sms', 'key' => 'sms.fraasms.api_key', 'value' => '', 'cast' => 'string', 'label' => 'کلید API فراز اس‌ام‌اس (هدر Api-Key)', 'is_sensitive' => true],
            ['group' => 'sms', 'key' => 'sms.fraasms.sender', 'value' => '', 'cast' => 'string', 'label' => 'شماره خط فرستنده فراز (line_number)'],
            ['group' => 'sms', 'key' => 'sms.fraasms.endpoint', 'value' => 'https://api.iranpayamak.com/ws/v1/sms/pattern', 'cast' => 'string', 'label' => 'آدرس API پترن فراز'],
            ['group' => 'sms', 'key' => 'sms.kavenegar.api_key', 'value' => '', 'cast' => 'string', 'label' => 'کلید API کاوه‌نگار', 'is_sensitive' => true],
            ['group' => 'sms', 'key' => 'sms.kavenegar.sender', 'value' => '', 'cast' => 'string', 'label' => 'شماره خط فرستنده کاوه‌نگار'],
            ['group' => 'sms', 'key' => 'sms.kavenegar.endpoint', 'value' => 'https://api.kavenegar.com', 'cast' => 'string', 'label' => 'آدرس API کاوه‌نگار'],

            // سفارش‌ها — تصمیم مالک
            ['group' => 'orders', 'key' => 'orders.broadcast_scope', 'value' => 'all', 'cast' => 'string', 'label' => 'محدوده پخش (all | province | city)'],
            ['group' => 'orders', 'key' => 'orders.broadcast_timeout', 'value' => '60', 'cast' => 'integer', 'label' => 'مهلت پخش سفارش (ثانیه)'],
            ['group' => 'orders', 'key' => 'orders.assign_after_timeout', 'value' => 'manual', 'cast' => 'string', 'label' => 'تعیین‌تکلیف بعد از مهلت (manual | rebroadcast)'],

            // کارکنان — سیاست افزودن کارمند توسط مدیر کافی‌نت (auto | approval)
            ['group' => 'staff', 'key' => 'staff.hiring.mode', 'value' => 'auto', 'cast' => 'string', 'label' => 'افزودن کارمند توسط مدیر کافی‌نت (auto = تایید خودکار | approval = نیازمند تایید مدیر کل)'],

            // Realtime — پوشر (فاز ۱۳) در کنار پولینگ
            ['group' => 'realtime', 'key' => 'realtime.pusher.enabled', 'value' => '0', 'cast' => 'boolean', 'label' => 'فعال‌سازی Realtime پوشر'],
            ['group' => 'realtime', 'key' => 'realtime.pusher.app_id', 'value' => '', 'cast' => 'string', 'label' => 'Pusher App ID', 'is_sensitive' => true],
            ['group' => 'realtime', 'key' => 'realtime.pusher.app_key', 'value' => '', 'cast' => 'string', 'label' => 'Pusher App Key (عمومی)'],
            ['group' => 'realtime', 'key' => 'realtime.pusher.app_secret', 'value' => '', 'cast' => 'string', 'label' => 'Pusher App Secret', 'is_sensitive' => true],
            ['group' => 'realtime', 'key' => 'realtime.pusher.cluster', 'value' => 'mt1', 'cast' => 'string', 'label' => 'Pusher Cluster (mt1 | eu | ap2 | us2 …)'],

            // پرداخت (فاز ۵)
            ['group' => 'payment', 'key' => 'payment.driver', 'value' => 'local', 'cast' => 'string', 'label' => 'درایور پرداخت (local | zarinpal | zibal)'],
            ['group' => 'payment', 'key' => 'payment.zarinpal.merchant_id', 'value' => '', 'cast' => 'string', 'label' => 'شناسهٔ پذیرندهٔ زرین‌پال', 'is_sensitive' => true],
            ['group' => 'payment', 'key' => 'payment.zarinpal.sandbox', 'value' => '1', 'cast' => 'boolean', 'label' => 'زرین‌پال حالت آزمایشی (sandbox)'],
            ['group' => 'payment', 'key' => 'payment.zibal.merchant_id', 'value' => '', 'cast' => 'string', 'label' => 'شناسهٔ پذیرندهٔ زیبال', 'is_sensitive' => true],

            // نگهداشت و پاکسازی دوره‌ای (فاز ۱۱)
            ['group' => 'system', 'key' => 'system.cleanup.notifications_read', 'value' => '30', 'cast' => 'integer', 'label' => 'نگهداشت اعلان خوانده‌شده (روز)'],
            ['group' => 'system', 'key' => 'system.cleanup.notifications_unread', 'value' => '90', 'cast' => 'integer', 'label' => 'نگهداشت اعلان خوانده‌نشده (روز)'],
            ['group' => 'system', 'key' => 'system.cleanup.sms_logs', 'value' => '90', 'cast' => 'integer', 'label' => 'نگهداشت لاگ پیامک (روز)'],
            ['group' => 'system', 'key' => 'system.cleanup.audit_logs', 'value' => '365', 'cast' => 'integer', 'label' => 'نگهداشت لاگ فعالیت (روز)'],
            ['group' => 'system', 'key' => 'system.cleanup.last', 'value' => null, 'cast' => 'json', 'label' => 'آخرین گزارش پاکسازی'],
        ];

        foreach ($rows as $row) {
            Setting::firstOrCreate(
                ['key' => $row['key']],
                [
                    'group' => $row['group'],
                    'value' => $row['value'],
                    'cast' => $row['cast'],
                    'label' => $row['label'],
                    'is_sensitive' => $row['is_sensitive'] ?? false,
                ],
            );
        }
    }
}
