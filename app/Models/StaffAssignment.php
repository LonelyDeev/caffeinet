<?php

namespace App\Models;

use App\Enums\StaffPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StaffAssignment extends Model
{
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'coffeenet_id', 'position', 'permissions',
        'assigned_by', 'is_active', 'approval_status',
    ];

    protected function casts(): array
    {
        return [
            'position' => StaffPosition::class,
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coffeenet(): BelongsTo
    {
        return $this->belongsTo(Coffeenet::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * آیا این اپراتور دسترسی مشخص (از کاتالوگ OperatorPermissions) را دارد؟
     * لایه دوم روی نقش Spatie — در پنل اپراتور اعمال می‌شود.
     */
    public function hasOperatorPermission(string $key): bool
    {
        return in_array($key, (array) ($this->permissions ?? []), true);
    }

    /**
     * مدل حقوق این کارمند در همین کافی‌نت (یکتا بر اساس user+coffeenet).
     *
     * قید coffeenet فقط در lazy load (مدل واقعی والد) اعمال می‌شود؛
     * در eager load مدل والد خالی است و قید skip می‌شود — کنترلرها باید
     * هنگام with() قید coffeenet را صریحاً بدهند (کوئری‌ها همیشه per-coffeenet‌اند).
     */
    public function salarySetting(): HasOne
    {
        return $this->hasOne(SalarySetting::class, 'user_id', 'user_id')
            ->when(
                $this->coffeenet_id,
                fn ($query) => $query->where('salary_settings.coffeenet_id', $this->coffeenet_id)
            );
    }
}
