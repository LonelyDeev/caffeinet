<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSetting extends Model
{
    protected $fillable = [
        'introduction_reward', 'per_order_type', 'per_order_value', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'introduction_reward' => 'decimal:2',
            'per_order_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            ['introduction_reward' => 0, 'is_active' => true],
        );
    }
}
