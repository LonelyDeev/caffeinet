<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | فینوتک (v40) — استعلام شاهکار (تطبیق کد ملی و موبایل) و تطبیق کارت
    |----------------------------------------------------------------------
    | اعتبارنامه‌ها (clientId/clientSecret/nid) از تنظیمات پنل خوانده می‌شوند.
    | base_url فقط برای تست/E2E قابل بازنویسی است (پیش‌فرض بر اساس «محیط»
    | انتخاب‌شده در تنظیمات: production یا sandbox).
    */
    'finnotech' => [
        'base_url' => env('FINNOTECH_BASE_URL'), // خالی = خودکار از تنظیمات
        'timeout' => 15,
        'connect_timeout' => 6,
    ],

];
