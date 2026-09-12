<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

/**
 * قالب‌های پیش‌فرض پیامک (فاز ۱۰) — متن‌ها همان پیام‌های فعلی کد هستند؛
 * از پنل ادمین (قالب‌های پیامک) قابل ویرایش.
 * اگر ردیف غیرفعال شود، متن پیش‌فرض کد (fallback) ارسال می‌شود.
 */
class SmsTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => 'otp_login',
                'title' => 'کد ورود (OTP)',
                'body' => "کد ورود شما به «کافی‌نت آنلاین»: {code}\nاعتبار کد: {minutes} دقیقه",
                'variables' => "{code} کد عددی · {minutes} مدت اعتبار (دقیقه)",
            ],
            [
                'key' => 'order.accepted',
                'title' => 'پذیرش سفارش توسط کافی‌نت',
                'body' => 'درخواست {order_number} شما توسط {accepted_by} پذیرفته شد؛ برای شروع کار، پرداخت را در اپ انجام دهید. کافی‌نت آنلاین',
                'variables' => '{order_number} شماره سفارش · {accepted_by} «اپراتور X از کافی‌نت Y» یا «کافی‌نت Y»',
            ],
            [
                'key' => 'order.queued',
                'title' => 'رفتن سفارش به صف بررسی',
                'body' => 'سفارش {order_number} شما در مهلت پخش پذیرفته نشد؛ به صف بررسی کارشناسان کافی‌نت آنلاین منتقل شد و نتیجه از طریق پیامک اطلاع داده می‌شود.',
                'variables' => '{order_number} شماره سفارش',
            ],
            [
                'key' => 'order.cancelled',
                'title' => 'لغو سفارش',
                'body' => 'سفارش {order_number} شما لغو شد: {reason} — وجه پرداختی طبق فرایند تسویه برگشت داده می‌شود. کافی‌نت آنلاین',
                'variables' => '{order_number} شماره سفارش · {reason} دلیل لغو',
            ],
            [
                'key' => 'order.delivered',
                'title' => 'تحویل سفارش',
                'body' => 'سفارش {order_number} شما آماده و تحویل شد؛ برای مشاهدهٔ نتیجه به اپ مراجعه کنید. کافی‌نت آنلاین',
                'variables' => '{order_number} شماره سفارش',
            ],
            [
                'key' => 'test',
                'title' => 'پیامک آزمایشی (تنظیمات)',
                'body' => 'پیامک آزمایشی «{app_name}» — تنظیمات پیامک با موفقیت ذخیره شد.',
                'variables' => '{app_name} نام سیستم',
            ],

            /* ---- v25 — اطلاع‌رسانی رویدادی (تنظیمات پیامک → رویدادها) ---- */
            [
                'key' => 'notify.order_transferred',
                'title' => 'انتقال درخواست به کافی‌نت/اپراتور آفلاین',
                'body' => 'سفارش {order_number} از طریق مدیریت به شما ({role_name}) واگذار شد — کافی‌نت آنلاین',
                'variables' => '{order_number} شماره سفارش · {role_name} نقش گیرنده',
            ],
            [
                'key' => 'notify.salary_paid',
                'title' => 'واریز حقوق به کیف پول',
                'body' => 'واریز حقوق: مبلغ {amount} به کیف پول شما واریز شد ({context}) — کافی‌نت آنلاین',
                'variables' => '{amount} مبلغ واریزی · {context} شرح (سفارش/تسویه)',
            ],
            [
                'key' => 'notify.unaccepted_request',
                'title' => 'درخواست بی‌پذیرش (یادآوری مدیر)',
                'body' => 'درخواست {order_number} مشتری {customer} پس از {minutes} دقیقه هنوز پذیرش نشده — بررسی کنید — کافی‌نت آنلاین',
                'variables' => '{order_number} شماره سفارش · {customer} نام مشتری · {minutes} دقیقه',
            ],
        ];

        foreach ($rows as $row) {
            SmsTemplate::firstOrCreate(
                ['key' => $row['key']],
                [
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'variables' => $row['variables'],
                    'is_active' => true,
                ],
            );
        }
    }
}
