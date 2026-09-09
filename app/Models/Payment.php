<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** پرداختِ سفارش (پیش‌فرض) */
    public const PURPOSE_ORDER = 'order';

    /** شارژ کیف پول مشتری از درگاه */
    public const PURPOSE_WALLET = 'wallet';

    protected $fillable = [
        'order_id', 'user_id', 'purpose', 'amount', 'driver', 'ref_id',
        'status', 'paid_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** آیا این پرداخت برای شارژ کیف پول است؟ */
    public function isForWallet(): bool
    {
        return $this->purpose === self::PURPOSE_WALLET;
    }
}
