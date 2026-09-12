<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\OrdersController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TicketsController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسیرهای API مشتری (نسخه ۱) — فاز ۵
|--------------------------------------------------------------------------
|
| جریان: OTP → تکمیل پروفایل → کاتالوگ → سفارش → پرداخت → تاریخچه.
| احراز هویت با Sanctum (توکن Bearer). اپ موبایل و اپ وب مشتری
| هر دو از همین اندپوینت‌ها استفاده می‌کنند.
|
*/

Route::prefix('v1')->name('api.')->group(function () {

    Route::get('health', fn () => response()->json([
        'ok' => true,
        'service' => 'کافی‌نت آنلاین',
        'version' => 'v1',
    ]))->name('health');

    /* ---------- احراز هویت (عمومی) ---------- */

    Route::post('otp/request', [AuthController::class, 'otpRequest'])
        ->middleware('throttle:otp-request')
        ->name('otp.request');

    Route::post('otp/verify', [AuthController::class, 'otpVerify'])
        ->middleware('throttle:15,1')
        ->name('otp.verify');

    /* ---------- جغرافیا (عمومی) ---------- */

    Route::prefix('geo')->name('geo.')->group(function () {
        Route::get('provinces', [GeoController::class, 'provinces'])->name('provinces');
        Route::get('cities/{province}', [GeoController::class, 'cities'])->name('cities');
    });

    /* ---------- ساعت کاری (عمومی — فاز ۱۵) ---------- */

    Route::get('work-hours', [\App\Http\Controllers\Api\V1\WorkHoursController::class, 'status'])
        ->name('work-hours.status');

    /* ---------- تشخیص VPN فعال (عمومی — v19) ----------
     * پنل‌ها قبل از هر چیز این اندپوینت را صدا می‌زنند؛ اگر آی‌پی
     * کاربر خارج از ایران باشد مودال «VPN را خاموش کنید» نمایش می‌دهند. */

    Route::get('vpn-status', [\App\Http\Controllers\Api\V1\VpnStatusController::class, 'status'])
        ->middleware('throttle:60,1')
        ->name('vpn.status');

    /* ---------- فضای احرازشده مشتری ---------- */

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        /* پروفایل */
        Route::get('me', [ProfileController::class, 'me'])->name('me');
        Route::post('profile/complete', [ProfileController::class, 'complete'])->name('profile.complete');

        /* کاتالوگ */
        Route::get('categories/tree', [CatalogController::class, 'categoriesTree'])->name('categories.tree');
        Route::get('services', [CatalogController::class, 'services'])->name('services.index');
        Route::get('services-grouped', [CatalogController::class, 'servicesGrouped'])->name('services.grouped');
        Route::get('services/{service}', [CatalogController::class, 'show'])
            ->whereNumber('service')->name('services.show');

        /* سفارش‌ها */
        Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');
        Route::post('orders', [OrdersController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [OrdersController::class, 'show'])
            ->whereNumber('order')->name('orders.show');
        Route::post('orders/{order}/pay', [OrdersController::class, 'pay'])
            ->whereNumber('order')->name('orders.pay');
        Route::post('orders/{order}/cancel', [OrdersController::class, 'cancel'])
            ->whereNumber('order')->name('orders.cancel');
        Route::post('orders/{order}/rating', [OrdersController::class, 'rate'])
            ->whereNumber('order')->name('orders.rating');

        /* گفتگوی سفارش (فاز ۷ — چت تلگرام‌گونه) */
        Route::get('orders/{order}/messages', [ChatController::class, 'index'])
            ->whereNumber('order')->name('orders.messages');
        Route::post('orders/{order}/messages', [ChatController::class, 'store'])
            ->whereNumber('order')->name('orders.messages.store');

        /* کیف پول */
        Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
        Route::post('wallet/charge', [WalletController::class, 'charge'])
            ->middleware('throttle:10,1')->name('wallet.charge');

        /* تیکت‌های پشتیبانی (فاز ۱۰) */
        Route::get('tickets', [TicketsController::class, 'index'])->name('tickets.index');
        Route::post('tickets', [TicketsController::class, 'store'])
            ->middleware('throttle:10,1')->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketsController::class, 'show'])
            ->whereNumber('ticket')->name('tickets.show');
        Route::post('tickets/{ticket}/messages', [TicketsController::class, 'reply'])
            ->whereNumber('ticket')->middleware('throttle:20,1')->name('tickets.reply');
        Route::post('tickets/{ticket}/close', [TicketsController::class, 'close'])
            ->whereNumber('ticket')->name('tickets.close');

        /* اعلان‌های درون‌برنامه‌ای (فاز ۱۰) */
        Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
        Route::get('notifications/badge', [NotificationsController::class, 'badge'])->name('notifications.badge');
        Route::post('notifications/read', [NotificationsController::class, 'read'])->name('notifications.read');

        /* نوتیف دستگاه (Web Push / FCM) — v25 */
        Route::post('push/token', [\App\Http\Controllers\Api\V1\PushTokenController::class, 'store'])
            ->middleware('throttle:10,1')->name('push.token');
        Route::delete('push/token', [\App\Http\Controllers\Api\V1\PushTokenController::class, 'destroy'])
            ->middleware('throttle:10,1')->name('push.token.destroy');

        /* اطلاعیه‌های سامانه (فاز ۱۵ — مودال متن/تصویر/ویدیو) */
        Route::get('announcements', [\App\Http\Controllers\Api\V1\AnnouncementsController::class, 'index'])
            ->name('announcements.index');
        Route::post('announcements/{announcement}/read', [\App\Http\Controllers\Api\V1\AnnouncementsController::class, 'read'])
            ->whereNumber('announcement')->name('announcements.read');

        /* Realtime پوشر (فاز ۱۳) — پیکربندی کلاینت برای کاربر جاری */
        Route::get('realtime/config', [\App\Http\Controllers\Api\V1\RealtimeController::class, 'config'])
            ->name('realtime.config');
    });
});
