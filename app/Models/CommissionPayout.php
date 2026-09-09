<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommissionPayout extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id', 'wallet_id', 'role', 'holder_type', 'holder_id',
        'amount', 'snapshot', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** دارندهٔ سهم: User | Organization | Coffeenet */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'platform' => 'پلتفرم',
            'organization' => 'سازمان',
            'coffeenet' => 'کافی‌نت',
            'operator' => 'اپراتور',
            default => $this->role,
        };
    }
}

