<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\ServiceVersionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServicesController extends Controller
{
    public const FIELD_TYPES = [
        'text', 'textarea', 'number', 'mobile', 'national_code',
        'email', 'date', 'select', 'radio', 'checkbox', 'file',
    ];

    public function __construct(
        protected ServiceVersionManager $versions,
    ) {
    }

    public function index(): View
    {
        return view('back.admin.services.index', [
            'categories' => $this->flatCategories(),
        ]);
    }

    /** لیست خدمات (AJAX + جستجو + فیلتر دسته/وضعیت/ویژه + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $query = Service::query()
            ->with([
                'category:id,name,parent_id',
                'category.parent:id,name',
            ])
            ->withCount(['costs', 'formFields']);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($categoryId = (int) $request->query('category_id')) {
            // فیلتر شامل زیردسته‌های دسته انتخابی
            $ids = ServiceCategory::where('parent_id', $categoryId)->pluck('id');
            $ids->push($categoryId);
            $query->whereIn('category_id', $ids);
        }

        if ($status = (string) $request->query('status')) {
            if (in_array($status, ['active', 'inactive'], true)) {
                $query->where('is_active', $status === 'active');
            }
        }

        if ($request->query('featured') === '1') {
            $query->where('is_featured', true);
        }

        $paginator = $query
            ->orderByDesc('is_featured')
            ->orderBy('sort')
            ->orderByDesc('id')
            ->paginate(20);

        $rows = $paginator->through(fn (Service $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'slug' => $s->slug,
            'category' => $s->category?->name,
            'category_parent' => $s->category?->parent?->name,
            'base_price' => (float) $s->base_price,
            'commissionable' => (float) $s->costs()->where('is_commission', true)->sum('amount'),
            'costs_count' => $s->costs_count,
            'fields_count' => $s->form_fields_count,
            'estimated_time' => (int) $s->estimated_time,
            'version' => (int) $s->version,
            'is_active' => (bool) $s->is_active,
            'is_featured' => (bool) $s->is_featured,
            'requires_upload' => (bool) $s->requires_upload,
            'requires_verification' => (bool) $s->requires_verification,
            // فاز ۱۵
            'image_url' => $s->imageUrl(),
            'availability_state' => $s->availabilityState(),
            'expires_at_label' => $s->expiresAtLabel(),
            'has_alert' => (bool) $s->alert_type && $s->alert_type !== 'none',
            'updated_at' => $s->updated_at?->diffForHumans(now(), ['locale' => 'fa']) ?? '—',
        ]);

        return response()->json($rows);
    }

    /** صفحه فرم‌ساز — ایجاد خدمت جدید */
    public function create(): View
    {
        return view('back.admin.services.builder', [
            'mode' => 'create',
            'categories' => $this->flatCategories(),
            'payload' => [
                'service' => [
                    'name' => '', 'category_id' => null, 'description' => '',
                    'base_price' => 0, 'estimated_time' => 0,
                    'requires_upload' => false, 'requires_verification' => false,
                    'is_active' => true, 'is_featured' => false, 'sort' => 0,
                    // فاز ۱۵
                    'image_url' => null,
                    'availability' => 'active', 'unavailable_note' => '',
                    'expires_date' => '', 'expires_time' => '23:59', 'expired_note' => '',
                    'alert_type' => 'none', 'alert_text' => '', 'alert_image_url' => null,
                ],
                'costs' => [],
                'fields' => [],
                'version' => 0,
            ],
            'fieldTypes' => self::FIELD_TYPES,
        ]);
    }

    /** صفحه فرم‌ساز — ویرایش خدمت */
    public function edit(Service $service): View
    {
        $service->load(['category:id,name,parent_id']);

        return view('back.admin.services.builder', [
            'mode' => 'edit',
            'categories' => $this->flatCategories(),
            'payload' => [
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'category_id' => $service->category_id,
                    'description' => (string) $service->description,
                    'base_price' => (float) $service->base_price,
                    'estimated_time' => (int) $service->estimated_time,
                    'requires_upload' => (bool) $service->requires_upload,
                    'requires_verification' => (bool) $service->requires_verification,
                    'is_active' => (bool) $service->is_active,
                    'is_featured' => (bool) $service->is_featured,
                    'sort' => (int) $service->sort,
                    // فاز ۱۵
                    'image_url' => $service->imageUrl(),
                    'availability' => $service->availability ?? 'active',
                    'unavailable_note' => (string) $service->unavailable_note,
                    'expires_date' => $service->expires_at ? fa_date($service->expires_at, 'Y/m/d') : '',
                    'expires_time' => $service->expires_at ? $service->expires_at->format('H:i') : '23:59',
                    'expired_note' => (string) $service->expired_note,
                    'alert_type' => $service->alert_type ?: 'none',
                    'alert_text' => (string) $service->alert_text,
                    'alert_image_url' => $service->alertImageUrl(),
                ],
                'costs' => $service->costs()->orderBy('id')->get()->map(fn ($c) => [
                    'id' => $c->id,
                    'type' => $c->type,
                    'title' => $c->title,
                    'amount' => (float) $c->amount,
                    'is_commission' => (bool) $c->is_commission,
                    'note' => (string) $c->note,
                ])->all(),
                'fields' => $service->formFields()->orderBy('sort')->get()->map(fn ($f) => [
                    'id' => $f->id,
                    'field_type' => $f->field_type,
                    'label' => $f->label,
                    'name' => $f->name,
                    'placeholder' => (string) $f->placeholder,
                    'help_text' => (string) $f->help_text,
                    'is_required' => (bool) $f->is_required,
                    'is_active' => (bool) $f->is_active,
                    'options' => $f->options ?? [],
                ])->all(),
                'version' => (int) $service->version,
            ],
            'fieldTypes' => self::FIELD_TYPES,
        ]);
    }

    /** اعتبارسنجی کامل payload (پایه + هزینه‌ها + فیلدها) — در صورت خطای منطقی JsonResponse برمی‌گرداند */
    private function validated(Request $request): array|JsonResponse
    {
        /* فاز ۱۵ — payload می‌تواند JSON یا multipart باشد؛
           در multipart، هزینه‌ها/فیلدها به‌صورت رشتهٔ JSON ارسال می‌شوند. */
        $input = $request->all();
        if (isset($input['costs']) && is_string($input['costs'])) {
            $decoded = json_decode($input['costs'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $input['costs'] = $decoded;
                $request->merge(['costs' => $decoded]);
            }
        }
        if (isset($input['fields']) && is_string($input['fields'])) {
            $decoded = json_decode($input['fields'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $input['fields'] = $decoded;
                $request->merge(['fields' => $decoded]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'estimated_time' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'requires_upload' => ['nullable', 'boolean'],
            'requires_verification' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:99999'],

            // فاز ۱۵ — وضعیت/مهلت/آلرت
            'availability' => ['nullable', 'string', 'in:active,unavailable'],
            'unavailable_note' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'string', 'max:40'],
            'expired_note' => ['nullable', 'string', 'max:500'],
            'alert_type' => ['nullable', 'string', 'in:none,text,image'],
            'alert_text' => ['nullable', 'string', 'max:500'],
            'remove_image' => ['nullable', 'boolean'],
            'remove_alert_image' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'alert_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'costs' => ['present', 'array', 'max:30'],
            'costs.*.type' => ['required', 'in:expense,fee'],
            'costs.*.title' => ['required', 'string', 'max:150'],
            'costs.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'costs.*.is_commission' => ['nullable', 'boolean'],
            'costs.*.note' => ['nullable', 'string', 'max:500'],

            'fields' => ['present', 'array', 'max:30'],
            'fields.*.field_type' => ['required', 'in:'.implode(',', self::FIELD_TYPES)],
            'fields.*.label' => ['required', 'string', 'max:150'],
            'fields.*.name' => ['nullable', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_]+$/'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:200'],
            'fields.*.help_text' => ['nullable', 'string', 'max:300'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.is_active' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:50'],
            'fields.*.options.*' => ['string', 'max:150'],
        ], [
            'name.required' => 'نام خدمت الزامی است.',
            'category_id.required' => 'انتخاب دسته‌بندی الزامی است.',
            'category_id.exists' => 'دسته‌بندی انتخابی معتبر نیست.',
            'base_price.required' => 'قیمت پایه الزامی است.',
            'base_price.numeric' => 'قیمت پایه باید عدد باشد.',
            'base_price.min' => 'قیمت پایه نمی‌تواند منفی باشد.',
            'costs.*.type.in' => 'نوع ردیف هزینه معتبر نیست.',
            'costs.*.title.required' => 'عنوان ردیف هزینه الزامی است.',
            'costs.*.amount.required' => 'مبلغ ردیف هزینه الزامی است.',
            'costs.*.amount.numeric' => 'مبلغ ردیف هزینه باید عدد باشد.',
            'fields.*.field_type.in' => 'نوع فیلد معتبر نیست.',
            'fields.*.label.required' => 'برچسب فیلد الزامی است.',
            'fields.*.name.regex' => 'نام فنی فیلد فقط حروف انگلیسی/عدد/زیرخط مجاز است.',
            'fields.*.options.max' => 'حداکثر ۵۰ گزینه برای هر فیلد.',
            'fields.*.options.*.max' => 'متن هر گزینه حداکثر ۱۵۰ کاراکتر است.',
        ]);

        // قواعد منطقی روی فیلدها
        $seen = [];
        foreach ($data['fields'] as $i => &$f) {
            $f['is_required'] = (bool) ($f['is_required'] ?? false);
            $f['is_active'] = (bool) ($f['is_active'] ?? true);
            $f['options'] = array_values(array_filter(array_map('trim', $f['options'] ?? [])));

            if (in_array($f['field_type'], ['select', 'radio', 'checkbox'], true)
                && count($f['options']) < 2) {
                return response()->json([
                    'message' => "فیلد «{$f['label']}» از نوع گزینه‌ای است؛ حداقل ۲ گزینه وارد کنید.",
                    'errors' => ["fields.{$i}.options" => ['حداقل ۲ گزینه لازم است.']],
                ], 422);
            }

            // تولید خودکار نام فنی یکتا از برچسب
            $name = strtolower((string) ($f['name'] ?? ''));
            $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?: '';
            $name = trim($name, '_');
            if ($name === '') {
                $name = Str::slug(Str::ascii($f['label']), '_');
                $name = preg_replace('/[^a-z0-9_]+/', '_', (string) $name) ?: '';
                if ($name === '') {
                    $name = 'field_'.($i + 1);
                }
            }
            while (in_array($name, $seen, true)) {
                $name .= '_2';
            }
            $seen[] = $name;
            $f['name'] = $name;
        }
        unset($f);

        foreach ($data['costs'] as &$c) {
            $c['is_commission'] = (bool) ($c['is_commission'] ?? false);
            $c['note'] = $c['note'] ?? null;
        }
        unset($c);

        $data['base_price'] = (float) $data['base_price'];
        $data['estimated_time'] = (int) ($data['estimated_time'] ?? 0);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['requires_upload'] = (bool) ($data['requires_upload'] ?? false);
        $data['requires_verification'] = (bool) ($data['requires_verification'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);
        $data['description'] = $data['description'] ?? null;

        /* ---------- فاز ۱۵ — نرمال‌سازی وضعیت/مهلت/آلرت ---------- */
        $data['availability'] = ($data['availability'] ?? 'active') === 'unavailable' ? 'unavailable' : 'active';
        $data['unavailable_note'] = trim((string) ($data['unavailable_note'] ?? '')) ?: null;
        $data['expired_note'] = trim((string) ($data['expired_note'] ?? '')) ?: null;
        $data['alert_type'] = in_array($data['alert_type'] ?? 'none', ['text', 'image'], true)
            ? $data['alert_type'] : null;
        $data['alert_text'] = trim((string) ($data['alert_text'] ?? '')) ?: null;

        // مهلت: تاریخ شمسی «۱۴۰۵/۰۶/۳۰ ۲۳:۵۹» یا ISO → Carbon
        $expiresRaw = trim(en_digits((string) ($data['expires_at'] ?? '')));
        $data['expires_at'] = $expiresRaw !== '' ? jalali_or_iso_to_carbon($expiresRaw, '23:59') : null;
        if ($expiresRaw !== '' && $data['expires_at'] === null) {
            return response()->json([
                'message' => 'فرمت تاریخ مهلت نامعتبر است (مثال صحیح: ۱۴۰۵/۰۶/۳۰).',
                'errors' => ['expires_at' => ['فرمت تاریخ مهلت نامعتبر است.']],
            ], 422);
        }

        // آلرت متنی بدون متن؟
        if ($data['alert_type'] === 'text' && $data['alert_text'] === null) {
            return response()->json([
                'message' => 'برای آلرت متنی، متن آلرت الزامی است.',
                'errors' => ['alert_text' => ['متن آلرت الزامی است.']],
            ], 422);
        }

        return $data;
    }

    /** ذخیره کامل خدمت: پایه + هزینه‌ها + فیلدها + نسخه ۱ (اتمیک) */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $service = DB::transaction(function () use ($data, $request) {
            $service = Service::create([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'],
                'base_price' => $data['base_price'],
                'estimated_time' => $data['estimated_time'],
                'requires_upload' => $data['requires_upload'],
                'requires_verification' => $data['requires_verification'],
                'is_active' => $data['is_active'],
                'is_featured' => $data['is_featured'],
                'sort' => $data['sort'],
                // فاز ۱۵
                'availability' => $data['availability'],
                'unavailable_note' => $data['unavailable_note'],
                'expires_at' => $data['expires_at'],
                'expired_note' => $data['expired_note'],
                'alert_type' => $data['alert_type'],
                'alert_text' => $data['alert_text'],
            ]);

            // فاز ۱۵ — آپلود تصاویر
            $this->receiveServiceImages($request, $service);

            $this->syncChildren($service, $data);

            // نسخه ۱ — snapshot اولیه
            $this->versions->sync($service);

            return $service->refresh();
        });

        AuditLogger::log('service.created', $service, null, [
            'name' => $service->name,
            'base_price' => $service->base_price,
            'costs' => count($data['costs']),
            'fields' => count($data['fields']),
        ], 'ایجاد خدمت «'.$service->name.'» با نسخه ۱');

        return response()->json([
            'message' => 'خدمت «'.$service->name.'» با موفقیت ایجاد شد.',
            'id' => $service->id,
            'version' => $service->version,
        ]);
    }

    /** بروزرسانی کامل + نسخه‌بندی خودکار در صورت تغییر داده‌های سفارش‌محور */
    public function update(Request $request, Service $service): JsonResponse
    {
        $data = $this->validated($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $old = [
            'name' => $service->name,
            'base_price' => (float) $service->base_price,
            'costs' => $service->costs()->count(),
            'fields' => $service->formFields()->count(),
            'version' => (int) $service->version,
        ];

        DB::transaction(function () use ($service, $data, $request) {
            $service->fill([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'base_price' => $data['base_price'],
                'estimated_time' => $data['estimated_time'],
                'requires_upload' => $data['requires_upload'],
                'requires_verification' => $data['requires_verification'],
                'is_active' => $data['is_active'],
                'is_featured' => $data['is_featured'],
                'sort' => $data['sort'],
                // فاز ۱۵
                'availability' => $data['availability'],
                'unavailable_note' => $data['unavailable_note'],
                'expires_at' => $data['expires_at'],
                'expired_note' => $data['expired_note'],
                'alert_type' => $data['alert_type'],
                'alert_text' => $data['alert_text'],
            ])->save();

            // فاز ۱۵ — آپلود/حذف تصاویر
            $this->receiveServiceImages($request, $service);

            $this->syncChildren($service, $data);

            // نسخه جدید فقط اگر قیمت/هزینه/فرم تغییر کرده باشد
            $this->versions->sync($service);
        });

        $service->refresh();

        AuditLogger::log('service.updated', $service, $old, [
            'name' => $service->name,
            'base_price' => $service->base_price,
            'costs' => count($data['costs']),
            'fields' => count($data['fields']),
            'version' => (int) $service->version,
        ], 'ویرایش خدمت «'.$service->name.'»'.(
            (int) $service->version > $old['version']
                ? ' — نسخه '.$service->version.' ثبت شد'
                : ''
        ));

        return response()->json([
            'message' => (int) $service->version > $old['version']
                ? 'تغییرات ذخیره شد و نسخه '.$service->version.' از خدمت ثبت شد (سفارش‌های جدید بر اساس همین نسخه قیمت‌گذاری می‌شوند).'
                : 'تغییرات ذخیره شد (بدون تغییر نسخه — داده‌های سفارش‌محور تغییر نکرد).',
            'id' => $service->id,
            'version' => (int) $service->version,
        ]);
    }

    /** بازنویسی هزینه‌ها و فیلدها (حذف+ثبت مجدد برای حفظ ترتیب تمیز) */
    private function syncChildren(Service $service, array $data): void
    {
        $service->costs()->delete();
        foreach ($data['costs'] as $c) {
            $service->costs()->create([
                'type' => $c['type'],
                'title' => $c['title'],
                'amount' => (float) $c['amount'],
                'is_commission' => $c['is_commission'],
                'note' => $c['note'],
            ]);
        }

        // فیلدها: رابطه مدل قید is_active دارد → حذف خام برای پوشش همه
        \App\Models\ServiceFormField::where('service_id', $service->id)->delete();
        foreach ($data['fields'] as $i => $f) {
            \App\Models\ServiceFormField::create([
                'service_id' => $service->id,
                'field_type' => $f['field_type'],
                'label' => $f['label'],
                'name' => $f['name'],
                'placeholder' => $f['placeholder'] ?? null,
                'help_text' => $f['help_text'] ?? null,
                'is_required' => $f['is_required'],
                'options' => in_array($f['field_type'], ['select', 'radio', 'checkbox'], true)
                    ? $f['options']
                    : null,
                'validation' => null,
                'sort' => $i,
                'is_active' => $f['is_active'],
            ]);
        }
    }

    /** فعال/غیرفعال سریع (AJAX) */
    public function toggle(Service $service): JsonResponse
    {
        $service->update(['is_active' => ! $service->is_active]);

        AuditLogger::log('service.toggled', $service,
            ['is_active' => ! $service->is_active],
            ['is_active' => $service->is_active],
            ($service->is_active ? 'فعال‌سازی' : 'غیرفعال‌سازی').' خدمت «'.$service->name.'»');

        return response()->json([
            'message' => $service->is_active
                ? 'خدمت فعال شد.'
                : 'خدمت غیرفعال شد (از کاتالوگ مشتریان پنهان می‌شود).',
            'is_active' => $service->is_active,
        ]);
    }

    /** حذف — فقط وقتی سفارشی ثبت نشده باشد (AJAX) */
    public function destroy(Service $service): JsonResponse
    {
        if ($service->orders()->exists()) {
            return response()->json([
                'message' => 'برای این خدمت سفارش ثبت شده است؛ حذف ممکن نیست. می‌توانید آن را غیرفعال کنید.',
            ], 422);
        }

        $old = $service->only(['name', 'slug', 'base_price']);
        $name = $service->name;
        $service->delete();

        AuditLogger::log('service.deleted', null, $old, null, 'حذف خدمت «'.$name.'»');

        return response()->json(['message' => 'خدمت «'.$name.'» حذف شد.']);
    }

    /** تاریخچه نسخه‌ها (AJAX — مودال) */
    public function versions(Service $service): JsonResponse
    {
        $versions = $service->versions()
            ->with('loggedBy:id,name,family')
            ->orderByDesc('version')
            ->limit(50)
            ->get()
            ->map(fn ($v) => [
                'version' => (int) $v->version,
                'created_by' => $v->loggedBy?->name
                    ? trim($v->loggedBy->name.' '.($v->loggedBy->family ?? ''))
                    : 'سیستم',
                'created_at' => $v->created_at?->format('Y-m-d H:i')
                    ? jdate($v->created_at)->format('Y/m/d H:i')
                    : '—',
                'base_price' => (float) data_get($v->snapshot, 'base_price', 0),
                'costs_count' => count(data_get($v->snapshot, 'costs', [])),
                'fields_count' => count(data_get($v->snapshot, 'form_fields', [])),
                'commissionable' => (float) collect(data_get($v->snapshot, 'costs', []))
                    ->where('is_commission', true)->sum('amount'),
            ]);

        return response()->json(['versions' => $versions]);
    }

    /** لیست مسطح دسته‌ها برای سلکت‌ها (والد ▸ فرزند) */
    private function flatCategories(): array
    {
        $all = ServiceCategory::orderBy('sort')->orderBy('id')->get(['id', 'name', 'parent_id', 'is_active']);

        $flat = [];
        foreach ($all->where('parent_id', null) as $root) {
            $flat[] = ['id' => $root->id, 'name' => $root->name, 'is_active' => $root->is_active, 'depth' => 0];
            foreach ($all->where('parent_id', $root->id) as $child) {
                $flat[] = ['id' => $child->id, 'name' => '— '.$child->name, 'is_active' => $child->is_active, 'depth' => 1];
            }
        }

        return $flat;
    }

    /**
     * فاز ۱۵ — دریافت تصاویر خدمت (multipart): تصویر اصلی + تصویر آلرت.
     * فایل جدید = جایگزینی (قدیمی حذف می‌شود)؛ remove_* = حذف بدون جایگزین.
     */
    private function receiveServiceImages(Request $request, Service $service): void
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        // تصویر اصلی
        if ($request->hasFile('image')) {
            if ($service->image_path) {
                $disk->delete($service->image_path);
            }
            $service->image_path = $request->file('image')->store('services', 'public');
            $service->save();
        } elseif (filter_var($request->input('remove_image', false), FILTER_VALIDATE_BOOLEAN) && $service->image_path) {
            $disk->delete($service->image_path);
            $service->image_path = null;
            $service->save();
        }

        // تصویر آلرت
        if ($request->hasFile('alert_image')) {
            if ($service->alert_image_path) {
                $disk->delete($service->alert_image_path);
            }
            $service->alert_image_path = $request->file('alert_image')->store('services', 'public');
            $service->save();
        } elseif (filter_var($request->input('remove_alert_image', false), FILTER_VALIDATE_BOOLEAN) && $service->alert_image_path) {
            $disk->delete($service->alert_image_path);
            $service->alert_image_path = null;
            $service->save();
        }

        // آلرت تصویری بدون تصویر؟ → آلرت نامعتبر
        if ($service->alert_type === 'image' && ! $service->alert_image_path) {
            $service->alert_type = null;
            $service->save();
        }
    }

    /** slug یکتا — نام فارسی به شناسه کوتاه تبدیل می‌شود */
    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug(Str::ascii($name));
        if ($slug === '' || $slug === null) {
            $slug = 's-'.strtolower(Str::random(6));
        }

        $base = $slug;
        $i = 1;
        while (Service::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
