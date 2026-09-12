<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Payment;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'family', 'email', 'password', 'mobile', 'gender',
    'province_id', 'city_id', 'birthdate', 'profile_completed',
    'is_active', 'last_login_at', 'last_seen_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'birthdate' => 'date',
            'profile_completed' => 'boolean',
            'is_active' => 'boolean',
            'gender' => Gender::class,
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'holder');
    }

    /** تراکنش‌های درگاهِ پرداخت این کاربر (سفارش/شارژ کیف) */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class);
    }

    /** تاریخچهٔ ورود/خروج (درخواست بازخوردی — پروفایل مشتری) */
    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    /** توکن‌های نوتیف دستگاه (Web Push / FCM) — v25 */
    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    /** سفارش‌های این مشتری */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /** امتیازهایی که این مشتری به سفارش‌هایش داده (از طریق سفارش‌ها) */
    public function orderRatings(): HasManyThrough
    {
        return $this->hasManyThrough(OrderRating::class, Order::class, 'customer_id', 'order_id', 'id', 'id');
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /** نام کامل */
    public function getFullNameAttribute(): string
    {
        return trim(($this->name ?? '').' '.($this->family ?? '')) ?: ($this->mobile ?? $this->email ?? '—');
    }

    /**
     * آیا کاربر «آنلاین» است؟ (v25 حضور — v29 آستانهٔ ثانیه‌ای)
     * آنلاین = در N ثانیهٔ اخیر درخواستی از او دیده شده
     * (پنل‌ها هر ۳۰ ثانیه بج زنگ و اپ هر ۲۵ ثانیه poll می‌کنند).
     *
     * v29: اگر سوییچ آستانهٔ آفلاین در تنظیمات خاموش باشد، مقدار ۰
     * برگردانده می‌شود → کاربر بلافاصله پس از آخرین درخواستش «آفلاین»
     * است (لحظه‌ای) — یعنی پوش/پیامک حتی هنگام باز بودن پنل ارسال می‌شود.
     */
    public function isOnline(?int $withinSeconds = null): bool
    {
        if ($this->last_seen_at === null) {
            return false;
        }

        $seconds = $withinSeconds ?? offline_threshold_seconds();

        // آفلاینِ لحظه‌ای: فقط در همان لحظهٔ درخواستِ خودش آنلاین است
        if ($seconds <= 0) {
            return $this->last_seen_at->gte(now());
        }

        return $this->last_seen_at->gt(now()->subSeconds($seconds));
    }

    /**
     * بخش‌های پنل مدیریت که این کاربر به آنها دسترسی دارد (درخواست بازخوردی ۶-۴).
     * برای مدیر کل — همهٔ بخش‌ها ['*'].
     */
    public function sections(): array
    {
        if ($this->hasRole('super_admin')) {
            return ['*'];
        }

        if (! $this->hasRole('admin')) {
            return [];
        }

        $permissions = $this->permissions->pluck('name')->all();

        $sections = [];
        foreach (\App\Policies\AdminAccessPolicy::SECTION_PERMISSIONS as $section => $permission) {
            if ($permission === null || in_array($permission, $permissions, true)) {
                $sections[] = $section;
            }
        }

        return $sections;
    }
}
