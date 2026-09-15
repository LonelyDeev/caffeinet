<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

/**
 * تنظیمات پیش‌فرض سیستم — مطابق تصمیمات مالک:
 *   پخش: همه کافی‌نت‌ها بدون فیلتر (قابل تغییر از پنل)
 *   بعد از ۶۰ ثانیه: صف تعیین‌تکلیف دستی
 *   پیامک: کاوه‌نگار / فراز / آی‌پی‌پنل / ملی‌پیامک / ایده‌پردازان (در dev روی log)
 *   درگاه: زرین‌پال / زیبال / بانک ملت / بانک ملی / سپهر (در dev روی local)
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // عمومی
            ['group' => 'general', 'key' => 'general.app_name', 'value' => 'کافی‌نت آنلاین', 'cast' => 'string', 'label' => 'نام سیستم'],
            ['group' => 'general', 'key' => 'general.timezone', 'value' => 'UTC', 'cast' => 'string', 'label' => 'منطقهٔ زمانی سامانه (IANA مثل Asia/Tehran)'],
            // v39 — بازهٔ سنین مجاز تاریخ تولد (لیست کشویی سال تولد مشتری + اعتبارسنجی سرور)
            ['group' => 'general', 'key' => 'general.birth_min_age', 'value' => '10', 'cast' => 'integer', 'label' => 'حداقل سن مشتریان (سال)'],
            ['group' => 'general', 'key' => 'general.birth_max_age', 'value' => '100', 'cast' => 'integer', 'label' => 'حداکثر سن مشتریان (سال)'],

            // پیامک
            ['group' => 'sms', 'key' => 'sms.provider', 'value' => 'log', 'cast' => 'string', 'label' => 'پرووایدر پیامک (log | kavenegar | fraasms)'],
            ['group' => 'sms', 'key' => 'sms.fraasms.api_key', 'value' => '', 'cast' => 'string', 'label' => 'کلید API فراز اس‌ام‌اس (هدر Api-Key)', 'is_sensitive' => true],
            ['group' => 'sms', 'key' => 'sms.fraasms.sender', 'value' => '', 'cast' => 'string', 'label' => 'شماره خط فرستنده فراز (line_number)'],
            ['group' => 'sms', 'key' => 'sms.fraasms.endpoint', 'value' => 'https://api.iranpayamak.com/ws/v1/sms/pattern', 'cast' => 'string', 'label' => 'آدرس API پترن فراز'],
            ['group' => 'sms', 'key' => 'sms.kavenegar.api_key', 'value' => '', 'cast' => 'string', 'label' => 'کلید API کاوه‌نگار', 'is_sensitive' => true],
            ['group' => 'sms', 'key' => 'sms.kavenegar.sender', 'value' => '', 'cast' => 'string', 'label' => 'شماره خط فرستنده کاوه‌نگار'],
            ['group' => 'sms', 'key' => 'sms.kavenegar.endpoint', 'value' => 'https://api.kavenegar.com', 'cast' => 'string', 'label' => 'آدرس API کاوه‌نگار'],

            // پیامک — آی‌پی‌پنل (پترن‌محور)
            ['group' => 'sms', 'key' => 'sms.ippanel.username', 'value' => '', 'cast' => 'string', 'label' => 'نام کاربری پنل آی‌پی‌پنل'],
            ['group' => 'sms', 'key' => 'sms.ippanel.password', 'value' => '', 'cast' => 'string', 'label' => 'رمز عبور پنل آی‌پی‌پنل', 'is_sensitive' => true],
            ['group' => 'sms', 'key' => 'sms.ippanel.from', 'value' => '', 'cast' => 'string', 'label' => 'شماره خط فرستنده آی‌پی‌پنل'],
            ['group' => 'sms', 'key' => 'sms.ippanel.endpoint', 'value' => 'https://ippanel.com/patterns/pattern', 'cast' => 'string', 'label' => 'آدرس ارسال پترن آی‌پی‌پنل'],

            // پیامک — ملی‌پیامک (متن ثابت / bodyId)
            ['group' => 'sms', 'key' => 'sms.melipayamak.username', 'value' => '', 'cast' => 'string', 'label' => 'نام کاربری پنل ملی‌پیامک'],
            ['group' => 'sms', 'key' => 'sms.melipayamak.password', 'value' => '', 'cast' => 'string', 'label' => 'رمز عبور پنل ملی‌پیامک', 'is_sensitive' => true],
            ['group' => 'sms', 'key' => 'sms.melipayamak.from', 'value' => '', 'cast' => 'string', 'label' => 'شماره خط فرستنده ملی‌پیامک'],
            ['group' => 'sms', 'key' => 'sms.melipayamak.endpoint', 'value' => 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', 'cast' => 'string', 'label' => 'آدرس REST سرویس متن ثابت ملی‌پیامک'],

            // پیامک — ایده‌پردازان (قالب سریع)
            ['group' => 'sms', 'key' => 'sms.idehpardazan.api_key', 'value' => '', 'cast' => 'string', 'label' => 'UserApiKey پنل ایده‌پردازان', 'is_sensitive' => true],
            ['group' => 'sms', 'key' => 'sms.idehpardazan.secret_key', 'value' => '', 'cast' => 'string', 'label' => 'SecretKey پنل ایده‌پردازان', 'is_sensitive' => true],
            ['group' => 'sms', 'key' => 'sms.idehpardazan.endpoint', 'value' => 'https://RestfulSms.com/api/UltraFastSend/direct', 'cast' => 'string', 'label' => 'آدرس ارسال قالب سریع ایده‌پردازان'],

            // ساعت کاری (فاز ۱۵)
            ['group' => 'workhours', 'key' => 'workhours.enabled', 'value' => '0', 'cast' => 'boolean', 'label' => 'محدودیت ثبت سفارش به ساعت کاری'],
            ['group' => 'workhours', 'key' => 'workhours.start', 'value' => '08:00', 'cast' => 'string', 'label' => 'شروع ساعت کاری (HH:MM)'],
            ['group' => 'workhours', 'key' => 'workhours.end', 'value' => '22:00', 'cast' => 'string', 'label' => 'پایان ساعت کاری (HH:MM)'],
            ['group' => 'workhours', 'key' => 'workhours.days', 'value' => '6,0,1,2,3,4', 'cast' => 'string', 'label' => 'روزهای کاری (CSV — 6=شنبه، 0=یکشنبه، …، 5=جمعه)'],
            ['group' => 'workhours', 'key' => 'workhours.message', 'value' => '', 'cast' => 'string', 'label' => 'پیام سفارشی مودال خارج از ساعت کاری'],

            // سفارش‌ها — تصمیم مالک
            ['group' => 'orders', 'key' => 'orders.broadcast_scope', 'value' => 'all', 'cast' => 'string', 'label' => 'محدوده پخش (all | province | city)'],
            ['group' => 'orders', 'key' => 'orders.broadcast_timeout', 'value' => '60', 'cast' => 'integer', 'label' => 'مهلت پخش سفارش (ثانیه)'],
            ['group' => 'orders', 'key' => 'orders.assign_after_timeout', 'value' => 'manual', 'cast' => 'string', 'label' => 'تعیین‌تکلیف بعد از مهلت (manual | rebroadcast)'],
            // v39 — صفحهٔ انتظار مشتری: ثانیه‌شمار + متن‌های قابل ویرایش
            ['group' => 'orders', 'key' => 'orders.broadcast_timer_enabled', 'value' => '1', 'cast' => 'boolean', 'label' => 'نمایش ثانیه‌شمار مهلت پخش در اپ مشتری'],
            ['group' => 'orders', 'key' => 'orders.broadcast_text', 'value' => 'درخواستتان بین اپراتورها و کافی‌نت‌های فعال پخش شده است؛ اولین اپراتوری که آن را بپذیرد، به شما وصل می‌شود و گفتگو آغاز می‌گردد.', 'cast' => 'string', 'label' => 'متن کارت پخش سفارش در اپ مشتری'],
            ['group' => 'orders', 'key' => 'orders.queued_text', 'value' => 'سفارش شما با موفقیت ثبت شد. همکاران ما در اولین فرصت آن را بررسی و به یکی از کافی‌نت‌ها تخصیص می‌دهند و نتیجه را از طریق پیامک و تماس به شما اطلاع می‌دهند.', 'cast' => 'string', 'label' => 'متن کارت صف تعیین‌تکلیف در اپ مشتری'],

            // نظرسنجی و پخش هوشمند (v33)
            ['group' => 'ratings', 'key' => 'ratings.survey_enabled', 'value' => '1', 'cast' => 'boolean', 'label' => 'نظرسنجی پس از تحویل فعال باشد'],
            ['group' => 'ratings', 'key' => 'ratings.routing_enabled', 'value' => '0', 'cast' => 'boolean', 'label' => 'پخش سفارش‌ها بر اساس امتیاز کافی‌نت‌ها'],
            ['group' => 'ratings', 'key' => 'ratings.routing_mode', 'value' => 'hybrid', 'cast' => 'string', 'label' => 'سیاست پخش هوشمند (filter | priority | hybrid)'],
            ['group' => 'ratings', 'key' => 'ratings.routing_min_rating', 'value' => '3', 'cast' => 'integer', 'label' => 'حداقل میانگین امتیاز کافی‌نت برای دریافت سفارش (۱..۵)'],
            ['group' => 'ratings', 'key' => 'ratings.routing_min_votes', 'value' => '3', 'cast' => 'integer', 'label' => 'حداقل تعداد نظرات برای اعتبار امتیاز کافی‌نت'],
            ['group' => 'ratings', 'key' => 'ratings.routing_unrated_policy', 'value' => 'include', 'cast' => 'string', 'label' => 'کافی‌نت بدون امتیاز معتبر (include | exclude)'],
            ['group' => 'ratings', 'key' => 'ratings.notify_low', 'value' => '1', 'cast' => 'boolean', 'label' => 'اعلان امتیاز پایین به مدیران'],
            ['group' => 'ratings', 'key' => 'ratings.notify_low_threshold', 'value' => '2', 'cast' => 'integer', 'label' => 'آستانهٔ اعلان امتیاز پایین (۱..۴)'],

            // کارکنان — سیاست افزودن کارمند توسط مدیر کافی‌نت (auto | approval)
            ['group' => 'staff', 'key' => 'staff.hiring.mode', 'value' => 'auto', 'cast' => 'string', 'label' => 'افزودن کارمند توسط مدیر کافی‌نت (auto = تایید خودکار | approval = نیازمند تایید مدیر کل)'],

            // Realtime — پوشر (فاز ۱۳) در کنار پولینگ
            ['group' => 'realtime', 'key' => 'realtime.pusher.enabled', 'value' => '0', 'cast' => 'boolean', 'label' => 'فعال‌سازی Realtime پوشر'],
            ['group' => 'realtime', 'key' => 'realtime.pusher.app_id', 'value' => '', 'cast' => 'string', 'label' => 'Pusher App ID', 'is_sensitive' => true],
            ['group' => 'realtime', 'key' => 'realtime.pusher.app_key', 'value' => '', 'cast' => 'string', 'label' => 'Pusher App Key (عمومی)'],
            ['group' => 'realtime', 'key' => 'realtime.pusher.app_secret', 'value' => '', 'cast' => 'string', 'label' => 'Pusher App Secret', 'is_sensitive' => true],
            ['group' => 'realtime', 'key' => 'realtime.pusher.cluster', 'value' => 'mt1', 'cast' => 'string', 'label' => 'Pusher Cluster (mt1 | eu | ap2 | us2 …)'],

            // اعلان‌ها — صدا (v25؛ فقط پنل‌ها، اپ مشتری صدا ندارد)
            ['group' => 'notifications', 'key' => 'notification.sound.enabled', 'value' => '1', 'cast' => 'boolean', 'label' => 'صدای اعلان جدید در پنل‌ها'],
            ['group' => 'notifications', 'key' => 'notification.sound.use_default', 'value' => '1', 'cast' => 'boolean', 'label' => 'استفاده از صدای پیش‌فرض سامانه'],
            ['group' => 'notifications', 'key' => 'notification.sound.file', 'value' => '', 'cast' => 'string', 'label' => 'مسیر صدای سفارشی (دیسک public — sounds/)'],

            // اعلان‌ها — نوتیف دستگاه (Web Push)
            ['group' => 'notifications', 'key' => 'notification.push.provider', 'value' => 'off', 'cast' => 'string', 'label' => 'سرویس نوتیف دستگاه (off | default | pusher | firebase)'],
            // v38 — وضعیت آنلاین/آفلاین کاربران برای ارسال نوتیف سیستمی
            ['group' => 'notifications', 'key' => 'notification.push.user_status', 'value' => 'offline', 'cast' => 'string', 'label' => 'وضعیت کاربران برای ارسال نوتیف سیستمی (offline | online | auto)'],
            // v29 → v35 — آستانهٔ آفلاین (کف ۹۰ ثانیه؛ خاموش = حالت کوتاه)
            ['group' => 'notifications', 'key' => 'notification.push.offline_enabled', 'value' => '1', 'cast' => 'boolean', 'label' => 'آستانهٔ آفلاین فعال باشد؟ (خاموش = حالت کوتاه ۴۵ ثانیه)'],
            ['group' => 'notifications', 'key' => 'notification.push.offline_seconds', 'value' => '45', 'cast' => 'integer', 'label' => 'پس از چند ثانیه بی‌فعالیتی، کاربر «آفلاین» فرض شود (حداقل ۴۵)'],
            ['group' => 'notifications', 'key' => 'notification.push.firebase.project_id', 'value' => '', 'cast' => 'string', 'label' => 'Firebase Project ID'],
            ['group' => 'notifications', 'key' => 'notification.push.firebase.sender_id', 'value' => '', 'cast' => 'string', 'label' => 'Firebase Messaging Sender ID (عمومی)'],
            ['group' => 'notifications', 'key' => 'notification.push.firebase.api_key', 'value' => '', 'cast' => 'string', 'label' => 'Firebase Web API Key (عمومی)'],
            ['group' => 'notifications', 'key' => 'notification.push.firebase.app_id', 'value' => '', 'cast' => 'string', 'label' => 'Firebase Web App ID (عمومی)'],
            ['group' => 'notifications', 'key' => 'notification.push.firebase.credentials', 'value' => '', 'cast' => 'string', 'label' => 'مسیر Service Account JSON (دیسک private)', 'is_sensitive' => true],
            // v26 — وب‌پوش داخلی (پیش‌فرض) + پوشر Beams
            ['group' => 'notifications', 'key' => 'notification.push.webpush.public_key', 'value' => '', 'cast' => 'string', 'label' => 'کلید عمومی VAPID وب‌پوش داخلی'],
            ['group' => 'notifications', 'key' => 'notification.push.webpush.private_key', 'value' => '', 'cast' => 'string', 'label' => 'کلید خصوصی VAPID وب‌پوش داخلی', 'is_sensitive' => true],
            ['group' => 'notifications', 'key' => 'notification.push.webpush.subject', 'value' => '', 'cast' => 'string', 'label' => 'ایمیل تماس VAPID (خالی = ایمیل سامانه)'],
            ['group' => 'notifications', 'key' => 'notification.push.beams.instance_id', 'value' => '', 'cast' => 'string', 'label' => 'Pusher Beams Instance ID'],
            ['group' => 'notifications', 'key' => 'notification.push.beams.primary_key', 'value' => '', 'cast' => 'string', 'label' => 'Pusher Beams Primary Key', 'is_sensitive' => true],

            // پیامک — رویدادهای اطلاع‌رسانی (v25)
            ['group' => 'sms', 'key' => 'sms.notify.ticket_reply', 'value' => '1', 'cast' => 'boolean', 'label' => 'پیامک پاسخ پشتیبانی به مشتری'],
            ['group' => 'sms', 'key' => 'sms.notify.transfer_offline', 'value' => '0', 'cast' => 'boolean', 'label' => 'پیامک انتقال درخواست به کافی‌نت/اپراتور آفلاین'],
            ['group' => 'sms', 'key' => 'sms.notify.salary', 'value' => '0', 'cast' => 'boolean', 'label' => 'پیامک واریز حقوق/کمیسیون به کیف پول'],
            ['group' => 'sms', 'key' => 'sms.notify.unaccepted', 'value' => '0', 'cast' => 'boolean', 'label' => 'پیامک درخواست بی‌پذیرش به مدیر آفلاین'],
            ['group' => 'sms', 'key' => 'sms.notify.unaccepted_minutes', 'value' => '15', 'cast' => 'integer', 'label' => 'اگر درخواست بعد از این تعداد دقیقه پذیرفته نشود، مدیران مطلع شوند'],

            // پرداخت (فاز ۵)
            ['group' => 'payment', 'key' => 'payment.driver', 'value' => 'local', 'cast' => 'string', 'label' => 'درایور پرداخت (local | zarinpal | zibal | behpardakht | sep | sepehr)'],
            ['group' => 'payment', 'key' => 'payment.zarinpal.merchant_id', 'value' => '', 'cast' => 'string', 'label' => 'شناسهٔ پذیرندهٔ زرین‌پال', 'is_sensitive' => true],
            ['group' => 'payment', 'key' => 'payment.zarinpal.sandbox', 'value' => '1', 'cast' => 'boolean', 'label' => 'زرین‌پال حالت آزمایشی (sandbox)'],
            ['group' => 'payment', 'key' => 'payment.zibal.merchant_id', 'value' => '', 'cast' => 'string', 'label' => 'شناسهٔ پذیرندهٔ زیبال', 'is_sensitive' => true],

            // پرداخت — بانک ملت (به‌پرداخت ملت)
            ['group' => 'payment', 'key' => 'payment.behpardakht.terminal_id', 'value' => '', 'cast' => 'string', 'label' => 'شماره ترمینال به‌پرداخت ملت (بانک ملت)'],
            ['group' => 'payment', 'key' => 'payment.behpardakht.username', 'value' => '', 'cast' => 'string', 'label' => 'نام کاربری به‌پرداخت ملت (بانک ملت)'],
            ['group' => 'payment', 'key' => 'payment.behpardakht.password', 'value' => '', 'cast' => 'string', 'label' => 'رمز عبور به‌پرداخت ملت (بانک ملت)', 'is_sensitive' => true],

            // پرداخت — بانک ملی (درگاه سپ SEP)
            ['group' => 'payment', 'key' => 'payment.sep.terminal_id', 'value' => '', 'cast' => 'string', 'label' => 'شماره ترمینال درگاه سپ بانک ملی'],

            // پرداخت — بانک صادرات (درگاه سپهر)
            ['group' => 'payment', 'key' => 'payment.sepehr.terminal_id', 'value' => '', 'cast' => 'string', 'label' => 'شماره ترمینال درگاه سپهر (بانک صادرات)'],

            // نگهداشت و پاکسازی دوره‌ای (فاز ۱۱)
            ['group' => 'system', 'key' => 'system.cleanup.notifications_read', 'value' => '30', 'cast' => 'integer', 'label' => 'نگهداشت اعلان خوانده‌شده (روز)'],
            ['group' => 'system', 'key' => 'system.cleanup.notifications_unread', 'value' => '90', 'cast' => 'integer', 'label' => 'نگهداشت اعلان خوانده‌نشده (روز)'],
            ['group' => 'system', 'key' => 'system.cleanup.sms_logs', 'value' => '90', 'cast' => 'integer', 'label' => 'نگهداشت لاگ پیامک (روز)'],
            ['group' => 'system', 'key' => 'system.cleanup.audit_logs', 'value' => '365', 'cast' => 'integer', 'label' => 'نگهداشت لاگ فعالیت (روز)'],
            ['group' => 'system', 'key' => 'system.cleanup.last', 'value' => null, 'cast' => 'json', 'label' => 'آخرین گزارش پاکسازی'],
            ['group' => 'system', 'key' => 'system.cron.last', 'value' => null, 'cast' => 'string', 'label' => 'آخرین ضربان کرون (schedule:run)'],

            // فینوتک — استعلام کد ملی/موبایل/کارت (v40)
            ['group' => 'finnotech', 'key' => 'finnotech.enabled', 'value' => '0', 'cast' => 'boolean', 'label' => 'فعال‌سازی سرویس استعلام فینوتک'],
            ['group' => 'finnotech', 'key' => 'finnotech.mode', 'value' => 'production', 'cast' => 'string', 'label' => 'محیط سرویس فینوتک (production/sandbox)'],
            ['group' => 'finnotech', 'key' => 'finnotech.client_id', 'value' => '', 'cast' => 'string', 'label' => 'شناسه برنامهٔ فینوتک (clientId)'],
            ['group' => 'finnotech', 'key' => 'finnotech.client_secret', 'value' => '', 'cast' => 'string', 'label' => 'رمز برنامهٔ فینوتک (clientSecret)', 'is_sensitive' => true],
            ['group' => 'finnotech', 'key' => 'finnotech.nid', 'value' => '', 'cast' => 'string', 'label' => 'کد ملی صاحب برنامهٔ فینوتک'],
            ['group' => 'finnotech', 'key' => 'finnotech.verify_profile', 'value' => '1', 'cast' => 'boolean', 'label' => 'بررسی تطبیق کد ملی با موبایل در پروفایل مشتری'],
            ['group' => 'finnotech', 'key' => 'finnotech.verify_cards', 'value' => '1', 'cast' => 'boolean', 'label' => 'بررسی تطبیق کارت بانکی با کد ملی'],
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
