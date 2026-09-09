<?php

use App\Console\Commands\CleanupSystem;
use App\Console\Commands\ExpireBroadcasts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| زمان‌بندی‌های سیستم (فاز ۶)
|--------------------------------------------------------------------------
| موتور تخصیص سفارش: هر دقیقه سفارش‌های پخشیِ منقضی‌شده تعیین‌تکلیف می‌شوند
| (ری‌پخش خودکار یا انتقال به صف + پیامک). دقتِ ثانیه‌ای از طریق انقضای
| «تنبل» روی هر poll پنل‌ها تأمین می‌شود.
|
*/

Schedule::command(ExpireBroadcasts::class)->everyMinute()->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| پاکسازی دوره‌ای (فاز ۱۱ — امنیت و لانچ)
|--------------------------------------------------------------------------
| هر شب ساعت ۰۳:۳۰: کد OTP منقضی، اعلان/لاگ‌های قدیمی طبق نگهداشت
| تنظیم‌شدهٔ پنل + روتیشن حجمی لاگ لاراول. گزارش آخرین اجرا در
| settings (system.cleanup.last) — نمایش زنده در /admin/system.
|
*/

Schedule::command(CleanupSystem::class)->dailyAt('03:30')->withoutOverlapping()->onOneServer();
