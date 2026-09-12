<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * فاز ۱۱ — مستندات API v1 مشتری.
 *
 * مرجع کامل اندپوینت‌های /api/v1 برای تیم فرانت/اپ موبایل:
 * متد، مسیر، احراز هویت، محدودیت نرخ، ورودی و نمونهٔ پاسخ/خطا.
 * صفحهٔ سروری (Blade) + جستجو/کپی/چاپ — بدون هیچ سرویس بیرونی.
 */
class ApiDocsController extends Controller
{
    public function index(Request $request)
    {
        return view('back.admin.api-docs.index', [
            'groups' => $this->endpoints(),
        ]);
    }

    private function endpoints(): array
    {
        return [
            [
                'id' => 'auth',
                'title' => 'احراز هویت',
                'icon' => 'key',
                'desc' => 'ورود با OTP موبایل و دریافت توکن Sanctum.',
                'items' => [
                    [
                        'method' => 'POST', 'path' => '/api/v1/otp/request', 'auth' => false,
                        'rate' => '۲ در دقیقه هر شماره + ۱۰ در ساعت هر IP',
                        'desc' => 'درخواست کد یکبارمصرف برای ورود/ثبت‌نام.',
                        'body' => ['mobile' => '09123456789'],
                        'response' => [
                            'message' => 'کد تأیید پیامک شد.',
                            'expires_in' => 180,
                            'resend_in' => 90,
                        ],
                        'notes' => 'در محیط local فیلد dev_code هم برگردانده می‌شود (فقط توسعه).',
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/otp/verify', 'auth' => false,
                        'rate' => '۱۵ در دقیقه هر IP',
                        'desc' => 'بررسی کد و صدور توکن. کاربر جدید با profile_completed=false.',
                        'body' => ['mobile' => '09123456789', 'code' => '123456'],
                        'response' => [
                            'token' => '1|xxxxxxxxxxxxxxxxxxxx',
                            'profile_completed' => true,
                            'user' => ['id' => 7, 'mobile' => '۰۹۱۲۳۴۵۶۷۸۹', 'name' => 'سارا', 'family' => 'احمدی'],
                        ],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/logout', 'auth' => true,
                        'rate' => '—',
                        'desc' => 'ابطال توکن جاری.',
                        'response' => ['message' => 'با موفقیت خارج شدید.'],
                    ],
                ],
            ],
            [
                'id' => 'profile',
                'title' => 'پروفایل',
                'icon' => 'user',
                'desc' => 'مشاهده و تکمیل اجباری پروفایل مشتری.',
                'items' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/me', 'auth' => true,
                        'rate' => 'سقف کلی',
                        'desc' => 'اطلاعات کاربر جاری.',
                        'response' => [
                            'id' => 7, 'mobile' => '۰۹۱۲۳۴۵۶۷۸۹', 'name' => 'سارا', 'family' => 'احمدی',
                            'profile_completed' => true,
                            'province' => ['id' => 8, 'name' => 'تهران'],
                            'city' => ['id' => 107, 'name' => 'تهران'],
                        ],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/profile/complete', 'auth' => true,
                        'rate' => 'سقف کلی',
                        'desc' => 'تکمیل اجباری پیش از سفارش (نام، نام‌خانوادگی، جنسیت، استان، شهر، تاریخ تولد شمسی).',
                        'body' => [
                            'name' => 'سارا', 'family' => 'احمدی', 'gender' => 'female',
                            'province_id' => 8, 'city_id' => 107, 'birth_date' => '1370/05/12',
                        ],
                        'response' => ['message' => 'پروفایل تکمیل شد.', 'user' => '…'],
                    ],
                ],
            ],
            [
                'id' => 'geo',
                'title' => 'جغرافیا',
                'icon' => 'map',
                'desc' => 'استان/شهر برای سلکت آبشاری پروفایل. عمومی.',
                'items' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/geo/provinces', 'auth' => false, 'rate' => '—',
                        'desc' => 'فهرست ۳۱ استان.',
                        'response' => ['data' => [['id' => 8, 'name' => 'تهران']]],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/geo/cities/{province}', 'auth' => false, 'rate' => '—',
                        'desc' => 'شهرهای یک استان (شناسه عددی).',
                        'response' => ['data' => [['id' => 107, 'name' => 'تهران', 'province_id' => 8]]],
                    ],
                ],
            ],
            [
                'id' => 'catalog',
                'title' => 'کاتالوگ خدمات',
                'icon' => 'layers',
                'desc' => 'دسته‌بندی‌ها و خدمات قابل سفارش.',
                'items' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/categories/tree', 'auth' => false, 'rate' => '—',
                        'desc' => 'درخت دسته‌بندی با تعداد خدمات فعال.',
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/services?q=&category_id=&sort=', 'auth' => false, 'rate' => '—',
                        'desc' => 'جستجو/فیلتر خدمات ( صفحه‌بندی استاندارد).',
                        'params' => 'q جستجو | category_id دسته | sort=price|duration',
                        'response' => ['data' => [['id' => 2, 'title' => 'گواهی سوءپیشینه', 'price' => 95000]], 'links' => '…', 'meta' => '…'],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/services/{id}', 'auth' => false, 'rate' => '—',
                        'desc' => 'جزئیات خدمت: فرم داینامیک، هزینه‌ها، مدارک لازم.',
                        'response' => ['id' => 2, 'title' => 'گواهی سوءپیشینه', 'form' => '…', 'costs' => '…'],
                    ],
                ],
            ],
            [
                'id' => 'orders',
                'title' => 'سفارش‌ها',
                'icon' => 'orders',
                'desc' => 'ثبت سفارش با فرم داینامیک + مدارک، پرداخت، لغو.',
                'items' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/orders?status=', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'تاریخچهٔ سفارش‌های کاربر (صفحه‌بندی).',
                        'response' => ['data' => [['id' => 13, 'number' => 'CN050615-9441', 'status' => 'delivered']], 'links' => '…'],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/orders', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'ثبت سفارش — multipart: service_id + form_data + files[] (PDF/تصویر/Word تا ۵MB).',
                        'body' => ['service_id' => 2, 'form_data' => ['national_code' => '0012345678'], 'files[]' => '(binary)'],
                        'response' => ['message' => 'سفارش ثبت شد.', 'order' => ['id' => 17, 'number' => 'CN050617-1023', 'status' => 'pending_payment', 'payable' => 95000]],
                        'notes' => 'پس از ثبت، وضعیت pending_payment → پرداخت → broadcasting (پخش ۶۰ ثانیه) → اتصال اپراتور.',
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/orders/{id}', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'جزئیات سفارش: وضعیت زنده، تایمر پخش، لینک‌های پرداخت، مدارک (لینک موقت ۶ ساعته)، تسویه.',
                        'response' => ['id' => 17, 'status' => 'accepted', 'broadcast_remaining' => 42, 'files' => [['url' => '/files/order/9?expires=…&signature=…']]],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/orders/{id}/pay', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'پرداخت — method=wallet (کیف پول) یا online (درگاه).',
                        'body' => ['method' => 'wallet'],
                        'response' => ['message' => 'پرداخت با کیف پول انجام شد.', 'status' => 'paid'],
                        'notes' => 'online: فیلد redirect حاوی آدرس درگاه است.',
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/orders/{id}/cancel', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'لغو تا قبل از پرداخت — دلیل لغو الزامی است (حداقل ۵ نویسه).',
                        'body' => ['reason' => 'تغییر نظر دادم'],
                        'response' => ['message' => 'سفارش لغو شد.'],
                    ],
                ],
            ],
            [
                'id' => 'chat',
                'title' => 'گفتگوی سفارش',
                'icon' => 'chat',
                'desc' => 'چت تلگرام‌گونه با اپراتور (متن/تصویر/صدا/ویدیو/فایل).',
                'items' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/orders/{id}/messages?after_id=', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'پیام‌ها (پولینگ افزایشی با after_id).',
                        'response' => ['data' => [['id' => 55, 'type' => 'text', 'message' => 'سلام', 'mine' => true, 'file_url' => null]]],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/orders/{id}/messages', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'ارسال پیام — JSON {message} یا multipart (type + file تا ۵۰MB ویدیو/۲۵MB صدا).',
                        'body' => ['message' => 'سلام، مدارک ارسال شد؟'],
                        'response' => ['message' => ['id' => 56, 'type' => 'text', 'message' => 'سلام، مدارک ارسال شد؟']],
                    ],
                ],
            ],
            [
                'id' => 'wallet',
                'title' => 'کیف پول',
                'icon' => 'wallet',
                'desc' => 'موجودی و گردش مالی مشتری.',
                'items' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/wallet?type=credit|debit&page=', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'موجودی + تراکنش‌های دفتری (تغییرناپذیر).',
                        'response' => ['balance' => 275000, 'transactions' => ['data' => [['amount' => 30000, 'type' => 'credit', 'ref' => 'سفارش CN050615-9441']]]],
                    ],
                ],
            ],
            [
                'id' => 'tickets',
                'title' => 'تیکت پشتیبانی',
                'icon' => 'tickets',
                'desc' => 'ثبت/پیگیری شکایت و پشتیبانی با پیوست.',
                'items' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/tickets?status=', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'تیکت‌های کاربر (صفحه‌بندی ۱۵تایی).',
                        'response' => ['data' => [['id' => 2, 'number' => 'TK260906-0002', 'status' => 'in_progress']]],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tickets', 'auth' => true, 'rate' => '۱۰ در دقیقه',
                        'desc' => 'ثبت تیکت — multipart: subject + message + order_id? + attachments[] (تا ۱۵MB). نیازمند پروفایل تکمیل (v24).',
                        'body' => ['subject' => 'مشکل در سفارش', 'message' => 'توضیحات…', 'order_id' => 13, 'attachments[]' => '(binary)'],
                        'response' => ['message' => 'تیکت ثبت شد.', 'ticket' => ['id' => 3, 'number' => 'TK260906-0003']],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/tickets/{id}', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'جزئیات + رشتهٔ پیام (یادداشت داخلی کارشناس مخفی است).',
                        'response' => ['ticket' => ['id' => 2, 'status' => 'in_progress'], 'messages' => [['id' => 9, 'message' => 'سلام']]],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tickets/{id}/messages', 'auth' => true, 'rate' => '۲۰ در دقیقه',
                        'desc' => 'پاسخ کاربر — multipart با attachments[]. نیازمند پروفایل تکمیل (v24).',
                        'body' => ['message' => 'ممنون از بررسی'],
                        'response' => ['message' => 'پیام ثبت شد.'],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/tickets/{id}/close', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'بستن تیکت توسط مشتری (با پیام جدید خودکار بازگشایی می‌شود).',
                        'response' => ['message' => 'تیکت بسته شد.'],
                    ],
                ],
            ],
            [
                'id' => 'notifications',
                'title' => 'اعلان‌ها',
                'icon' => 'bell',
                'desc' => 'اعلان in-app: سفارش/پرداخت/تسویه/تیکت/برداشت.',
                'items' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/notifications?unread=1', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'فهرست اعلان‌ها (صفحه‌بندی).',
                        'response' => ['data' => [['id' => 'uuid', 'title' => 'سفارش تحویل شد', 'read_at' => null]]],
                    ],
                    [
                        'method' => 'GET', 'path' => '/api/v1/notifications/badge', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'شمارش خوانده‌نشده (پولینگ سبک).',
                        'response' => ['unread' => 8],
                    ],
                    [
                        'method' => 'POST', 'path' => '/api/v1/notifications/read', 'auth' => true, 'rate' => 'سقف کلی',
                        'desc' => 'علامت‌گذاری خوانده‌شده — بدون id یعنی همه.',
                        'body' => ['id' => 'uuid'],
                        'response' => ['message' => 'خوانده شد.'],
                    ],
                ],
            ],
            [
                'id' => 'push',
                'title' => 'نوتیف دستگاه (Web Push)',
                'icon' => 'bell',
                'desc' => 'ثبت/حذف اشتراک نوتیف دستگاه — برای وقتی که اپ بسته است (v26: سه سرویس پیش‌فرض/پوشر/فایربیس).',
                'items' => [
                    [
                        'method' => 'POST', 'path' => '/api/v1/push/token', 'auth' => true, 'rate' => '۱۰ در دقیقه',
                        'desc' => 'ثبت دستگاه جاری کاربر — provider: firebase (توکن FCM) | webpush (endpoint + کلیدهای اشتراک p256dh/auth — سرویس پیش‌فرض داخلی) | pusher (شناسهٔ دستگاه Beams). platform اختیاری: web|android|ios|windows|other.',
                        'body' => ['token' => 'https://fcm.googleapis.com/fcm/send/eWxhYi…', 'provider' => 'webpush', 'p256dh' => 'BEQ0…', 'auth' => 'Ie-u…', 'platform' => 'android'],
                        'response' => ['ok' => true, 'message' => 'دستگاه برای دریافت نوتیف‌ها ثبت شد.'],
                    ],
                    [
                        'method' => 'DELETE', 'path' => '/api/v1/push/token', 'auth' => true, 'rate' => '۱۰ در دقیقه',
                        'desc' => 'حذف توکن/اشتراک دستگاه (مثلاً هنگام خروج یا خاموش‌کردن نوتیف).',
                        'body' => ['token' => 'eWxhYi…'],
                        'response' => ['ok' => true, 'message' => 'دستگاه حذف شد.'],
                    ],
                ],
            ],
            [
                'id' => 'misc',
                'title' => 'عمومی',
                'icon' => 'grid',
                'desc' => 'بررسی سلامت سرویس.',
                'items' => [
                    [
                        'method' => 'GET', 'path' => '/api/v1/health', 'auth' => false, 'rate' => '—',
                        'desc' => 'پاسخ همیشه 200 — برای مانیتورینگ/بالانس.',
                        'response' => ['ok' => true, 'service' => 'کافی‌نت آنلاین', 'version' => 'v1'],
                    ],
                ],
            ],
        ];
    }
}
