<?php

namespace App\Http\Resources\Api;

use App\Models\OrderFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin \App\Models\Order */
class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total = (float) $this->price + (float) $this->expenses;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'price' => (float) $this->price,
            'expenses' => (float) $this->expenses,
            'commissionable_amount' => (float) $this->commissionable_amount,
            'total_amount' => $total,
            'cancel_reason' => $this->cancel_reason,
            'is_paid' => (bool) $this->paid_at,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_fa' => $this->created_at ? fa_date($this->created_at, 'Y/m/d H:i') : null,
            'paid_at_fa' => $this->paid_at ? fa_date($this->paid_at, 'Y/m/d H:i') : null,
            'accepted_at_fa' => $this->accepted_at ? fa_date($this->accepted_at, 'Y/m/d H:i') : null,
            'completed_at_fa' => $this->completed_at ? fa_date($this->completed_at, 'Y/m/d H:i') : null,

            // فاز ۶ — موتور تخصیص
            'broadcast_seconds_left' => $this->broadcastSecondsLeft(),
            'broadcast_attempts' => (int) $this->broadcast_attempts,
            'queued_at_fa' => $this->queued_at ? fa_date($this->queued_at, 'Y/m/d H:i') : null,

            'estimated_time_label' => $this->service?->estimated_time
                ? 'حدود '.fa_digits((string) $this->service->estimated_time).' دقیقه'
                : null,

            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'description' => $this->service?->description,
                'icon' => $this->service?->category?->icon ?: '📄',
                'category_name' => $this->service?->category?->name,
            ]),

            'coffeenet' => $this->whenLoaded('coffeenet', fn () => $this->coffeenet ? [
                'id' => $this->coffeenet->id,
                'name' => $this->coffeenet->name,
            ] : null),

            'operator' => $this->whenLoaded('operator', fn () => $this->operator ? [
                'id' => $this->operator->id,
                'name' => trim(($this->operator->name ?? '').' '.($this->operator->family ?? '')) ?: ($this->operator->name),
            ] : null),

            'form_data' => $this->form_data,

            'form_data_display' => $this->whenLoaded(
                'serviceVersion',
                fn () => collect($this->form_data ?? [])->map(function ($value, $key) {
                    $label = $key;

                    foreach ($this->serviceVersion?->snapshot['form_fields'] ?? [] as $field) {
                        if (($field['name'] ?? null) === $key) {
                            $label = $field['label'] ?? $key;
                            break;
                        }
                    }

                    return [
                        'label' => $label,
                        'value' => is_array($value) ? implode('، ', $value) : (string) $value,
                    ];
                })->values()
            ),

            'files' => $this->whenLoaded('files', fn () => $this->files->filter(
                fn (OrderFile $f) => $f->file_type === 'document'
            )->map(fn (OrderFile $f) => [
                'id' => $f->id,
                'original_name' => $f->original_name,
                'size_kb' => (int) round((int) $f->size / 1024),
                'is_image' => str_starts_with((string) $f->mime, 'image/'),
                'url' => URL::temporarySignedRoute('files.order', now()->addHours(6), ['file' => $f->id]),
            ])->values()),

            'result_files' => $this->whenLoaded('files', fn () => $this->files->filter(
                fn (OrderFile $f) => $f->file_type === 'result'
            )->map(fn (OrderFile $f) => [
                'id' => $f->id,
                'original_name' => $f->original_name,
                'size_kb' => (int) round((int) $f->size / 1024),
                'url' => URL::temporarySignedRoute('files.order', now()->addHours(6), ['file' => $f->id]),
            ])->values()),

            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'to_status_label' => \App\Enums\OrderStatus::tryFrom((string) $h->to_status)?->label(),
                'note' => $h->note,
                'created_at_fa' => $h->created_at ? fa_date($h->created_at, 'Y/m/d H:i') : null,
            ])->values()),

            'payments' => $this->whenLoaded('payments', fn () => PaymentResource::collection($this->payments)),

            // نظرسنجی مشتری (پس از تحویل/تکمیل)
            'rating' => $this->whenLoaded('rating', fn () => $this->rating ? [
                'rating' => (int) $this->rating->rating,
                'comment' => $this->rating->comment,
                'rated_at_fa' => $this->rating->rated_at ? fa_date($this->rating->rated_at, 'Y/m/d H:i') : null,
            ] : null),
        ];
    }
}
