<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'mobile', 'code_hash', 'purpose', 'expires_at', 'attempts', 'ip', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
