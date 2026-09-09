<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Sms\CustomerSmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * سرویس کیف پول — عملیات بستانکار/بدهکار دفتری با قفل اتمیک.
 *
 * همه تراکنش‌ها immutable هستند: مبلغ + balance_after در لحظه ثبت ذخیره می‌شود
 * و تراز کیف پول همیشه با آخرین تراکنش منطبق است.
 */
class WalletService
{
    public function __construct(
        protected CustomerSmsService $customerSms,
    ) {}

    /**
     * افزایش موجودی (بستانکار)
     *
     * @param  Model  $holder  مدل دارنده (User | Organization | Coffeenet)
     */
    public function credit(
        Model $holder,
        float $amount,
        ?string $refType = null,
        ?int $refId = null,
        ?string $description = null,
        array $meta = [],
    ): Transaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('مبلغ بستانکار باید بزرگ‌تر از صفر باشد.');
        }

        $transaction = DB::transaction(function () use ($holder, $amount, $refType, $refId, $description, $meta) {
            /** @var Wallet $wallet */
            $wallet = $this->lockedWallet($holder, true);

            $balanceAfter = (float) $wallet->balance + $amount;
            $wallet->forceFill(['balance' => $balanceAfter])->save();

            return Transaction::create([
                'wallet_id' => $wallet->id,
                'type' => TransactionType::Credit,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'description' => $description,
                'meta' => $meta ?: null,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });

        // پیامک واریز به کیف پولِ «مشتری» (درخواست بازخوردی ۶-۶ — fail-safe)
        // فقط کیف پول کاربر حقیقی؛ کیف کافی‌نت/سازمان پیامک مشتری ندارد
        if ($holder instanceof User && $holder->mobile) {
            try {
                $this->customerSms->walletCharged($holder, $transaction);
            } catch (\Throwable) {
                // پیامک تراکنش را نمی‌شکند
            }
        }

        return $transaction;
    }

    /**
     * کاهش موجودی (بدهکار) — در صورت ناکافی بودن موجودی استثنا می‌دهد.
     *
     * @param  Model  $holder  مدل دارنده
     *
     * @throws WalletException
     */
    public function debit(
        Model $holder,
        float $amount,
        ?string $refType = null,
        ?int $refId = null,
        ?string $description = null,
        array $meta = [],
    ): Transaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('مبلغ بدهکار باید بزرگ‌تر از صفر باشد.');
        }

        return DB::transaction(function () use ($holder, $amount, $refType, $refId, $description, $meta) {
            /** @var Wallet $wallet */
            $wallet = $this->lockedWallet($holder, false);

            if (! $wallet || (float) $wallet->balance < $amount) {
                throw new WalletException('موجودی کیف پول برای این عملیات کافی نیست.');
            }

            $balanceAfter = (float) $wallet->balance - $amount;
            $wallet->forceFill(['balance' => $balanceAfter])->save();

            return Transaction::create([
                'wallet_id' => $wallet->id,
                'type' => TransactionType::Debit,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'description' => $description,
                'meta' => $meta ?: null,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    /** موجودی فعلی دارنده (بدون ساخت کیف پول) */
    public function balance(Model $holder): float
    {
        $wallet = Wallet::query()
            ->where('holder_type', $holder::class)
            ->where('holder_id', $holder->getKey())
            ->first();

        return $wallet ? (float) $wallet->balance : 0.0;
    }

    /** کیف پولِ قفل‌شده برای آپدیت اتمیک (در صورت نیاز ساخته می‌شود) */
    protected function lockedWallet(Model $holder, bool $create): ?Wallet
    {
        again:
        $wallet = Wallet::query()
            ->where('holder_type', $holder::class)
            ->where('holder_id', $holder->getKey())
            ->lockForUpdate()
            ->first();

        if (! $wallet && $create) {
            Wallet::firstOrCreate(
                ['holder_type' => $holder::class, 'holder_id' => $holder->getKey()],
                ['balance' => 0],
            );
            goto again; // رفرش با قفل مجدد
        }

        return $wallet;
    }
}
