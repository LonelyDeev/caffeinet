<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'mobile', 'template_key', 'message', 'provider', 'status', 'response', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
