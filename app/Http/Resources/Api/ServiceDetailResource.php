<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * جزئیات خدمت — فرم داینامیک + ردیف هزینه از snapshot نسخه جاری.
 *
 * @mixin \App\Models\Service
 */
class ServiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->versions()->orderByDesc('version')->first();
        $snapshot = is_array($version?->snapshot) ? $version->snapshot : [];

        $fields = array_values(
            is_array($snapshot['form_fields'] ?? null) ? $snapshot['form_fields'] : []
        );

        $fileFieldCount = collect($fields)
            ->filter(fn ($f) => ($f['field_type'] ?? '') === 'file')
            ->count();

        $costs = is_array($snapshot['costs'] ?? null) ? $snapshot['costs'] : [];

        $price = (float) ($snapshot['base_price'] ?? $this->base_price);
        $expenses = collect($costs)->sum(fn ($c) => (float) ($c['amount'] ?? 0));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'icon' => $this->category?->icon ?: '📁',
            ]),
            'base_price' => $price,
            'total_amount' => $price + $expenses,
            'estimated_time' => (int) ($snapshot['estimated_time'] ?? $this->estimated_time),
            'estimated_time_label' => ($snapshot['estimated_time'] ?? $this->estimated_time) > 0
                ? 'حدود '.fa_digits((string) ($snapshot['estimated_time'] ?? $this->estimated_time)).' دقیقه'
                : '—',
            'is_featured' => (bool) $this->is_featured,

            // فاز ۱۵ — رسانه، وضعیت برخط و آلرت خدمت
            'image_url' => $this->when($this->image_path, fn () => $this->imageUrl()),
            'availability_state' => $this->availabilityState(),
            'availability_note' => $this->availabilityNote(),
            'expires_at_label' => $this->expiresAtLabel(),
            'alert' => $this->alertPayload(),

            'version' => (int) ($version?->version ?? $this->version ?? 1),
            'version_id' => $version?->id,
            'requires_upload' => (bool) ($snapshot['requires_upload'] ?? $this->requires_upload),
            'requires_verification' => (bool) ($snapshot['requires_verification'] ?? $this->requires_verification),
            'has_file_fields' => $fileFieldCount > 0,
            'costs' => collect($costs)->map(fn ($c) => [
                'type' => $c['type'] ?? 'expense',
                'title' => $c['title'] ?? '—',
                'amount' => (float) ($c['amount'] ?? 0),
                'is_commission' => (bool) ($c['is_commission'] ?? false),
                'note' => $c['note'] ?? null,
            ])->values(),
            'form_fields' => collect($fields)->map(fn ($f) => [
                'name' => (string) ($f['name'] ?? ''),
                'label' => (string) ($f['label'] ?? ''),
                'field_type' => (string) ($f['field_type'] ?? 'text'),
                'placeholder' => $f['placeholder'] ?? null,
                'help_text' => $f['help_text'] ?? null,
                'is_required' => (bool) ($f['is_required'] ?? false),
                'options' => $f['options'] ?? null,
                'validation' => $f['validation'] ?? null,
                'sort' => (int) ($f['sort'] ?? 0),
            ])->values(),
        ];
    }
}
