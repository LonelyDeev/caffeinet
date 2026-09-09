<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCost extends Model
{
    protected $fillable = [
        'service_id', 'type', 'title', 'amount', 'is_commission', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_commission' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
