<?php

namespace App\Http\Resources\Api;

use App\Services\Finance\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mobile' => $this->mobile ? fa_digits($this->mobile) : null,
            'name' => $this->name,
            'family' => $this->family,
            'full_name' => $this->full_name,
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'province' => $this->whenLoaded('province', fn () => [
                'id' => $this->province?->id,
                'name' => $this->province?->name,
            ]),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city?->id,
                'name' => $this->city?->name,
            ]),
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'birthdate_fa' => $this->birthdate ? fa_date($this->birthdate, 'Y/m/d') : null,
            'profile_completed' => (bool) $this->profile_completed,
            'wallet_balance' => app(WalletService::class)->balance($this->resource),
            'member_since_fa' => $this->created_at ? fa_date($this->created_at, 'Y/m/d') : null,
        ];
    }
}
