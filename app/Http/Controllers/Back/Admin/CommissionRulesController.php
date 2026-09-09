<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use App\Models\Service;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * قواعد کمیسیون (فاز ۸) — قاعدهٔ سراسری + قواعد اختصاصی هر خدمت.
 * موتور تسویه اول قاعدهٔ فعال خدمت را برمی‌دارد؛ نبود → قاعدهٔ سراسری.
 */
class CommissionRulesController extends Controller
{
    public function index(Request $request): View
    {
        $global = CommissionSetting::query()
            ->where('scope', 'global')
            ->whereNull('service_id')
            ->first();

        $services = Service::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('back.admin.commissions.index', [
            'global' => $global,
            'services' => $services,
            'serviceRulesCount' => CommissionSetting::query()->where('scope', 'service')->count(),
        ]);
    }

    /** ذخیرهٔ قاعدهٔ سراسری */
    public function saveGlobal(Request $request): JsonResponse
    {
        $data = $this->validateRule($request);

        $setting = CommissionSetting::query()
            ->where('scope', 'global')
            ->whereNull('service_id')
            ->first();

        $old = $setting?->only(['platform_type', 'platform_value', 'organization_type', 'organization_value', 'coffeenet_type', 'coffeenet_value']);

        if ($setting) {
            $setting->update($data);
        } else {
            $data['scope'] = 'global';
            $data['service_id'] = null;
            $setting = CommissionSetting::create($data);
        }

        AuditLogger::log(
            'commissions.global_saved',
            $setting,
            $old,
            $setting->only(['platform_type', 'platform_value', 'organization_type', 'organization_value', 'coffeenet_type', 'coffeenet_value']),
            'به‌روزرسانی قاعدهٔ سراسری کمیسیون',
        );

        return response()->json([
            'message' => 'قاعدهٔ سراسری کمیسیون ذخیره شد.',
            'data' => $this->ruleRow($setting, null),
        ]);
    }

    /** لیست قواعد اختصاصی خدمات (AJAX) */
    public function data(Request $request): JsonResponse
    {
        $query = CommissionSetting::query()
            ->where('scope', 'service')
            ->whereNotNull('service_id')
            ->with('service:id,name');

        $rows = $query->orderByDesc('id')->get();

        return response()->json([
            'data' => $rows->map(fn (CommissionSetting $r) => $this->ruleRow($r, $r->service?->name)),
        ]);
    }

    /** ساخت قاعدهٔ اختصاصی برای یک خدمت */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateRule($request, requireService: true);

        $service = Service::findOrFail((int) $data['service_id']);

        if (CommissionSetting::query()->where('scope', 'service')->where('service_id', $service->id)->exists()) {
            return response()->json(['message' => 'برای این خدمت قبلاً قاعده ثبت شده است — آن را ویرایش کنید.'], 422);
        }

        $data['scope'] = 'service';
        $setting = CommissionSetting::create($data);

        AuditLogger::log(
            'commissions.service_rule_created',
            $setting,
            [],
            ['service' => $service->name] + $this->flatRule($setting),
            'ثبت قاعدهٔ کمیسیون اختصاصی برای خدمت «'.$service->name.'»',
        );

