<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['holder_type', 'holder_id', 'balance'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** گرفتن یا ساخت کیف پول یک holder */
    public static function for(Model $holder): self
    {
        return static::firstOrCreate(
            ['holder_type' => $holder::class, 'holder_id' => $holder->getKey()],
            ['balance' => 0],
        );
    }
}
