<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Service */
class ServiceResource extends JsonResource
{
    /**
     * @param  float|null  $totalAmount  جمع کل (قیمت + هزینه‌ها) — از قبل محاسبه‌شده
     */
    public function __construct($resource, protected ?float $totalAmount = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'base_price' => (float) $this->base_price,
            'total_amount' => $this->totalAmount
                ?? (float) $this->base_price + ($this->relationLoaded('costs') ? (float) $this->costs->sum('amount') : 0.0),
            'estimated_time' => (int) $this->estimated_time,
            'estimated_time_label' => $this->estimated_time > 0
                ? 'حدود '.fa_digits((string) $this->estimated_time).' دقیقه'
                : '—',
            'is_featured' => (bool) $this->is_featured,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'icon' => $this->category?->icon ?: '📁',
            ]),
        ];
    }
}
