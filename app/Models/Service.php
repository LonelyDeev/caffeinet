<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'base_price',
        'estimated_time', 'requires_upload', 'requires_verification',
        'is_active', 'is_featured', 'sort', 'version',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'requires_upload' => 'boolean',
            'requires_verification' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ServiceCost::class);
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(ServiceFormField::class)
            ->where('is_active', true)->orderBy('sort');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ServiceVersion::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(\App\Models\Order::class);
    }

    /** مبلغ مشمول کمیسیون (مجموع ردیف‌های دارای فلگ) */
    public function commissionableAmount(): float
    {
        return (float) $this->costs()->where('is_commission', true)->sum('amount');
    }
}
