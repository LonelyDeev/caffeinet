<?php

namespace Database\Seeders;

use App\Models\CommissionSetting;
use App\Models\ReferralSetting;
use Illuminate\Database\Seeder;

/**
 * قواعد مالی پیش‌فرض — قابل ویرایش از پنل در فاز ۸
 */
class FinanceDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        // قاعده سراسری کمیسیون: پلتفرم ۱۰٪، سازمان ۵٪، کافی‌نت ۸۰٪ (قابل تنظیم)
        CommissionSetting::firstOrCreate(
            ['scope' => 'global'],
            [
                'platform_type' => 'percent',
                'platform_value' => 10,
                'organization_type' => 'percent',
                'organization_value' => 5,
                'coffeenet_type' => 'percent',
                'coffeenet_value' => 80,
                'is_active' => true,
            ],
        );

        // پاداش معرفی: اولیه صفر (طبق نیاز مالک)، ۲٪ از هر کار
        ReferralSetting::firstOrCreate(
            ['id' => 1],
            [
                'introduction_reward' => 0,
                'per_order_type' => 'percent',
                'per_order_value' => 2,
                'is_active' => true,
            ],
        );
    }
}
