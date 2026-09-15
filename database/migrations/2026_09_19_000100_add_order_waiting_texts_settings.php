<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * v39 — کنترل مدیر بر صفحهٔ انتظار مشتری (کارت پخش / صف تعیین‌تکلیف):
 *
 *   • broadcast_timer_enabled: نمایش ثانیه‌شمار مهلت پخش (خاموش = فقط متن)
 *   • broadcast_text:          متن کارت «در حال ارسال به اپراتورها»
 *   • queued_text:             متن کارت «صف تعیین‌تکلیف» (متن جدید و ساده‌تر به درخواست مالک)
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->firstOrCreate(
            ['key' => 'orders.broadcast_timer_enabled'],
            ['group' => 'orders', 'key' => 'orders.broadcast_timer_enabled', 'value' => '1', 'cast' => 'boolean', 'label' => 'نمایش ثانیه‌شمار مهلت پخش در اپ مشتری'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'orders.broadcast_text'],
            ['group' => 'orders', 'key' => 'orders.broadcast_text', 'value' => 'درخواستتان بین اپراتورها و کافی‌نت‌های فعال پخش شده است؛ اولین اپراتوری که آن را بپذیرد، به شما وصل می‌شود و گفتگو آغاز می‌گردد.', 'cast' => 'string', 'label' => 'متن کارت پخش سفارش در اپ مشتری'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'orders.queued_text'],
            ['group' => 'orders', 'key' => 'orders.queued_text', 'value' => 'سفارش شما با موفقیت ثبت شد. همکاران ما در اولین فرصت آن را بررسی و به یکی از کافی‌نت‌ها تخصیص می‌دهند و نتیجه را از طریق پیامک و تماس به شما اطلاع می‌دهند.', 'cast' => 'string', 'label' => 'متن کارت صف تعیین‌تکلیف در اپ مشتری'],
        );
    }

    public function down(): void
    {
        Setting::query()->whereIn('key', [
            'orders.broadcast_timer_enabled',
            'orders.broadcast_text',
            'orders.queued_text',
        ])->delete();
    }
};
