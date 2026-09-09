<?php

namespace App\Console\Commands;

use App\Services\Orders\OrderAssignmentService;
use Illuminate\Console\Command;

/**
 * فاز ۶ — تعیین‌تکلیف سفارش‌های پخشیِ منقضی‌شده (۶۰ ثانیه بدون پذیرش).
 *
 * cron دقیق لازم نیست: هر خواندنِ لیست پخش/جزئیات سفارش هم expireStale را
 * تنبل اجرا می‌کند؛ این دستور پوشش زمان‌بندی‌شده است.
 *
 * php artisan orders:expire-broadcasts
 */
class ExpireBroadcasts extends Command
{
    protected $signature = 'orders:expire-broadcasts {--limit= : حداکثر تعداد سفارش در هر اجرا}';

    protected $description = 'تعیین‌تکلیف سفارش‌های پخشی منقضی‌شده (ری‌پخش یا انتقال به صف + پیامک)';

    public function handle(OrderAssignmentService $assignment): int
    {
        $limit = (int) ($this->option('limit') ?: 50);

        $count = $assignment->expireStale($limit);

        $this->info($count > 0
            ? "تعیین‌تکلیف {$count} سفارش منقضی‌شده انجام شد."
            : 'سفارش منقضی‌شده‌ای وجود ندارد.');

        return self::SUCCESS;
    }
}
