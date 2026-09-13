<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * v33 — گزینهٔ دلایل نظرسنجی (چک‌باکس‌های کارتی زیر ستاره‌ها).
 *
 *  - type: pos (نقطه قوت — برای امتیازهای ۴/۵) | neg (نقطه ضعف — برای ۱/۲)
 *  - مدیریت کامل (افزودن/ویرایش/غیرفعال/حذف) از پنل مدیریت کل
 *  - امتیازهای ثبت‌شده اسنپ‌شات ({id,title,type}) دارند → حذف گزینه تاریخ را خراب نمی‌کند
 */
class RatingOption extends Model
{
    protected $fillable = ['title', 'type', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** فقط گزینه‌های فعال */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** ترتیب استاندارد: نوع، بعد شمارهٔ ترتیب */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw("type = 'neg'")->orderBy('sort_order')->orderBy('id');
    }
}
