<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number', 'customer_id', 'service_id', 'service_version_id',
        'status', 'coffeenet_id', 'operator_id', 'form_data',
        'price', 'expenses', 'commissionable_amount',
        'cancel_reason', 'cancelled_by', 'paid_at', 'accepted_at',
        'broadcast_expires_at', 'broadcast_attempts', 'queued_at',
        'delivered_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'form_data' => 'array',
            'price' => 'decimal:2',
            'expenses' => 'decimal:2',
            'commissionable_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'accepted_at' => 'datetime',
            'broadcast_expires_at' => 'datetime',
            'broadcast_attempts' => 'integer',
            'queued_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceVersion(): BelongsTo
    {
        return $this->belongsTo(ServiceVersion::class);
    }

    public function coffeenet(): BelongsTo
    {
        return $this->belongsTo(Coffeenet::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(OrderFile::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('id');
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(OrderBroadcast::class);
    }

    /** رکورد پخش این سفارش برای یک کافی‌نت مشخص */
    public function broadcastFor(int $coffeenetId): ?OrderBroadcast
    {
        return $this->broadcasts()->where('coffeenet_id', $coffeenetId)->first();
    }

    /** ثانیه‌های باقی‌مانده مهلت پخش (۰ اگر منقضی/نامعتبر) */
    public function broadcastSecondsLeft(): int
    {
        if ($this->status !== OrderStatus::Broadcasting || ! $this->broadcast_expires_at) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->broadcast_expires_at, false));
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** پرداخت‌های تسویهٔ کمیسیون (فاز ۸) */
    public function commissionPayouts(): HasMany
    {
        return $this->hasMany(CommissionPayout::class);
    }

    /** نظرسنجی مشتری (امتیاز پس از تحویل/تکمیل) */
    public function rating()
    {
        return $this->hasOne(OrderRating::class);
    }
}
