<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** صفحه تنظیمات کافی‌نت */
    public function index(Request $request, Coffeenet $coffeenet): View
    {
        abort_unless($request->attributes->get('current_coffeenet')?->id === $coffeenet->id, 403);

        return view('back.coffeenet.settings.index', [
            'coffeenet' => $coffeenet->load('province:id,name', 'city:id,name', 'organization:id,name'),
            'provinces' => \App\Models\Province::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** ذخیره اطلاعات کافی‌نت (AJAX) */
    public function update(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        abort_unless($request->attributes->get('current_coffeenet')?->id === $coffeenet->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:15'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'نام کافی‌نت الزامی است.',
            'province_id.exists' => 'استان انتخابی معتبر نیست.',
            'city_id.exists' => 'شهرستان انتخابی معتبر نیست.',
        ]);

        $old = $coffeenet->only(['name', 'phone', 'province_id', 'city_id', 'address']);

        $coffeenet->fill($data)->save();

        AuditLogger::log('coffeenet.self_updated', $coffeenet->refresh(), $old,
            $coffeenet->only(['name', 'phone', 'province_id', 'city_id', 'address']),
            'ویرایش اطلاعات کافی‌نت از پنل مدیریت آن');

        return response()->json(['message' => 'اطلاعات کافی‌نت ذخیره شد.']);
    }

    /** تغییر رمز عبور مدیر (AJAX — با رمز فعلی) */
    public function password(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        abort_unless($request->attributes->get('current_coffeenet')?->id === $coffeenet->id, 403);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'رمز عبور فعلی الزامی است.',
            'password.required' => 'رمز عبور جدید الزامی است.',
            'password.min' => 'رمز عبور جدید حداقل ۸ کاراکتر باشد.',
            'password.confirmed' => 'تکرار رمز عبور جدید مطابقت ندارد.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'رمز عبور فعلی اشتباه است.'], 422);
        }

        $user->update(['password' => $data['password']]);

        AuditLogger::log('coffeenet.password_changed', $user, null, null, 'تغییر رمز عبور مدیر کافی‌نت');

        return response()->json(['message' => 'رمز عبور با موفقیت تغییر کرد.']);
    }
}
