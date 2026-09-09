<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderBroadcast extends Model
{
    protected $fillable = ['order_id', 'coffeenet_id', 'sent_at', 'seen_at'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function coffeenet(): BelongsTo
    {
        return $this->belongsTo(Coffeenet::class);
    }
}
