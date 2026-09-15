<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * v39 — کارت بانکی اپراتور/مدیر کافی‌نت/مدیر سازمان.
 *
 * هر کاربر چند کارت می‌تواند داشته باشد؛ یکی پیش‌فرضِ تسویه است.
 * حداقل یکی از سه شماره (کارت/شبا/حساب) باید پر باشد (کنترلر اعتبارسنجی می‌کند).
 */
class BankCard extends Model
{
    protected $fillable = [
        'user_id', 'card_number', 'sheba_number', 'account_number', 'holder_name', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** نمایش زیبای شماره کارت: ۶۲۱۹-****-****-۹۸۶۱ (۶ رقم اول و ۴ رقم آخر) */
    public function maskedCard(): ?string
    {
        $c = preg_replace('/\D/', '', (string) $this->card_number);
        if (strlen($c) !== 16) {
            return $this->card_number;
        }

        return substr($c, 0, 4).'-'.substr($c, 4, 2).'**-****-'.substr($c, -4);
    }
}
