<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Services\Deletion\EntityDeleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * حذف نرم/دائم مشترک بخش‌های پنل مدیریت کل (v28).
 *
 *  GET    /admin/{section}/trashed          لیست حذف‌شده‌ها
 *  GET    /admin/{section}/{id}/delete-info خلاصهٔ وابستگی‌ها (پیام تأیید)
 *  DELETE /admin/{section}/{id}            حذف نرم → حذف‌شده‌ها
 *  POST   /admin/{section}/{id}/restore    بازگردانی
 *  DELETE /admin/{section}/{id}/purge      حذف دائم + فایل‌ها
 *
 * «section» از نام روت استخراج می‌شود (admin.orders.delete-info → orders)
 * — همان منطق AdminSectionAccess؛ دسترسی همان مجوز بخش خودش است.
 */
class TrashController extends Controller
{
    public function __construct(protected EntityDeleteService $trash) {}

    /** بخش از نام روت: admin.customers.delete-info → customers */
    protected function sectionOf(Request $request): string
    {
        $name = (string) $request->route()?->getName();
        $rest = str_starts_with($name, 'admin.') ? substr($name, strlen('admin.')) : $name;

        return strtok($rest, '.') ?: '';
    }

    public function trashed(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->trash->trashed($this->sectionOf($request), trim((string) $request->query('q')) ?: null),
        ]);
    }

    public function info(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'data' => $this->trash->info($this->sectionOf($request), (int) $id, $request->user()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return response()->json(
            $this->trash->softDelete($this->sectionOf($request), (int) $id, $request->user())
        );
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        return response()->json(
            $this->trash->restore($this->sectionOf($request), (int) $id, $request->user())
        );
    }

    public function purge(Request $request, string $id): JsonResponse
    {
        return response()->json(
            $this->trash->purge($this->sectionOf($request), (int) $id, $request->user())
        );
    }
}
