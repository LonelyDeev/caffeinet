<?php

namespace App\Services\Finance;

use App\Enums\OrderStatus;
use App\Enums\SalaryType;
use App\Models\CommissionPayout;
use App\Models\CommissionSetting;
use App\Models\Coffeenet;
use App\Models\Organization;
use App\Models\Order;
use App\Models\ReferralSetting;
use App\Models\SalarySetting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Sms\CustomerSmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * موتور تسویهٔ کمیسیون (فاز ۸)
 *
 * جریان: سفارش تحویل‌شده (Delivered/Completed) + پرداخت‌شده →
 * محاسبهٔ سهم‌ها فقط روی «مبالغ مشمول کمیسیون» (commissionable_amount) طبق قاعدهٔ
 * کمیسیون (اختصاصی خدمت یا سراسری) → افزایش کیف پول‌ها با تراکنش دفتری + ثبت
 * commission_payouts با snapshot قواعد — همه در «یک» تراکنش دیتابیس.
 *
 * قواعد تقسیم:
 *  - سهم پلتفرم (درصد یا مبلغ ثابت)
 *  - سهم سازمان — فقط وقتی کافی‌نت زیرمجموعهٔ سازمان است
 *  - پاداش معرفی هر سفارش (referral) — درصدی از سهم سازمان یا مبلغ ثابت (تنظیمات پاداش معرفی)
 *  - سهم ناخالص کافی‌نت
 *  - درآمد اپراتور از دل سهم کافی‌نت طبق salary_settings:
 *      percent → درصدی از مبلغ مشمول | fixed_per_order → مبلغ ثابت | monthly → فقط لاگ ماهانه (بدون تسویه درجا)
 *  - باقیمانده (اگر جمع سهم‌ها < ۱۰۰٪) نزد پلتفرم می‌ماند
 *  - idempotent: اگر سفارش قبلاً تسویه شده، دوباره پرداخت نمی‌کند
 */
class SettlementService
{
    public function __construct(
        protected WalletService $wallets,
        protected NotificationService $notifications,
        protected CustomerSmsService $customerSms,
    ) {
    }

    /**
     * تسویهٔ یک سفارش موفق — idempotent و امن برای فراخوانی مجدد.
     *
     * @return array{settled: bool, reason?: string, order_id?: int, base?: float, shares?: array, payouts?: array}
     */
    public function settle(Order $order): array
    {
        // --- گاردها (idempotency و پیش‌شرط‌ها) ---
        if (CommissionPayout::query()->where('order_id', $order->id)->exists()) {
            return ['settled' => false, 'reason' => 'already_settled', 'order_id' => $order->id];
        }

        if (! in_array($order->status, [OrderStatus::Delivered, OrderStatus::Completed], true)) {
            return ['settled' => false, 'reason' => 'not_delivered', 'order_id' => $order->id];
        }

        if (! $order->paid_at) {
            return ['settled' => false, 'reason' => 'unpaid', 'order_id' => $order->id];
        }

        $base = round((float) $order->commissionable_amount, 2);
        if ($base <= 0) {
            return ['settled' => false, 'reason' => 'no_commissionable', 'order_id' => $order->id];
        }

        $rule = CommissionSetting::query()
            ->where('is_active', true)
            ->where(function ($q) use ($order) {
                $q->where(function ($sq) use ($order) {
                    $sq->where('scope', 'service')->where('service_id', $order->service_id);
                })->orWhere(function ($sq) {
                    $sq->where('scope', 'global')->whereNull('service_id');
                });
            })
            ->orderByRaw("CASE WHEN scope = 'service' THEN 0 ELSE 1 END")
            ->first();

        if (! $rule) {
            return ['settled' => false, 'reason' => 'no_rule', 'order_id' => $order->id];
        }

        try {
            return $this->runSettlement($order, $rule, $base);
        } catch (Throwable $e) {
            report($e);

            return ['settled' => false, 'reason' => 'error: '.$e->getMessage(), 'order_id' => $order->id];
        }
    }

