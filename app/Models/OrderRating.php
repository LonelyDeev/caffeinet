<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نظرسنجی سفارش — امتیاز مشتری پس از تحویل/تکمیل.
 *
 * v33:
 *  - rating: امتیاز کلی تجربه (۱..۵ ستاره)
 *  - operator_rating: امتیاز اپراتورِ مسئول (اختیاری — فقط وقتی سفارش اپراتور دارد)
 *  - options: اسنپ‌شات گزینه‌های دلایل انتخابی [{id,title,type}]
 */
class OrderRating extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'rating', 'operator_rating', 'comment', 'options', 'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'operator_rating' => 'integer',
            'options' => 'array',
            'rated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** برچسب‌های گزینه‌های انتخابی (برای نمایش) */
    public function optionTitles(): array
    {
        return array_column($this->options ?? [], 'title');
    }
}
