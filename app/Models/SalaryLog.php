<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'coffeenet_id', 'user_id', 'period', 'type', 'amount',
        'description', 'logged_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function coffeenet(): BelongsTo
    {
        return $this->belongsTo(Coffeenet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** کاربر ثبت‌کننده لاگ (مدیر کافی‌نت) */
    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
