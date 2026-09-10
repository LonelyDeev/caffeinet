<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * فاز ۱۵ — اطلاعیه‌های سامانه.
 *
 * رسانه: متن / تصویر / ویدیو (فایل آپلودی یا لینک مستقیم).
 * مخاطب: مشتریان، پنل‌ها (هر نقش جدا یا همه) یا همه.
 */
class Announcement extends Model
{
    public const AUDIENCES = [
        'customers' => 'مشتریان (اپ مشتری)',
        'admins' => 'مدیران کل',
        'org_managers' => 'مدیران سازمان',
        'coffeenet_managers' => 'مدیران کافی‌نت',
        'operators' => 'اپراتورها',
        'all_panels' => 'همه پنل‌ها (کارکنان)',
        'everyone' => 'همه (مشتریان + پنل‌ها)',
    ];

    public const MEDIA_TYPES = ['none', 'image', 'video'];

    protected $fillable = [
        'title', 'body', 'media_type', 'media_path', 'video_url',
        'audience', 'is_active', 'starts_at', 'ends_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /** آیا اطلاعیه همین حالا در پنجره نمایش است؟ */
    public function inWindow(): bool
    {
        $now = now();

        return (! $this->starts_at || $now->gte($this->starts_at))
            && (! $this->ends_at || $now->lte($this->ends_at));
    }

    /** URL رسانهٔ عمومی (تصویر یا ویدیوی آپلودی) */
    public function mediaUrl(): ?string
    {
        return $this->media_path ? Storage::disk('public')->url($this->media_path) : null;
    }

    /** URL نهایی ویدیو (آپلودی یا لینک) */
    public function videoUrl(): ?string
    {
        if ($this->media_type !== 'video') {
            return null;
        }

        return $this->mediaUrl() ?: ($this->video_url ?: null);
    }

    /** برچسب فارسی مخاطب */
    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    /** ساختار API برای کلاینت‌ها (پنل‌ها و اپ مشتری) */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'media_type' => $this->media_type,
            'media_url' => $this->media_type === 'image' ? $this->mediaUrl() : null,
            'video_url' => $this->videoUrl(),
            'audience' => $this->audience,
            'created_at_label' => fa_date($this->created_at, 'Y/m/d H:i'),
        ];
    }
}
