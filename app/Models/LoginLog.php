<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * لاگ ورود/خروج کاربران (درخواست بازخوردی — پروفایل مشتری پنل مدیریت کل).
 */
class LoginLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'guard', 'event', 'ip', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** فقط رخدادهای ورود */
    public function scopeLogins(Builder $q): Builder
    {
        return $q->where('event', 'login');
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'login' => 'ورود',
            'logout' => 'خروج',
            'forced_logout' => 'خروج اجباری (مسدودسازی)',
            default => $this->event,
        };
    }

    public function guardLabel(): string
    {
        return match ($this->guard) {
            'customer' => 'اپ مشتری',
            'admin' => 'پنل مدیریت کل',
            'coffeenet' => 'پنل کافی‌نت',
            'org' => 'پنل سازمان',
            'operator' => 'پنل اپراتور',
            default => $this->guard,
        };
    }
}
