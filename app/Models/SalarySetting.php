<?php

namespace App\Models;

use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalarySetting extends Model
{
    protected $fillable = [
        'coffeenet_id', 'user_id', 'type', 'rate', 'overtime_rate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => SalaryType::class,
            'rate' => 'decimal:2',
            'overtime_rate' => 'decimal:2',
            'is_active' => 'boolean',
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
}
