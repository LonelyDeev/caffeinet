<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed های فاز ۱ — پایه داده و دسترسی‌ها
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            GeoSeeder::class,
            SettingsSeeder::class,
            FinanceDefaultsSeeder::class,
            SuperAdminSeeder::class,
            SmsTemplatesSeeder::class,
            GuideSeeder::class,
            ServiceCatalogSeeder::class,
        ]);
    }
}
