<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\City;
use App\Models\Province;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Morilog\Jalali\Jalalian;

/**
 * پروفایل مشتری — نمایش و تکمیل اجباری.
 */
class ProfileController extends Controller
{
    /** GET /api/v1/me — پروفایل + آمار سفارش‌ها (کارت آماری صفحهٔ پروفایل) */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = $user->orders()
            ->selectRaw("status, COUNT(*) AS n")
            ->groupBy('status')
            ->pluck('n', 'status');

        $active = collect(['paid', 'accepted', 'in_progress', 'needs_info', 'broadcasting', 'queued', 'delivered'])
            ->sum(fn (string $s) => (int) ($stats[$s] ?? 0));

        return response()->json([
            'user' => UserResource::make($user->load(['province', 'city'])),
            'orders_stats' => [
                'total' => (int) $stats->sum(),
                'active' => (int) $active,
                'completed' => (int) ($stats['completed'] ?? 0),
                'cancelled' => (int) (($stats['cancelled'] ?? 0) + ($stats['refunded'] ?? 0)),
            ],
        ]);
    }

    /** POST /api/v1/profile/complete */
    public function complete(Request $request): JsonResponse
    {
        $data = $request->all();
        $data['birthdate'] = $this->normalizeBirthdate((string) ($data['birthdate'] ?? ''));

        /** @var Validator $validator */
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'family' => ['required', 'string', 'min:2', 'max:60'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'city_id' => [
                'required', 'integer', 'exists:cities,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($data) {
                    $city = City::find($value);
                    if ($city && (int) ($data['province_id'] ?? 0) !== (int) $city->province_id) {
                        $fail('شهر انتخاب‌شده به استان انتخابی تعلق ندارد.');
                    }
                },
            ],
            'birthdate' => ['required', 'date_format:Y-m-d'],
        ], [
            'required' => '«:attribute» الزامی است.',
            'min' => '«:attribute» باید حداقل :min نویسه باشد.',
            'max' => '«:attribute» نباید بیشتر از :max نویسه باشد.',
            'in' => 'مقدار «:attribute» معتبر نیست.',
            'exists' => '«:attribute» انتخاب‌شده معتبر نیست.',
            'date_format' => 'تاریخ تولد معتبر نیست (نمونه: ۱۳۷۰/۰۵/۱۲).',
        ], [
            'name' => 'نام',
            'family' => 'نام‌خانوادگی',
            'gender' => 'جنسیت',
            'province_id' => 'استان',
            'city_id' => 'شهر',
            'birthdate' => 'تاریخ تولد',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();
        $birth = Carbon::createFromFormat('Y-m-d', $validated['birthdate']);

        // سن معقول ۱۰ تا ۱۰۰ سال
        $age = $birth->age;
        if ($age < 10 || $age > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'birthdate' => ['سن شما باید بین '.fa_digits('۱۰').' تا '.fa_digits('۱۰۰').' سال باشد.'],
            ]);
        }

        $user = $request->user();
        $old = $user->only(['name', 'family', 'gender', 'province_id', 'city_id', 'birthdate']);

        $user->forceFill([
            'name' => trim($validated['name']),
            'family' => trim($validated['family']),
            'gender' => $validated['gender'],
            'province_id' => (int) $validated['province_id'],
            'city_id' => (int) $validated['city_id'],
            'birthdate' => $birth,
            'profile_completed' => true,
        ])->save();

        AuditLogger::log('customer.profile_completed', $user, $old, $user->only(['name', 'family', 'gender', 'province_id', 'city_id', 'birthdate']), 'تکمیل/ویرایش پروفایل مشتری');

        return response()->json([
            'message' => 'پروفایل با موفقیت ذخیره شد.',
            'user' => UserResource::make($user->refresh()->load(['province', 'city'])),
        ]);
    }

    /** تاریخ تولد: ارقام فارسی + شمسی یا میلادی → Y-m-d (یا خروجی نامعتبر که date_format خطا می‌دهد) */
    protected function normalizeBirthdate(string $value): string
    {
        $value = trim(en_digits($value));

        if ($value === '') {
            return '';
        }

        foreach (['Y/m/d', 'Y-m-d', 'Y.m.d'] as $format) {
            try {
                return Jalalian::fromFormat($format, $value)->toCarbon()->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return $value; // فرمت میلادی یا نامعتبر — اعتبارسنجی خودش خطا می‌دهد
    }
}
