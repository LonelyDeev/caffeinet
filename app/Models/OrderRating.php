<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نظرسنجی سفارش — امتیاز مشتری (۱ تا ۵ ستاره) پس از تحویل/تکمیل.
 */
class OrderRating extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'rating', 'comment', 'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'rated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
