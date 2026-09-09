<?php

namespace App\Models;

use App\Enums\CoffeenetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Coffeenet extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'phone', 'province_id', 'city_id',
        'address', 'status', 'approved_at', 'approved_by',
        'introduction_reward_paid', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => CoffeenetStatus::class,
            'approved_at' => 'datetime',
            'introduction_reward_paid' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class);
    }

    /** کاربر مدیرِ فعال این کافی‌نت (برای ویرایش از پنل مدیریت کل) */
    public function managerUser(): ?User
    {
        $assignment = $this->staffAssignments()
            ->where('position', 'manager')
            ->where('is_active', true)
            ->first();

        return $assignment?->user;
    }

    /** فاز ۶ — پخش‌های دریافتی این کافی‌نت */
    public function broadcasts(): HasMany
    {
        return $this->hasMany(OrderBroadcast::class);
    }

    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'holder');
    }
}
