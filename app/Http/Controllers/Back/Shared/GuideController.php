<?php

namespace App\Http\Controllers\Back\Shared;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * فاز ۱۳ — آموزش استفاده از پنل (مبتنی بر نقش).
 *
 * یک کنترلر مشترک برای ۴ پیشوند؛ نقش آموزش با route defaults تعیین می‌شود:
 *   /admin/guide                    → super_admin
 *   /organization/guide             → organization
 *   /coffeenet/{id}/guide           → coffeenet
 *   /operator/guide                 → operator
 *
 * هر کاربر فقط راهنماهای نقش خودش را می‌بیند.
 */
class GuideController extends Controller
{
    /** نگاشت نقش → چیدمان + برچسب پنل */
    protected const PANELS = [
        'super_admin' => ['layout' => 'back.layouts.panel', 'label' => 'پنل مدیریت کل', 'home' => '/admin'],
        'organization' => ['layout' => 'back.layouts.org', 'label' => 'پنل سازمان', 'home' => '/organization'],
        'coffeenet' => ['layout' => 'back.coffeenet.layouts.panel', 'label' => 'پنل کافی‌نت', 'home' => '/coffeenet'],
        'operator' => ['layout' => 'back.operator.layouts.panel', 'label' => 'پنل اپراتور', 'home' => '/operator/dashboard'],
    ];

    /** لیست راهنماهای نقش جاری */
    public function index(Request $request): View
    {
        $role = (string) $request->route()->parameter('guide_role', 'operator');
        abort_unless(array_key_exists($role, self::PANELS), 404);

        $panel = self::PANELS[$role];

        $guides = Guide::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // مسیر پایهٔ راهنما در همین پنل (مثلاً /admin/guide یا /coffeenet/5/guide)
        $base = rtrim($request->getPathInfo(), '/');

        return view('back.shared.guide.index', [
            'guides' => $guides,
            'guideLayout' => $panel['layout'],
            'guidePanelLabel' => $panel['label'],
            'guideRole' => $role,
            'guideRoleLabel' => Guide::ROLES[$role] ?? $role,
            'guideBase' => $base,
        ]);
    }

    /** محتوای یک راهنما */
    public function show(Request $request): View
    {
        $role = (string) $request->route()->parameter('guide_role', 'operator');
        $slug = (string) $request->route('slug', '');
        abort_unless(array_key_exists($role, self::PANELS), 404);

        $panel = self::PANELS[$role];

        $guide = Guide::query()
            ->where('role', $role)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // راهنمای بعدی (برای ناوبری پایان مطلب)
        $next = Guide::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->where('sort_order', '>=', $guide->sort_order)
            ->whereKeyNot($guide->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        // مسیر پایهٔ راهنما (بدون slug آخر مسیر)
        $base = preg_replace('#/[^/]+$#', '', rtrim($request->getPathInfo(), '/'));

        return view('back.shared.guide.show', [
            'guide' => $guide,
            'nextGuide' => $next,
            'guideLayout' => $panel['layout'],
            'guidePanelLabel' => $panel['label'],
            'guideRole' => $role,
            'guideRoleLabel' => Guide::ROLES[$role] ?? $role,
            'guideBase' => $base,
        ]);
    }
}
