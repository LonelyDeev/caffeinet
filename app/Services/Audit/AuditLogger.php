<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * لاگر فعالیت — تمام اقدامات حساس پنل‌ها را ثبت می‌کند.
 * App\Services\Audit\AuditLogger::log('admin.created', $user, $old, $new, 'توضیح')
 */
class AuditLogger
{
    public static function log(
        string $action,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
    ): void {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'description' => $description,
                'ip' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // لاگ فعالیت هرگز نباید جریان اصلی را متوقف کند
        }
    }
}
