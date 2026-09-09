<?php

namespace App\Http\Controllers\Back\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ReferralSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CoffeenetsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');

        return view('back.org.coffeenets.index', [
            'organization' => $org,
            'referral' => ReferralSetting::current(),
            'provinces' => \App\Models\Province::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** کافی‌نت‌های زیرمجموعه سازمان (AJAX + جستجو + فیلتر وضعیت) */
    public function data(Request $request): JsonResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');

        $query = $org->coffeenets()
            ->with(['province:id,name', 'city:id,name']);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%"));
        }

        if ($status = (string) $request->query('status')) {
            if (in_array($status, ['pending', 'approved', 'rejected', 'suspended'], true)) {
                $query->where('status', $status);
            }
        }

        $paginator = $query->latest('id')->paginate(25);

        $rows = $paginator->through(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'province' => $c->province?->name,
            'city' => $c->city?->name,
            'address' => $c->address,
            'reward_paid' => $c->introduction_reward_paid,
            'status' => [
                'value' => $c->status->value,
                'label' => $c->status->label(),
                'color' => $c->status->color(),
            ],
            'created_at' => $c->created_at ? jdate($c->created_at)->format('Y/m/d') : '—',
            'approved_at' => $c->approved_at ? jdate($c->approved_at)->format('Y/m/d') : null,
        ]);

        return response()->json($rows);
    }

    /**
     * معرفی کافی‌نت جدید (AJAX) — با وضعیت «در انتظار تأیید» ثبت می‌شود
     * و پس از تأیید مدیریت کل، پاداش معرفی به کیف پول سازمان واریز خواهد شد.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150',
                // جلوگیری از ثبت تکراری با همان نام در این سازمان
                function (string $attribute, mixed $value, \Closure $fail) use ($org) {
                    $exists = $org->coffeenets()
                        ->where('name', trim((string) $value))
                        ->whereIn('status', ['pending', 'approved'])
                        ->exists();
                    if ($exists) {
                        $fail('کافی‌نتی با این نام قبلاً برای سازمان شما ثبت شده است.');
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:15'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'نام کافی‌نت الزامی است.',
            'city_id.exists' => 'شهرستان انتخابی معتبر نیست.',
        ]);

        $coffeenet = DB::transaction(function () use ($org, $data) {
            return $org->coffeenets()->create([
                'name' => trim($data['name']),
                'phone' => $data['phone'] ?? null,
                'province_id' => $data['province_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => 'pending',
            ]);
        });

        AuditLogger::log('coffeenet.introduced', $coffeenet, null, [
            'name' => $coffeenet->name,
            'organization' => $org->name,
        ], 'معرفی کافی‌نت جدید توسط سازمان «'.$org->name.'»');

        return response()->json([
            'message' => 'کافی‌نت «'.$coffeenet->name.'» ثبت شد. پس از تأیید مدیریت کل، پاداش معرفی (در صورت فعال بودن) به کیف پول شما واریز می‌شود.',
        ]);
    }
}