    /** محاسبهٔ سهم طبق نوع/مقدار قاعده روی مبلغ پایه */
    protected function shareAmount(?string $type, ?string $value, float $base): float
    {
        if (! $type || ! $value) {
            return 0.0;
        }

        $amount = $type === 'fixed'
            ? (float) $value
            : $base * ((float) $value / 100);

        return round(max(0.0, min($amount, $base)), 2);
    }

    /** حساب سیستمی پلتفرم — کیف پول سهم پلتفرم (بدون نقش، بدون ورود) */
    public function platformUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'platform@caffeinet.ir'],
            [
                'name' => 'حساب',
                'family' => 'پلتفرم',
                'password' => Hash::make(Str::random(40)),
                'is_active' => false,
            ],
        );
    }

    protected function runSettlement(Order $order, CommissionSetting $rule, float $base): array
    {
        /** @var Coffeenet|null $coffeenet */
        $coffeenet = $order->coffeenet()->first();
        /** @var User|null $operator */
        $operator = $order->operator()->first();
        /** @var Organization|null $organization */
        $organization = $coffeenet?->organization()->first();

        // --- سهم‌های ناخالص طبق قاعده ---
        $platform = $this->shareAmount($rule->platform_type, $rule->platform_value, $base);
        $orgShare = $organization
            ? $this->shareAmount($rule->organization_type, $rule->organization_value, $base)
            : 0.0;
        $coffeenetGross = $this->shareAmount($rule->coffeenet_type, $rule->coffeenet_value, $base);

        // --- پاداش معرفی هر سفارش (v11): درصدی از سهم سازمان یا مبلغ ثابت ---
        // فقط وقتی کافی‌نت توسط سازمان معرفی شده (organization_id) و سیستم پاداش فعال باشد.
        [$referralBonus, $referralInfo] = $this->referralBonus($organization, $orgShare, $base);

        // --- گارد دفاعی: جمع سهم‌های ناخالص هرگز بیشتر از پایه نشود ---
        $clamped = false;
        if (round($platform + $orgShare + $coffeenetGross, 2) > $base) {
            $coffeenetGross = round(max(0.0, $base - $platform - $orgShare), 2);
            $clamped = true;
        }

        // --- باقیمانده نزد پلتفرم ---
        $residual = round($base - $platform - $orgShare - $coffeenetGross, 2);
        if ($residual > 0) {
            $platform = round($platform + $residual, 2);
        }

        // --- درآمد اپراتور (از دل سهم کافی‌نت) ---
        $operatorIncome = 0.0;
        $salaryInfo = null;

        if ($operator && $coffeenet) {
            /** @var SalarySetting|null $salary */
            $salary = SalarySetting::query()
                ->where('coffeenet_id', $coffeenet->id)
                ->where('user_id', $operator->id)
                ->where('is_active', true)
                ->first();

            if ($salary) {
                $salaryInfo = [
                    'type' => $salary->type->value,
                    'type_label' => $salary->type->label(),
                    'rate' => (float) $salary->rate,
                ];

                $operatorIncome = match ($salary->type) {
                    SalaryType::Percent => round($base * ((float) $salary->rate / 100), 2),
                    SalaryType::FixedPerOrder => round((float) $salary->rate, 2),
                    SalaryType::Monthly => 0.0, // ماهیانه: فقط لاگ ماهانهٔ مدیر کافی‌نت
                };
            }
        }

        // سقف درآمد اپراتور = سهم ناخالص کافی‌نت
        $operatorIncome = round(min($operatorIncome, $coffeenetGross), 2);
        $coffeenetNet = round($coffeenetGross - $operatorIncome, 2);

        $shares = [
            'base' => $base,
            'platform' => $platform,
            'organization' => $orgShare,
            'referral_bonus' => $referralBonus,
            'coffeenet_gross' => $coffeenetGross,
            'coffeenet_net' => $coffeenetNet,
            'operator' => $operatorIncome,
            'residual_to_platform' => max(0.0, $residual),
            'clamped' => $clamped,
        ];

        $platformUser = $this->platformUser();

        $rows = [
            [
                'role' => 'platform',
                'holder' => $platformUser,
                'amount' => $platform,
                'desc' => 'سهم پلتفرم — تسویهٔ سفارش '.$order->order_number,
            ],
        ];

        if ($organization && $orgShare > 0) {
            $rows[] = [
                'role' => 'organization',
                'holder' => $organization,
                'amount' => $orgShare,
                'desc' => 'سهم سازمان «'.$organization->name.'» — تسویهٔ سفارش '.$order->order_number,
            ];
        }

        if ($organization && $referralBonus > 0) {
            $rows[] = [
                'role' => 'referral',
                'holder' => $organization,
                'amount' => $referralBonus,
                'ref' => 'reward',
                'desc' => 'پاداش معرفی — سفارش '.$order->order_number.' (کافی‌نت «'.$coffeenet?->name.'»)',
            ];
        }

        if ($coffeenet && $coffeenetNet > 0) {
            $rows[] = [
                'role' => 'coffeenet',
                'holder' => $coffeenet,
                'amount' => $coffeenetNet,
                'desc' => 'سهم کافی‌نت «'.$coffeenet->name.'» — تسویهٔ سفارش '.$order->order_number,
            ];
        }

        if ($operator && $operatorIncome > 0) {
            $rows[] = [
                'role' => 'operator',
                'holder' => $operator,
                'amount' => $operatorIncome,
                'desc' => 'درآمد اپراتور «'.trim(($operator->name ?? '').' '.($operator->family ?? '')).'» — تسویهٔ سفارش '.$order->order_number,
            ];
        }

        $snapshot = [
            'settled_at' => now()->format('Y-m-d H:i:s'),
            'order_number' => $order->order_number,
            'order_status' => $order->status->value,
            'base_commissionable' => $base,
            'order_total' => (float) $order->price + (float) $order->expenses,
            'rule' => [
                'id' => $rule->id,
                'scope' => $rule->scope,
                'service_id' => $rule->service_id,
                'platform' => [$rule->platform_type, (float) $rule->platform_value],
                'organization' => [$rule->organization_type, (float) ($rule->organization_value ?? 0)],
                'coffeenet' => [$rule->coffeenet_type, (float) ($rule->coffeenet_value ?? 0)],
            ],
            'coffeenet' => $coffeenet ? ['id' => $coffeenet->id, 'name' => $coffeenet->name] : null,
            'organization' => $organization ? ['id' => $organization->id, 'name' => $organization->name] : null,
            'referral' => $referralInfo,
            'operator' => $operator ? ['id' => $operator->id, 'name' => trim(($operator->name ?? '').' '.($operator->family ?? ''))] : null,
            'salary' => $salaryInfo,
            'shares' => $shares,
        ];

        $payoutIds = DB::transaction(function () use ($rows, $order, $snapshot) {
            $ids = [];

            foreach ($rows as $row) {
                if ($row['amount'] <= 0) {
                    continue;
                }

                /** @var Wallet $wallet */
                $wallet = Wallet::for($row['holder']);

                $payout = CommissionPayout::create([
                    'order_id' => $order->id,
                    'wallet_id' => $wallet->id,
                    'role' => $row['role'],
                    'holder_type' => $row['holder']::class,
                    'holder_id' => $row['holder']->getKey(),
                    'amount' => $row['amount'],
                    'snapshot' => $snapshot,
                    'created_at' => now(),
                ]);

                $this->wallets->credit(
                    $row['holder'],
                    (float) $row['amount'],
                    $row['ref'] ?? 'order',
                    $order->id,
                    $row['desc'],
                    [
                        'payout_id' => $payout->id,
                        'order_number' => $order->order_number,
                        'role' => $row['role'],
                    ],
                );

                $ids[] = $payout->id;
            }

            AuditLogger::log(
                'orders.settled',
                $order,
                [],
                ['payouts' => count($ids), 'base' => $snapshot['base_commissionable']],
                'تسویهٔ کمیسیون سفارش '.$order->order_number.' — '.count($ids).' پرداخت کیف پولی',
            );

            return $ids;
        });

        // اعلان‌های کیف پول به ذی‌نفعان (فاز ۱۰ — هرگز تسویه را نمی‌شکند)
        if ($coffeenet && $coffeenetNet > 0) {
            $this->notifications->notifyCoffeenetManagers(
                (int) $coffeenet->id,
                'settlement',
                'واریز کمیسیون به کیف پول',
                'سفارش «'.$order->order_number.'» تحویل شد و سهم کافی‌نت ('.fa_money($coffeenetNet).') به کیف پول شما واریز شد.',
                ['url' => '/coffeenet/'.$coffeenet->id.'/wallet', 'ref' => ['order_number' => $order->order_number]],
            );
        }

        if ($operator && $operatorIncome > 0) {
            $this->notifications->notifyOperator(
                (int) $operator->id,
                'settlement',
                'درآمد جدید',
                'سفارش «'.$order->order_number.'» تحویل شد و درآمد شما ('.fa_money($operatorIncome).') به کیف پول شما واریز شد.',
                ['url' => '/operator/earnings', 'ref' => ['order_number' => $order->order_number]],
            );
        }

        if ($organization && ($orgShare > 0 || $referralBonus > 0)) {
            $parts = [];
            if ($orgShare > 0) {
                $parts[] = 'سهم سازمان ('.fa_money($orgShare).')';
            }
            if ($referralBonus > 0) {
                $parts[] = 'پاداش معرفی ('.fa_money($referralBonus).')';
            }
            $this->notifications->notifyOrgOwner(
                (int) $organization->id,
                'settlement',
                'واریز سهم سازمان',
                'سفارش «'.$order->order_number.'» تحویل شد و '.implode(' و ', $parts).' به کیف پول شما واریز شد.',
                ['url' => '/organization/wallet', 'ref' => ['order_number' => $order->order_number]],
            );
        }

        // پیامک تکمیل/تسویه به مشتری (درخواست بازخوردی ۶-۶ — fail-safe)
        try {
            $this->customerSms->orderCompleted($order);
        } catch (Throwable) {
            // پیامک تسویه را نمی‌شکند
        }

        return [
            'settled' => true,
            'order_id' => $order->id,
            'base' => $base,
            'shares' => $shares,
            'payouts' => $payoutIds,
        ];
    }

    /** آیا سفارش تسویه شده؟ */
    public function isSettled(Order $order): bool
    {
        return CommissionPayout::query()->where('order_id', $order->id)->exists();
    }

    /**
     * پاداش معرفی هر سفارش (v11): درصدی از سهم سازمان یا مبلغ ثابت.
     *
     * @return array{0: float, 1: array|null} [مبلغ پاداش، snapshot قاعده]
     */
    protected function referralBonus(?Organization $organization, float $orgShare, float $base): array
    {
        if (! $organization || $orgShare < 0) {
            return [0.0, null];
        }

        $referral = ReferralSetting::current();

        if (! $referral->is_active || (float) $referral->per_order_value <= 0) {
            return [0.0, null];
        }

        $type = $referral->per_order_type ?: 'percent';
        $value = (float) $referral->per_order_value;

        $raw = $type === 'fixed' ? $value : $orgShare * ($value / 100);

        // سقف: درصدی → سهم سازمان؛ ثابت → مبلغ مشمول کمیسیون سفارش
        $cap = $type === 'fixed' ? $base : $orgShare;

        return [round(max(0.0, min($raw, $cap)), 2), [
            'type' => $type,
            'value' => $value,
            'mode' => 'per_order',
            'org_share' => $orgShare,
        ]];
    }
}
