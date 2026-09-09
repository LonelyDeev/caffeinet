<?php

namespace App\Services\Catalog;

use App\Models\Service;
use App\Models\ServiceVersion;
use Illuminate\Support\Facades\DB;

/**
 * مدیریت نسخه‌های خدمت — هر تغییر در داده‌های «مؤثر بر سفارش»
 * (قیمت، هزینه‌ها، فیلدهای فرم) یک snapshot جدید می‌سازد؛
 * سفارش‌ها (فاز ۵) به نسخهٔ ثبت‌شده اشاره می‌کنند تا قیمت/فرم
 * حین سفارش فریز شود.
 */
class ServiceVersionManager
{
    /** فقط داده‌های مؤثر بر سفارش — تغییرات متنی (نام/توضیحات) نسخه جدید نمی‌سازند */
    public function snapshot(Service $service): array
    {
        return [
            'base_price' => (float) $service->base_price,
            'estimated_time' => (int) $service->estimated_time,
            'requires_upload' => (bool) $service->requires_upload,
            'requires_verification' => (bool) $service->requires_verification,
            'costs' => $service->costs()->orderBy('id')
                ->get()
                ->map(fn ($c) => [
                    'type' => $c->type,
                    'title' => $c->title,
                    'amount' => (float) $c->amount,
                    'is_commission' => (bool) $c->is_commission,
                    'note' => $c->note,
                ])->all(),
            'form_fields' => $service->formFields()->get()
                ->map(fn ($f) => [
                    'field_type' => $f->field_type,
                    'label' => $f->label,
                    'name' => $f->name,
                    'placeholder' => $f->placeholder,
                    'help_text' => $f->help_text,
                    'is_required' => (bool) $f->is_required,
                    'options' => $f->options,
                    'validation' => $f->validation,
                    'sort' => (int) $f->sort,
                ])->all(),
        ];
    }

    /**
     * اگر داده‌های مؤثر بر سفارش تغییر کرده باشند نسخه جدید ثبت می‌شود.
     * خروجی: ServiceVersion جدید یا null (بدون تغییر).
     */
    public function sync(Service $service): ?ServiceVersion
    {
        $snapshot = $this->snapshot($service);

        /** @var ServiceVersion|null $latest */
        $latest = $service->versions()->orderByDesc('version')->first();

        if ($latest && $this->normalize($latest->snapshot) === $this->normalize($snapshot)) {
            return null;
        }

        return DB::transaction(function () use ($service, $snapshot, $latest) {
            $version = ($latest?->version ?? 0) + 1;

            /** @var ServiceVersion $created */
            $created = $service->versions()->create([
                'version' => $version,
                'snapshot' => $snapshot,
                'created_by' => auth()->id(),
            ]);

            $service->forceFill(['version' => $version])->saveQuietly();

            return $created;
        });
    }

    /**
     * یکسان‌سازی تایپ داده‌ها برای مقایسه === :
     * decode JSON اعداد بدون ممیز را int برمی‌گرداند ولی snapshot تازه float می‌سازد؛
     * مقایسه سخت‌گیر آرایه‌ای تایپ‌ها را هم مقایسه می‌کند → باید یکدست شوند.
     */
    private function normalize(array $s): array
    {
        $s['base_price'] = isset($s['base_price']) ? (float) $s['base_price'] : null;
        $s['estimated_time'] = isset($s['estimated_time']) ? (int) $s['estimated_time'] : null;
        $s['requires_upload'] = (bool) ($s['requires_upload'] ?? false);
        $s['requires_verification'] = (bool) ($s['requires_verification'] ?? false);

        $s['costs'] = array_values(array_map(function ($c) {
            $c['amount'] = isset($c['amount']) ? (float) $c['amount'] : null;
            $c['is_commission'] = (bool) ($c['is_commission'] ?? false);

            return $c;
        }, $s['costs'] ?? []));

        $s['form_fields'] = array_values(array_map(function ($f) {
            $f['is_required'] = (bool) ($f['is_required'] ?? false);
            $f['sort'] = isset($f['sort']) ? (int) $f['sort'] : 0;

            return $f;
        }, $s['form_fields'] ?? []));

        return $s;
    }
}
