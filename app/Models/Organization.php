<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id', 'name', 'type', 'national_id', 'phone',
        'province_id', 'city_id', 'address', 'status', 'verified_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function coffeenets(): HasMany
    {
        return $this->hasMany(Coffeenet::class);
    }

    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'holder');
    }
}
