<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * v40 — لاگ استعلام‌های فینوتک (شاهکار / تطبیق کارت / توکن).
 */
class FinnotechLog extends Model
{
    protected $fillable = [
        'user_id', 'type', 'mobile', 'national_id', 'card_number',
        'matched', 'succeeded', 'response_code', 'track_id', 'error_message', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'matched' => 'boolean',
            'succeeded' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** عنوان فارسی نوع استعلام */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'shahkar' => 'شاهکار (کد ملی ↔ موبایل)',
            'card_owner' => 'تطبیق کارت و کد ملی',
            'mobile_card' => 'تطبیق کارت و موبایل',
            'token' => 'توکن',
            default => $this->type,
        };
    }

    /** شماره کارت ماسک‌شده برای نمایش امن */
    public function maskedCard(): ?string
    {
        if (! $this->card_number || strlen($this->card_number) < 12) {
            return $this->card_number;
        }

        return substr($this->card_number, 0, 6).'******'.substr($this->card_number, -4);
    }
}
