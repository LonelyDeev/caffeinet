<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * درخواست بازخوردی ۶-۶ — مرکز پیامک پترن‌محور:
 *   category     → دستهٔ قالب (auth | order | wallet | support)
 *   pattern_code → کد پترن ثبت‌شده در پنل پرووایدر (Kavenegar VerifyLookup / ippanel)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            $table->string('category', 40)->default('other')->after('key');
            $table->string('pattern_code', 100)->nullable()->after('variables');
        });

        // دسته‌بندی قالب‌های موجود
        $this->updateCategory('otp_login', 'auth');

        foreach (['order.paid', 'order.accepted', 'order.queued', 'order.needs_info', 'order.in_progress', 'order.delivered', 'order.completed', 'order.cancelled', 'order.refunded'] as $key) {
            $this->updateCategory($key, 'order');
        }

        $this->updateCategory('wallet.charged', 'wallet');
        $this->updateCategory('ticket.replied', 'support');

        // قالب‌های جدید — پوشش پیامکی همهٔ حرکات مشتری
        $this->ensureTemplate([
            'key' => 'order.paid',
            'title' => 'پرداخت موفق سفارش',
            'category' => 'order',
            'body' => '{app_name}
سفارش {order_number} با موفقیت پرداخت شد و در صف انجام قرار گرفت. کد پیگیری: {trace}.',
            'variables' => '{app_name} نام سیستم · {order_number} شماره سفارش · {trace} کد پیگیری پرداخت',
        ]);

        $this->ensureTemplate([
            'key' => 'order.needs_info',
            'title' => 'نیاز به اطلاعات تکمیلی',
            'category' => 'order',
            'body' => 'برای ادامهٔ سفارش {order_number} به اطلاعات تکمیلی نیاز است؛ لطفاً از طریق گفتگوی سفارش در اپ {app_name} آن را ارسال کنید.',
            'variables' => '{order_number} شماره سفارش · {app_name} نام سیستم',
        ]);

        $this->ensureTemplate([
            'key' => 'order.in_progress',
            'title' => 'شروع انجام سفارش',
            'category' => 'order',
            'body' => 'کار روی سفارش {order_number} توسط {operator} آغاز شد. اپ {app_name}',
            'variables' => '{order_number} شماره سفارش · {operator} نام اپراتور · {app_name} نام سیستم',
        ]);

        $this->ensureTemplate([
            'key' => 'order.completed',
            'title' => 'تکمیل و تسویهٔ سفارش',
            'category' => 'order',
            'body' => 'سفارش {order_number} تکمیل و تسویه شد. از اعتماد شما به {app_name} سپاسگزاریم.',
            'variables' => '{order_number} شماره سفارش · {app_name} نام سیستم',
        ]);

        $this->ensureTemplate([
            'key' => 'order.refunded',
            'title' => 'بازگشت وجه به کیف پول',
            'category' => 'order',
            'body' => 'مبلغ سفارش {order_number} ({amount} تومان) به کیف پول شما در {app_name} برگشت داده شد.',
            'variables' => '{order_number} شماره سفارش · {amount} مبلغ بازگشتی · {app_name} نام سیستم',
        ]);

        $this->ensureTemplate([
            'key' => 'wallet.charged',
            'title' => 'واریز به کیف پول',
            'category' => 'wallet',
            'body' => 'مبلغ {amount} تومان به کیف پول شما در {app_name} واریز شد. موجودی فعلی: {balance} تومان.',
            'variables' => '{amount} مبلغ واریزی · {balance} موجودی پس از واریز · {app_name} نام سیستم',
        ]);

        $this->ensureTemplate([
            'key' => 'ticket.replied',
            'title' => 'پاسخ پشتیبانی به تیکت',
            'category' => 'support',
            'body' => 'به تیکت {ticket_number} شما در {app_name} پاسخ داده شد؛ برای مشاهده به اپ مراجعه کنید.',
            'variables' => '{ticket_number} شماره تیکت · {app_name} نام سیستم',
        ]);
    }

    public function down(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropColumn(['category', 'pattern_code']);
        });
    }

    protected function updateCategory(string $key, string $category): void
    {
        DB::table('sms_templates')->where('key', $key)->update(['category' => $category]);
    }

    protected function ensureTemplate(array $data): void
    {
        DB::table('sms_templates')->updateOrInsert(
            ['key' => $data['key']],
            [
                'title' => $data['title'],
                'category' => $data['category'],
                'body' => $data['body'],
                'variables' => $data['variables'],
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
};
