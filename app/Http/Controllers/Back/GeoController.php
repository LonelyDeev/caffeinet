<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * جغرافیا مشترک پنل‌ها — شهرهای یک استان برای سلکت آبشاری.
 */
class GeoController extends Controller
{
    public function cities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
        ]);

        $cities = City::where('province_id', $data['province_id'])
            ->orderBy('name')
            ->get(['id', 'name', 'province_id']);

        return response()->json(['cities' => $cities]);
    }
}
