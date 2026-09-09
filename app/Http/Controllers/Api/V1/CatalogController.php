<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ServiceDetailResource;
use App\Http\Resources\Api\ServiceResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * کاتالوگ خدمات مشتری — درخت دسته‌ها + جستجو/فیلتر + جزئیات خدمت با فرم داینامیک.
 */
class CatalogController extends Controller
{
    /** GET /api/v1/categories/tree */
    public function categoriesTree(): JsonResponse
    {
        $all = ServiceCategory::query()->orderBy('sort')->orderBy('id')->get();
        $activeIds = $this->activeCategoryIds($all);

        $counts = Service::query()
            ->where('is_active', true)
            ->whereIn('category_id', $activeIds)
            ->selectRaw('category_id, COUNT(*) AS c')
            ->groupBy('category_id')
            ->pluck('c', 'category_id');

        $visible = $all->filter(fn (ServiceCategory $c) => in_array($c->id, $activeIds))->values();

        $build = function (ServiceCategory $c) use (&$build, $visible, $counts): array {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon ?: '📁',
                'parent_id' => $c->parent_id,
                'services_count' => (int) ($counts[$c->id] ?? 0),
                'children' => $visible
                    ->filter(fn (ServiceCategory $x) => (int) $x->parent_id === (int) $c->id)
                    ->map($build)
                    ->values()
                    ->all(),
            ];
        };

        $tree = $visible
            ->filter(function (ServiceCategory $c) use ($activeIds) {
                // ریشه: بدون والد یا والدش فعال نیست (نمایش جدا)
                return ! $c->parent_id || ! in_array($c->parent_id, $activeIds);
            })
            ->map($build)
            ->values()
            ->all();

        return response()->json(['data' => $tree]);
    }

    /** GET /api/v1/services?q=&category=&featured=1 */
    public function services(Request $request): JsonResponse
    {
        $activeIds = $this->activeCategoryIds(ServiceCategory::query()->get());

        $query = Service::query()
            ->where('is_active', true)
            ->whereIn('category_id', $activeIds)
            ->with([
                'category' => fn ($q) => $q->select(['id', 'name', 'icon', 'parent_id']),
                'costs' => fn ($q) => $q->select(['service_id', 'amount']),
            ]);

        // جستجو
        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // فیلتر دسته (شامل زیردسته‌های فعال)
        if ($categoryId = (int) $request->query('category')) {
            $family = $this->categoryFamily($categoryId, $activeIds);
            $query->whereIn('category_id', $family);
        }

        // ویژه‌ها
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $query->orderByDesc('is_featured')->orderBy('sort')->orderBy('id');

        $paginator = $query->paginate(10)->withQueryString();

        $paginator->getCollection()->transform(fn (Service $service) => ServiceResource::make($service)->resolve());

        return response()->json($paginator);
    }

    /** GET /api/v1/services/{service} */
    public function show(Request $request, Service $service): JsonResponse
    {
        abort_if(! $service->is_active, 404, 'خدمت یافت نشد.');

        $activeIds = $this->activeCategoryIds(ServiceCategory::query()->get());
        abort_unless(in_array($service->category_id, $activeIds), 404, 'خدمت یافت نشد.');

        return response()->json([
            'data' => ServiceDetailResource::make($service->load([
                'category' => fn ($q) => $q->select(['id', 'name', 'icon', 'parent_id']),
            ])),
        ]);
    }

    /**
     * شناسه دسته‌هایی که خودشان و کل زنجیره والدشان فعال‌اند.
     *
     * @param  \Illuminate\Support\Collection<ServiceCategory>  $all
     * @return int[]
     */
    protected function activeCategoryIds($all): array
    {
        $byId = $all->keyBy('id');
        $active = [];

        foreach ($all as $category) {
            $ok = true;
            $cursor = $category;
            $depth = 0;

            while ($cursor && $depth < 10) {
                if (! $cursor->is_active) {
                    $ok = false;
                    break;
                }
                $cursor = $cursor->parent_id ? ($byId->get($cursor->parent_id)) : null;
                $depth++;
            }

            if ($ok) {
                $active[] = (int) $category->id;
            }
        }

        return $active;
    }

    /** خود دسته + زیردسته‌های فعال آن */
    protected function categoryFamily(int $categoryId, array $activeIds): array
    {
        $all = ServiceCategory::query()->get(['id', 'parent_id']);
        $family = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = $all
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $activeIds))
                ->values()
                ->all();

            $family = array_values(array_unique(array_merge($family, $children)));
            $frontier = $children;
        }

        return $family;
    }

    /**
     * GET /api/v1/services-grouped — درخت دسته‌ها + خدماتِ هر دسته و زیردسته.
     *
     * برای صفحهٔ «خدمات»: در حالت «همه»، هر دسته و هر زیردسته سکشن
     * جداگانه با خدمات خودش برمی‌گردد (خدماتِ مستقیمِ هر دسته،
     * نه توارث از زیردسته‌ها).
     */
    public function servicesGrouped(): JsonResponse
    {
        $all = ServiceCategory::query()->orderBy('sort')->orderBy('id')->get();
        $activeIds = $this->activeCategoryIds($all);

        // خدمات فعال، گروه‌بندی روی category_id
        $byCategory = Service::query()
            ->where('is_active', true)
            ->whereIn('category_id', $activeIds)
            ->with(['costs' => fn ($q) => $q->select(['service_id', 'amount'])])
            ->orderByDesc('is_featured')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->groupBy('category_id');

        $serviceItem = fn (Service $s) => ServiceResource::make($s)->resolve();

        $build = function (ServiceCategory $c) use (&$build, $all, $activeIds, $byCategory, $serviceItem): array {
            $children = $all
                ->filter(fn (ServiceCategory $x) => (int) $x->parent_id === (int) $c->id && in_array($x->id, $activeIds))
                ->sort(fn ($a, $b) => [$a->sort, $a->id] <=> [$b->sort, $b->id])
                ->values();

            return [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon ?: '📁',
                'parent_id' => $c->parent_id,
                'services_count' => $byCategory->get($c->id)?->count() ?? 0,
                'services' => ($byCategory->get($c->id) ?? collect())
                    ->map($serviceItem)
                    ->values()
                    ->all(),
                'children' => $children
                    ->map(fn (ServiceCategory $x) => $build($x))
                    ->values()
                    ->all(),
            ];
        };

        $tree = $all
            ->filter(function (ServiceCategory $c) use ($activeIds) {
                // ریشه: بدون والد یا والدش فعال نیست (نمایش جدا)
                return in_array($c->id, $activeIds)
                    && (! $c->parent_id || ! in_array((int) $c->parent_id, $activeIds));
            })
            ->values()
            ->map(fn (ServiceCategory $c) => $build($c))
            ->all();

        $totalServices = $byCategory->flatten()->count();

        return response()->json([
            'data' => [
                'total_services' => $totalServices,
                'total_categories' => count($activeIds),
                'categories' => $tree,
            ],
        ]);
    }
}
