<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v13 — درگاه‌های بانکی + پرووایدرهای پیامک جدید:
 *
 *   پیامک:  آی‌پی‌پنل (ippanel) · ملی‌پیامک (melipayamak) · ایده‌پردازان (idehpardazan)
 *   درگاه:  بانک ملت (behpardakht) · بانک ملی (sep) · درگاه سپهر (sepehr)
 *
 * ردیف‌های تنظیماتِ خالی insert می‌شوند تا پنل مدیریت فیلدهای آن‌ها را
 * نمایش دهد؛ مقادیر موجود دست نمی‌خورند (insertOrIgnore).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            // ---------- پیامک: آی‌پی‌پنل ----------
            ['sms', 'sms.ippanel.username', '', 'string', 'نام کاربری پنل آی‌پی‌پنل', 0],
            ['sms', 'sms.ippanel.password', '', 'string', 'رمز عبور پنل آی‌پی‌پنل', 1],
            ['sms', 'sms.ippanel.from', '', 'string', 'شماره خط فرستنده آی‌پی‌پنل', 0],
            ['sms', 'sms.ippanel.endpoint', 'https://ippanel.com/patterns/pattern', 'string', 'آدرس ارسال پترن آی‌پی‌پنل', 0],

            // ---------- پیامک: ملی‌پیامک ----------
            ['sms', 'sms.melipayamak.username', '', 'string', 'نام کاربری پنل ملی‌پیامک', 0],
            ['sms', 'sms.melipayamak.password', '', 'string', 'رمز عبور پنل ملی‌پیامک', 1],
            ['sms', 'sms.melipayamak.from', '', 'string', 'شماره خط فرستنده ملی‌پیامک', 0],
            ['sms', 'sms.melipayamak.endpoint', 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', 'string', 'آدرس REST سرویس متن ثابت ملی‌پیامک', 0],

            // ---------- پیامک: ایده‌پردازان ----------
            ['sms', 'sms.idehpardazan.api_key', '', 'string', 'UserApiKey پنل ایده‌پردازان', 1],
            ['sms', 'sms.idehpardazan.secret_key', '', 'string', 'SecretKey پنل ایده‌پردازان', 1],
            ['sms', 'sms.idehpardazan.endpoint', 'https://RestfulSms.com/api/UltraFastSend/direct', 'string', 'آدرس ارسال قالب سریع ایده‌پردازان', 0],

            // ---------- درگاه: بانک ملت (به‌پرداخت) ----------
            ['payment', 'payment.behpardakht.terminal_id', '', 'string', 'شماره ترمینال به‌پرداخت ملت (بانک ملت)', 0],
            ['payment', 'payment.behpardakht.username', '', 'string', 'نام کاربری به‌پرداخت ملت (بانک ملت)', 0],
            ['payment', 'payment.behpardakht.password', '', 'string', 'رمز عبور به‌پرداخت ملت (بانک ملت)', 1],

            // ---------- درگاه: بانک ملی (سپ SEP) ----------
            ['payment', 'payment.sep.terminal_id', '', 'string', 'شماره ترمینال درگاه سپ بانک ملی', 0],

            // ---------- درگاه: بانک صادرات (سپهر) ----------
            ['payment', 'payment.sepehr.terminal_id', '', 'string', 'شماره ترمینال درگاه سپهر (بانک صادرات)', 0],
        ];

        foreach ($rows as [$group, $key, $value, $cast, $label, $sensitive]) {
            DB::table('settings')->insertOrIgnore([
                'group' => $group,
                'key' => $key,
                'value' => $value,
                'cast' => $cast,
                'label' => $label,
                'is_sensitive' => $sensitive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'sms.ippanel.username', 'sms.ippanel.password', 'sms.ippanel.from', 'sms.ippanel.endpoint',
            'sms.melipayamak.username', 'sms.melipayamak.password', 'sms.melipayamak.from', 'sms.melipayamak.endpoint',
            'sms.idehpardazan.api_key', 'sms.idehpardazan.secret_key', 'sms.idehpardazan.endpoint',
            'payment.behpardakht.terminal_id', 'payment.behpardakht.username', 'payment.behpardakht.password',
            'payment.sep.terminal_id',
            'payment.sepehr.terminal_id',
        ])->delete();
    }
};
