<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isCredit = $this->type?->value === 'credit';

        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'type_label' => $isCredit ? 'واریز' : 'برداشت',
            'amount' => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'description' => $this->description,
            'created_at_fa' => $this->created_at ? fa_date($this->created_at, 'Y/m/d H:i') : null,
        ];
    }
}
