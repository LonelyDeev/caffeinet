<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * مدیر کل پیش‌فرض — بعد از نصب حتماً رمز را تغییر دهید!
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@caffeinet.ir';

        if (User::where('email', $email)->exists()) {
            return;
        }

        $user = User::create([
            'name' => 'مدیر',
            'family' => 'کل سیستم',
            'email' => $email,
            'password' => Hash::make('Admin@1234'),
            'is_active' => true,
            'profile_completed' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('super_admin');
    }
}
