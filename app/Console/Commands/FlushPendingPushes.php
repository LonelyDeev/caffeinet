<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Push\PushManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * v36 → v37 — «پوش تاخیری» فقط به‌عنوان تور ایمنی.
 *
 * با رفتار v37 (= v34) پوش دستگاه «همیشه» در لحظهٔ ساخت اعلان ارسال
 * می‌شود؛ تنها ردیف‌هایی که pushed ≠ 1 دارند، اعلان‌هایی هستند که
 * لحظهٔ ساختشان سرویس پوش خاموش بوده است — اینجا (بعد از فعال شدن
 * سرویس) دنبالشان می‌گردیم و پوش می‌رود + علامت می‌خورد.
 *
 *  • خوانده‌شده‌ها (read_at) چک نمی‌شوند — کاربر خودش دیده است
 *  • فقط ۱۵ دقیقهٔ اخیر — بعدش اعلان کهنه دیگر پوش نمی‌شود
 *
 * php artisan notifications:flush-pending  (هر دقیقه زمان‌بندی‌شده در
 * پس‌زمینه؛ داخل هر اجرا ۳ گذار با فاصلهٔ ۱۵ ثانیه)
 */
class FlushPendingPushes extends Command
{
    /** گذارهای هر اجرا × فاصلهٔ بین آن‌ها ≈ ۴۵ ثانیه (زیر دورهٔ کرون) */
    protected const PASSES = 3;

    protected const PASS_SLEEP = 15;

    /** پنجرهٔ عمر اعلانِ در انتظار پوش */
    protected const MAX_AGE_MINUTES = 15;

    protected $signature = 'notifications:flush-pending {--once : فقط یک گذار بدون حلقه}';

    protected $description = 'ارسال پوش سیستمی اعلان‌های در انتظار برای گیرندگانی که الان آفلاین شده‌اند';

    public function handle(PushManager $push): int
    {
        $passes = $this->option('once') ? 1 : self::PASSES;

        for ($i = 0; $i < $passes; $i++) {
            $this->flushOnce($push);

            if ($i < $passes - 1) {
                sleep(self::PASS_SLEEP);
            }
        }

        return self::SUCCESS;
    }

    /** یک گذار: اعلان‌های pending → گیرندهٔ آفلاین → پوش + علامت */
    protected function flushOnce(PushManager $push): void
    {
        try {
            if (! $push->enabled()) {
                return; // سرویس پوش خاموش — pending می‌مانند (بی‌ضرر)
            }

            $rows = DB::table('notifications')
                ->whereNull('read_at')
                ->where('created_at', '>=', now()->subMinutes(self::MAX_AGE_MINUTES))
                ->whereRaw("COALESCE(json_extract(data, '$.pushed'), 0) = 0")
                ->orderBy('id')
                ->limit(200)
                ->get(['id', 'notifiable_id', 'data']);

            if ($rows->isEmpty()) {
                return;
            }

            $users = User::query()
                ->whereIn('id', $rows->pluck('notifiable_id')->unique()->all())
                ->get()
                ->keyBy('id');

            foreach ($rows as $row) {
                $user = $users->get((int) $row->notifiable_id);

                // کاربر حذف‌شده/ناموجود — علامت بزن تا دوباره چک نشود
                if (! $user) {
                    $this->markRow($row->id);
                    continue;
                }

                // v37: بدون چک آنلاین — این ردیف‌ها فقط وقتی pending م‌مانند
                // که سرویس پوش لحظهٔ ساخت اعلان خاموش بوده است
                $payload = json_decode((string) $row->data, true) ?: [];

                // تلاش پوش — هر نتیجه‌ای که باشد «تلاش شده» است
                $push->sendToUser(
                    $user,
                    mb_substr((string) ($payload['title'] ?? ''), 0, 100) ?: 'اعلان جدید',
                    mb_substr((string) ($payload['body'] ?? ''), 0, 250),
                    [
                        'url' => $payload['url'] ?? null,
                        'event' => $payload['event'] ?? null,
                        'tag' => 'cn-'.($payload['type'] ?? 'system').'-'.$row->notifiable_id,
                        'oid' => (int) ($payload['ref']['order_id'] ?? 0) ?: null,
                    ],
                );

                $this->markRow($row->id);
            }
        } catch (Throwable $e) {
            $this->warn('flush-pending: '.$e->getMessage());
        }
    }

    /** علامت‌گذاری یک ردیف به‌عنوان «پوش تلاش شده» */
    protected function markRow(string $id): void
    {
        try {
            $row = DB::table('notifications')->where('id', $id)->first(['id', 'data']);

            if (! $row) {
                return;
            }

            $payload = json_decode((string) $row->data, true) ?: [];
            $payload['pushed'] = 1;
            $payload['pushed_at'] = now()->toDateTimeString();

            DB::table('notifications')
                ->where('id', $id)
                ->update(['data' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        } catch (Throwable) {
            // علامت‌نخوردن = بعداً دوباره تلاش می‌شود — بی‌ضرر
        }
    }
}
