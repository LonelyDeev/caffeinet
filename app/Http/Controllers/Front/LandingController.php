<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Settings\WorkingHoursService;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    /**
     * صفحه فرود عمومی — تک‌صفحه معرفی برای مشتریان
     * (خدمات واقعی از دیتابیس + آمار زنده + وضعیت ساعت کاری)
     */
    public function index(WorkingHoursService $workHours): View
    {
        /* ---------- خدمات و دسته‌بندی‌ها (داده واقعی) ---------- */
        $categories = ServiceCategory::query()
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->whereNull('parent_id')
            ->orderBy('sort')
            ->get();

        // خدمات قابل نمایش: فعال + غیر منقضی‌وضعیت
        $services = Service::query()
            ->where('is_active', true)
            ->with('category')
            ->orderByDesc('is_featured')
            ->orderBy('sort')
            ->get()
            ->filter(fn (Service $s) => $s->availabilityState() !== 'inactive')
            ->values();

        // گروه‌بندی خدمات بر اساس دستهٔ (خودِ دسته یا والدش)
        $grouped = $services
            ->groupBy(fn (Service $s) => $s->category?->parent_id ?: $s->category_id)
            ->map(fn ($group, $rootId) => [
                'category' => $categories->firstWhere('id', $rootId),
                'services' => $group->take(6)->values(),
                'total'    => $group->count(),
            ])
            ->filter(fn ($g) => $g['category'] !== null)
            ->sortBy(fn ($g) => $g['category']->sort ?? 999) // v21: ترتیب اهمیت دسته‌ها
            ->values();

        // v21 — لندینگ فقط ۸ دستهٔ برتر را نمایش می‌دهد (بقیه در اپ)
        $landingGroups = $grouped->take(8)->values();

        // v25 — بخش خدمات لندینگ فقط «دسته‌بندی‌ها» را نشان می‌دهد (کارت خدمات‌ها
        // در موبایل اسکرول زیادی می‌گرفت)؛ فهرست کامل خدمت‌ها داخل اپ است.
        $landingCategories = $grouped
            ->map(fn ($g) => [
                'category' => $g['category'],
                'total'    => $g['total'],
            ])
            ->values();

        /* ---------- آمار زندهٔ پلتفرم ---------- */
        $stats = [
            'coffeenets' => Coffeenet::count(),
            'services'   => $services->count(),
            'orders'     => Order::count(),
            'customers'  => User::role('customer')->count(),
            'categories' => $categories->count() + $categories->flatMap->children->count(),
        ];

        /* ---------- وضعیت لحظه‌ای ساعت کاری ---------- */
        $wh = $workHours->status();
        $workStatus = [
            'enabled'  => $wh['enabled'],
            'open'     => $wh['open'],
            'today'    => $wh['day_today_open'],
            'range'    => fa_digits($wh['start']).' تا '.fa_digits($wh['end']),
            'message'  => $wh['message'],
        ];

        return view('front.landing', compact('grouped', 'landingGroups', 'landingCategories', 'stats', 'workStatus'));
    }
}
