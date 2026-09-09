<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * جغرافیا — استان/شهر برای فرم پروفایل (سلکت آبشاری).
 */
class GeoController extends Controller
{
    /** GET /api/v1/geo/provinces */
    public function provinces(): JsonResponse
    {
        return response()->json([
            'data' => Province::query()
                ->orderBy('sort')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Province $p) => ['id' => $p->id, 'name' => $p->name]),
        ]);
    }

    /** GET /api/v1/geo/cities/{province} */
    public function cities(Request $request, int $province): JsonResponse
    {
        $cities = City::query()
            ->where('province_id', $province)
            ->orderBy('name')
            ->get(['id', 'name', 'province_id']);

        return response()->json([
            'data' => $cities->map(fn (City $c) => ['id' => $c->id, 'name' => $c->name]),
        ]);
    }
}
