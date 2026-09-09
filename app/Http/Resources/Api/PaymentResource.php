<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver' => $this->driver,
            'driver_label' => match ($this->driver) {
                'wallet' => 'کیف پول',
                'local' => 'درگاه تست',
                'zarinpal' => 'زرین‌پال',
                default => $this->driver,
            },
            'amount' => (float) $this->amount,
            'ref_id' => $this->ref_id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_at_fa' => $this->paid_at ? fa_date($this->paid_at) : null,
            'created_at_fa' => $this->created_at ? fa_date($this->created_at) : null,
        ];
    }
}
