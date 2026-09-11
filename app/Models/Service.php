<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** آواتار پیش‌فرض تصویر خدمت (در فرم‌ساز) */
    public const AVAILABILITY_ACTIVE = 'active';
    public const AVAILABILITY_UNAVAILABLE = 'unavailable';

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'base_price',
        'estimated_time', 'requires_upload', 'requires_verification',
        'is_active', 'is_featured', 'sort', 'version',
        // فاز ۱۵ — رسانه و وضعیت
        'image_path', 'availability', 'unavailable_note',
        'expires_at', 'expired_note',
        'alert_type', 'alert_text', 'alert_image_path',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'requires_upload' => 'boolean',
            'requires_verification' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'expires_at' => 'datetime',
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

    /* =================================================================
     |  فاز ۱۵ — رسانه، وضعیت برخط و آلرت خدمت
     * ================================================================= */

    /** URL عمومی تصویر خدمت (null اگر ندارد) — از روت /media (فاز ۲۲) */
    public function imageUrl(): ?string
    {
        return media_url($this->image_path);
    }

    /** URL عمومی تصویر آلرت (null اگر ندارد) — از روت /media (فاز ۲۲) */
    public function alertImageUrl(): ?string
    {
        return media_url($this->alert_image_path);
    }

    /**
     * وضعیت نهایی قابل نمایش به مشتری:
     *   active        → همه‌چیز عادی
     *   unavailable   → قطع از سایت اصلی (مدیریت دستی)
     *   expired       → مهلت خدمت تمام شده (مثلاً مهلت ثبت‌نام)
     *   inactive      → خدمت غیرفعال است (در کاتالوگ نمی‌آید)
     */
    public function availabilityState(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->availability === self::AVAILABILITY_UNAVAILABLE) {
            return 'unavailable';
        }

        if ($this->expires_at && now()->gte($this->expires_at)) {
            return 'expired';
        }

        return 'active';
    }

    /** پیام قابل نمایش برای وضعیت غیر فعال (قطع/منقضی) — null اگر فعال است */
    public function availabilityNote(): ?string
    {
        return match ($this->availabilityState()) {
            'unavailable' => $this->unavailable_note ?: 'این خدمت در حال حاضر از سایت اصلی قطع است و به‌صورت موقت قابل ثبت نیست.',
            'expired' => $this->expired_note ?: 'مهلت این خدمت (مثلاً مهلت ثبت‌نام) به پایان رسیده و دیگر قابل ثبت نیست.',
            default => null,
        };
    }

    /** برچسب شمسی مهلت خدمت برای نمایش (مثلاً «تا ۱۴۰۵/۰۶/۳۰») */
    public function expiresAtLabel(): ?string
    {
        if (! $this->expires_at) {
            return null;
        }

        return fa_date($this->expires_at, 'Y/m/d');
    }

    /** ساختار آلرت خدمت برای API (null اگر آلرت ندارد) */
    public function alertPayload(): ?array
    {
        if (! $this->alert_type || $this->alert_type === 'none') {
            return null;
        }

        return [
            'type' => $this->alert_type, // text | image
            'text' => $this->alert_type === 'text' ? ($this->alert_text ?: null) : null,
            'image_url' => $this->alert_type === 'image' ? $this->alertImageUrl() : null,
        ];
    }
}
