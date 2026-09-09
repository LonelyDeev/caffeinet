<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionSetting extends Model
{
    protected $fillable = [
        'scope', 'service_id', 'platform_type', 'platform_value',
        'organization_type', 'organization_value',
        'coffeenet_type', 'coffeenet_value', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'platform_value' => 'decimal:2',
            'organization_value' => 'decimal:2',
            'coffeenet_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /** قاعده فعال سراسری */
    public static function global(): ?self
    {
        return static::where('scope', 'global')->where('is_active', true)->first();
    }
}
