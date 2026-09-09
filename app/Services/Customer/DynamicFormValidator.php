<?php

namespace App\Services\Customer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * اعتبارسنجی فرم داینامیک خدمت — بر اساس snapshot نسخه (فریزشده در لحظه سفارش).
 *
 * خطاها با کلیدِ «نام فنی فیلد» برگردانده می‌شوند تا کلاینت مستقیماً
 * روی فیلد مربوطه نمایش دهد.
 */
class DynamicFormValidator
{
    /**
     * @param  array  $fields  فیلدهای snapshot (form_fields)
     * @param  array  $formData  پاسخ‌های مشتری
     * @return array داده‌های نرمال‌شده
     *
     * @throws ValidationException
     */
    public function validate(array $fields, array $formData): array
    {
        $data = [];
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            $label = (string) ($field['label'] ?? $name);
            $type = (string) ($field['field_type'] ?? 'text');
            $required = (bool) ($field['is_required'] ?? false);
            $options = is_array($field['options'] ?? null) ? $field['options'] : [];
            $extra = is_array($field['validation'] ?? null) ? $field['validation'] : [];

            if ($name === '') {
                continue;
            }

            // فایل‌ها جداگانه اعتبارسنجی می‌شوند (multipart)
            if ($type === 'file') {
                $data[$name] = null;
                continue;
            }

            $value = $formData[$name] ?? null;
            $value = $this->normalize($type, $value, $options);
            $data[$name] = $value;

            $rules[$name] = $this->buildRules($type, $required, $options, $extra, $name);
            $attributes[$name] = $label;
        }

        $validator = Validator::make($data, $rules, $this->messages(), $attributes);

        if ($validator->fails()) {
            // کلید خطا = نام فنی فیلد (مستقیم قابل استفاده در کلاینت)
            throw new ValidationException($validator);
        }

        // مقدار خالیِ اختیاری حذف شود
        return array_filter($data, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** نرمال‌سازی مقدار بر اساس نوع */
    protected function normalize(string $type, mixed $value, array $options): mixed
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'mobile':
            case 'national_code':
            case 'number':
                if (is_string($value)) {
                    $value = en_digits(trim($value));
                    $value = str_replace([',', '،'], '', $value);
                }

                return $value === '' ? null : $value;

            case 'date':
                if (is_string($value) && $value !== '') {
                    return $this->normalizeDate($value);
                }

                return null;

            case 'checkbox':
                if (is_string($value) && $value !== '') {
                    return [$value];
                }

                return $value;

            case 'text':
            case 'textarea':
            case 'email':
            case 'select':
            case 'radio':
            default:
                if (is_string($value)) {
                    $value = trim($value);

                    return $value === '' ? null : $value;
                }

                return $value;
        }
    }

    /** تاریخ شمسی یا میلادی → Y-m-d */
    protected function normalizeDate(string $value): string
    {
        $value = trim(en_digits($value));

        foreach (['Y/m/d', 'Y-m-d', 'Y.m.d'] as $format) {
            try {
                return \Morilog\Jalali\Jalalian::fromFormat($format, $value)->toCarbon()->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        // فرمت میلادی یا هر ورودی دیگر — اعتبارسنجی date_format خودش خطا می‌دهد
        return $value;
    }

    /** قواعد اعتبارسنجی بر اساس نوع + قواعد اضافی فرم‌ساز */
    protected function buildRules(string $type, bool $required, array $options, array $extra, string $name): array
    {
        $rules = [];

        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        switch ($type) {
            case 'text':
                $rules[] = 'string';
                $rules[] = 'max:255';
                break;

            case 'textarea':
                $rules[] = 'string';
                $rules[] = 'max:2000';
                break;

            case 'number':
                $rules[] = 'numeric';
                $rules[] = 'min:'.(isset($extra['min']) ? (float) $extra['min'] : 0);
                $rules[] = 'max:999999999999';
                break;

            case 'mobile':
                $rules[] = 'regex:/^09\d{9}$/';
                $rules[] = 'digits:11';
                break;

            case 'national_code':
                $rules[] = 'digits:10';
                $rules[] = function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && $value !== '' && ! $this->validNationalCode((string) $value)) {
                        $fail('کد ملی واردشده معتبر نیست.');
                    }
                };
                break;

            case 'email':
                $rules[] = 'email:filter';
                $rules[] = 'max:190';
                break;

            case 'date':
                $rules[] = 'date_format:Y-m-d';
                break;

            case 'select':
            case 'radio':
                if ($options !== []) {
                    $rules[] = Rule::in(array_map('strval', $options));
                }
                break;

            case 'checkbox':
                $rules[] = 'array';
                if ($options !== []) {
                    $rules[] = Rule::in(array_map('strval', $options));
                }
                break;
        }

        // قواعد اضافی فرم‌ساز (min / max / pattern) — فقط برای انواع متنی/عددی
        if (in_array($type, ['text', 'textarea', 'number']) && $extra !== []) {
            $mapped = [];
            foreach ($rules as $r) {
                if (is_string($r) && str_starts_with($r, 'min:')) {
                    continue; // قاعده پیش‌فرض min جایگزین می‌شود
                }
                $mapped[] = $r;
            }

            if (isset($extra['min'])) {
                $mapped[] = 'min:'.(float) $extra['min'];
            }
            if (isset($extra['max'])) {
                $mapped[] = 'max:'.(float) $extra['max'];
            }
            if (! empty($extra['pattern'])) {
                $mapped[] = 'regex:'.$extra['pattern'];
            }

            $rules = $mapped;
        }

        return $rules;
    }

    /** پیام‌های فارسی */
    protected function messages(): array
    {
        return [
            'required' => 'فیلد «:attribute» الزامی است.',
            'string' => '«:attribute» باید متن باشد.',
            'numeric' => '«:attribute» باید عدد باشد.',
            'array' => '«:attribute» باید انتخاب(های) multiples باشد.',
            'email' => 'ایمیل واردشده در «:attribute» معتبر نیست.',
            'date_format' => 'تاریخ «:attribute» معتبر نیست (نمونه: ۱۴۰۰/۰۵/۱۲).',
            'digits' => '«:attribute» باید :digits رقم باشد.',
            'regex' => 'قالب «:attribute» معتبر نیست.',
            'min' => '«:attribute» نباید کمتر از :min باشد.',
            'max' => '«:attribute» نباید بیشتر از :max باشد.',
            'in' => 'انتخاب «:attribute» معتبر نیست.',
        ];
    }

    /** اعتبارسنجی الگوریتمی کد ملی ایران */
    public function validNationalCode(string $code): bool
    {
        $code = en_digits(trim($code));

        if (! preg_match('/^\d{10}$/', $code)) {
            return false;
        }

        // همه ارقام یکسان (مثل ۱۱۱۱۱۱۱۱۱۱) نامعتبر
        if (preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $code[$i]) * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $code[9];

        return $remainder < 2 ? ($check === $remainder) : ($check === 11 - $remainder);
    }
}
