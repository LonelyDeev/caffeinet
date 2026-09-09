<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total = (float) $this->price + (float) $this->expenses;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'total_amount' => $total,
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'icon' => $this->service?->category?->icon ?: '📄',
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_fa' => $this->created_at ? fa_date($this->created_at, 'Y/m/d H:i') : null,
            'paid' => $this->status !== \App\Enums\OrderStatus::PendingPayment
                && $this->status !== \App\Enums\OrderStatus::Cancelled,
        ];
    }
}
