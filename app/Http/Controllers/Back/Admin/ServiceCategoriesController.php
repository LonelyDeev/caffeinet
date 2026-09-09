<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCategoriesController extends Controller
{
    public function __construct(
        protected \App\Services\Catalog\ServiceVersionManager $versions,
    ) {
    }

    public function index(): View
    {
        return view('back.admin.services.categories');
    }

    /** درخت دسته‌بندی‌ها + تعداد خدمات تجمعی (AJAX) */
    public function data(): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->withCount('services')
            ->with('parent:id,name')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $tree = $categories
            ->where('parent_id', null)
            ->map(fn (ServiceCategory $c) => $this->mapNode($c, $categories))
            ->values();

        return response()->json(['categories' => $tree]);
    }

    private function mapNode(ServiceCategory $node, $all): array
    {
        $children = $all->where('parent_id', $node->id)
            ->map(fn (ServiceCategory $c) => $this->mapNode($c, $all))
            ->values();

        return [
            'id' => $node->id,
            'name' => $node->name,
            'icon' => $node->icon,
            'description' => $node->description,
            'sort' => (int) $node->sort,
            'is_active' => (bool) $node->is_active,
            'parent_id' => $node->parent_id,
            'parent_name' => $node->parent?->name,
            'services_count' => $node->services_count + $children->sum('services_count'),
            'children' => $children,
        ];
    }

    /** اعتبارسنجی مشترک ساخت/ویرایش */
    private function validated(Request $request, ?ServiceCategory $category): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'icon' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'نام دسته‌بندی الزامی است.',
            'parent_id.exists' => 'دسته والد معتبر نیست.',
            'parent_id.integer' => 'دسته والد معتبر نیست.',
        ]);

        $data['parent_id'] = $data['parent_id'] ?: null;
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['icon'] = trim((string) ($data['icon'] ?? '')) ?: null;
        $data['description'] = $data['description'] ?? null;

        // عمق درخت حداکثر ۲ سطح (دسته اصلی + زیردسته)
        if ($data['parent_id'] !== null) {
            $parent = ServiceCategory::find($data['parent_id']);
            abort_if(! $parent, 422, 'دسته والد یافت نشد.');
            if ($parent->parent_id !== null) {
                abort(response()->json([
                    'message' => 'عمق درخت دسته‌بندی حداکثر دو سطح است (دسته اصلی + زیردسته).',
                ], 422));
            }
            // جابجایی زیر شاخه‌ی خود ممنوع
            if ($category && $category->id === $data['parent_id']) {
                abort(response()->json([
                    'message' => 'دسته نمی‌تواند والد خودش باشد.',
                ], 422));
            }
        }

        return $data;
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);

        $category = ServiceCategory::create($data);

        AuditLogger::log('service_category.created', $category, null, [
            'name' => $category->name,
            'parent_id' => $category->parent_id,
            'is_active' => $category->is_active,
        ], 'ایجاد دسته‌بندی خدمت «'.$category->name.'»');

        return response()->json([
            'message' => 'دسته‌بندی «'.$category->name.'» ایجاد شد.',
        ]);
    }

    public function update(Request $request, ServiceCategory $category): JsonResponse
    {
        $data = $this->validated($request, $category);

        $old = $category->only(['name', 'parent_id', 'icon', 'description', 'sort', 'is_active']);
        $category->update($data);

        AuditLogger::log('service_category.updated', $category, $old,
            $category->only(['name', 'parent_id', 'icon', 'description', 'sort', 'is_active']),
            'ویرایش دسته‌بندی «'.$category->name.'»');

        return response()->json(['message' => 'تغییرات دسته‌بندی ذخیره شد.']);
    }

    /** فعال/غیرفعال (AJAX) */
    public function toggle(ServiceCategory $category): JsonResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        AuditLogger::log('service_category.toggled', $category,
            ['is_active' => ! $category->is_active],
            ['is_active' => $category->is_active],
            ($category->is_active ? 'فعال‌سازی' : 'غیرفعال‌سازی').' دسته‌بندی «'.$category->name.'»');

        return response()->json([
            'message' => $category->is_active
                ? 'دسته‌بندی فعال شد.'
                : 'دسته‌بندی غیرفعال شد (از کاتالوگ مشتریان پنهان می‌شود).',
            'is_active' => $category->is_active,
        ]);
    }

    /** حذف — فقط وقتی فرزند یا خدمتی نداشته باشد (AJAX) */
    public function destroy(ServiceCategory $category): JsonResponse
    {
        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'این دسته دارای زیردسته است؛ ابتدا زیردسته‌ها را حذف یا منتقل کنید.',
            ], 422);
        }

        if ($category->services()->exists()) {
            return response()->json([
                'message' => 'این دسته دارای خدمت است؛ ابتدا خدمات آن را حذف یا منتقل کنید.',
            ], 422);
        }

        $old = $category->only(['name', 'parent_id', 'icon']);
        $name = $category->name;
        $category->delete();

        AuditLogger::log('service_category.deleted', null, $old, null,
            'حذف دسته‌بندی «'.$name.'»');

        return response()->json(['message' => 'دسته‌بندی «'.$name.'» حذف شد.']);
    }
}
