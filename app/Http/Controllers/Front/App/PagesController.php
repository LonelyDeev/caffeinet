<?php

namespace App\Http\Controllers\Front\App;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * صفحات اپ مشتری (وب) — پوسته‌های Blade؛ داده‌ها از API v1 با jQuery.
 */
class PagesController extends Controller
{
    /** GET /app — ورودی */
    public function index()
    {
        return redirect()->route('app.home');
    }

    /** GET /app/auth — ورود با OTP */
    public function auth(): View
    {
        return view('app.auth');
    }

    /** GET /app/home — کاتالوگ خدمات */
    public function home(): View
    {
        return view('app.home');
    }

    /** GET /app/services — همهٔ خدمات با دسته‌بندی‌ها (گروهی/سکشنی) */
    public function services(): View
    {
        return view('app.services');
    }

    /** GET /app/service/{service} — جزئیات + فرم سفارش */
    public function service(int $service): View
    {
        return view('app.service', ['serviceId' => $service]);
    }

    /** GET /app/orders — سفارش‌های من */
    public function orders(): View
    {
        return view('app.orders');
    }

    /** GET /app/orders/{order} — جزئیات سفارش */
    public function orderShow(int $order): View
    {
        return view('app.order-detail', ['orderId' => $order]);
    }

    /** GET /app/wallet — کیف پول */
    public function wallet(): View
    {
        return view('app.wallet');
    }

    /** GET /app/profile — پروفایل (نمای کاربر) */
    public function profile(): View
    {
        return view('app.profile');
    }

    /** GET /app/profile/edit — ویرایش اطلاعات شخصی (v24 — جدا از نمای پروفایل) */
    public function profileEdit(): View
    {
        return view('app.profile-edit');
    }

    /** GET /app/support — تیکت‌های پشتیبانی (فاز ۱۰) */
    public function support(): View
    {
        return view('app.support');
    }

    /** GET /app/support/{ticket} — گفتگوی تیکت (فاز ۱۰) */
    public function supportShow(int $ticket): View
    {
        return view('app.support-detail', ['ticketId' => $ticket]);
    }
}