        return response()->json([
            'message' => 'قاعدهٔ اختصاصی خدمت «'.$service->name.'» ثبت شد.',
            'data' => $this->ruleRow($setting, $service->name),
        ]);
    }

    /** ویرایش قاعدهٔ اختصاصی */
    public function update(Request $request, CommissionSetting $commission): JsonResponse
    {
        if ($commission->scope !== 'service') {
            return response()->json(['message' => 'این قاعده از این مسیر قابل ویرایش نیست.'], 422);
        }

        $old = $commission->only(['platform_type', 'platform_value', 'organization_type', 'organization_value', 'coffeenet_type', 'coffeenet_value']);
        $data = $this->validateRule($request);
        $commission->update($data);

        AuditLogger::log(
            'commissions.service_rule_updated',
            $commission,
            $old,
            $commission->only(['platform_type', 'platform_value', 'organization_type', 'organization_value', 'coffeenet_type', 'coffeenet_value']),
            'ویرایش قاعدهٔ کمیسیون خدمت «'.($commission->service?->name ?? '-').'»',
        );

        return response()->json([
            'message' => 'قاعدهٔ کمیسیون بروزرسانی شد.',
            'data' => $this->ruleRow($commission->refresh(), $commission->service?->name),
        ]);
    }

    /** فعال/غیرفعال قاعدهٔ اختصاصی */
    public function toggle(Request $request, CommissionSetting $commission): JsonResponse
    {
        if ($commission->scope !== 'service') {
            return response()->json(['message' => 'قاعدهٔ سراسری همیشه فعال است.'], 422);
        }

        $commission->update(['is_active' => ! $commission->is_active]);

        AuditLogger::log(
            'commissions.service_rule_toggled',
            $commission,
            ['is_active' => ! $commission->is_active],
            ['is_active' => $commission->is_active],
            ($commission->is_active ? 'فعال‌سازی' : 'غیرفعال‌سازی').' قاعدهٔ کمیسیون خدمت «'.($commission->service?->name ?? '-').'»',
        );

        return response()->json([
            'message' => $commission->is_active ? 'قاعده فعال شد.' : 'قاعده غیرفعال شد.',
            'data' => $this->ruleRow($commission->refresh(), $commission->service?->name),
        ]);
    }

    /** حذف قاعدهٔ اختصاصی */
    public function destroy(Request $request, CommissionSetting $commission): JsonResponse
    {
        if ($commission->scope !== 'service') {
            return response()->json(['message' => 'قاعدهٔ سراسری قابل حذف نیست.'], 422);
        }

        $name = $commission->service?->name ?? '-';
        $flat = $this->flatRule($commission);
        $commission->delete();

        AuditLogger::log(
            'commissions.service_rule_deleted',
            null,
            ['service' => $name] + $flat,
            [],
            'حذف قاعدهٔ کمیسیون خدمت «'.$name.'»',
        );

        return response()->json(['message' => 'قاعدهٔ خدمت «'.$name.'» حذف شد.']);
    }

    /* ---------- ابزارها ---------- */

    protected function validateRule(Request $request, bool $requireService = false): array
    {
        $data = $request->validate([
            'service_id' => [$requireService ? 'required' : 'nullable', 'integer', 'exists:services,id'],
            'platform_type' => ['required', 'in:percent,fixed'],
            'platform_value' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'organization_type' => ['nullable', 'in:percent,fixed'],
            'organization_value' => ['nullable', 'numeric', 'min:0', 'max:1000000000', 'required_with:organization_type'],
            'coffeenet_type' => ['required', 'in:percent,fixed'],
            'coffeenet_value' => ['required', 'numeric', 'min:0', 'max:1000000000'],
        ], [
            'platform_type.required' => 'نوع سهم پلتفرم را انتخاب کنید.',
            'platform_value.required' => 'مقدار سهم پلتفرم را وارد کنید.',
            'coffeenet_type.required' => 'نوع سهم کافی‌نت را انتخاب کنید.',
            'coffeenet_value.required' => 'مقدار سهم کافی‌نت را وارد کنید.',
            'organization_value.required_with' => 'برای سهم سازمان، مقدار را وارد کنید.',
        ]);

        // هر سه درصدی؟ جمع ≤ ۱۰۰ (باقیمانده نزد پلتفرم)
        if ($data['platform_type'] === 'percent' && ($data['coffeenet_type'] ?? '') === 'percent'
            && ($data['organization_type'] ?? null) === 'percent') {
            $sum = (float) $data['platform_value'] + (float) $data['coffeenet_value'] + (float) ($data['organization_value'] ?? 0);
            if ($sum > 100) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'platform_value' => "جمع سهم‌های درصدی نمی‌تواند بیشتر از ۱۰۰٪ باشد (جمع فعلی: {$sum}٪). باقیمانده نزد پلتفرم می‌ماند.",
                ]);
            }
        }

        return $data;
    }

    protected function flatRule(CommissionSetting $r): array
    {
        return [
            'platform' => $this->shareText($r->platform_type, $r->platform_value),
            'organization' => $r->organization_type ? $this->shareText($r->organization_type, $r->organization_value) : '—',
            'coffeenet' => $this->shareText($r->coffeenet_type, $r->coffeenet_value),
        ];
    }

    protected function ruleRow(CommissionSetting $r, ?string $serviceName): array
    {
        return [
            'id' => $r->id,
            'service_id' => $r->service_id,
            'service_name' => $serviceName ?? 'سراسری',
            'scope' => $r->scope,
            'is_active' => (bool) $r->is_active,
            'platform' => $this->shareText($r->platform_type, $r->platform_value),
            'platform_raw' => ['type' => $r->platform_type, 'value' => (float) $r->platform_value],
            'organization' => $r->organization_type ? $this->shareText($r->organization_type, $r->organization_value) : null,
            'organization_raw' => ['type' => $r->organization_type, 'value' => (float) ($r->organization_value ?? 0)],
            'coffeenet' => $this->shareText($r->coffeenet_type, $r->coffeenet_value),
            'coffeenet_raw' => ['type' => $r->coffeenet_type, 'value' => (float) $r->coffeenet_value],
        ];
    }

    protected function shareText(?string $type, ?string $value): string
    {
        if (! $type) {
            return '—';
        }

        return $type === 'percent'
            ? fa_number((float) $value).'٪ از مبلغ مشمول'
            : fa_money((float) $value).' ثابت';
    }
}
