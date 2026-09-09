<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * فاز ۱۳ — آموزش پنل مبتنی بر نقش.
 *
 * هر رکورد یک «راهنما» برای یک نقش است؛ content JSON شامل بخش‌ها:
 *   [ { h: 'عنوان', body: 'HTML', steps: ['گام ۱', ...] } ]
 */
class Guide extends Model
{
    protected $fillable = [
        'role', 'slug', 'title', 'description', 'icon',
        'sort_order', 'content', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** نقش‌های دارای آموزش */
    public const ROLES = [
        'super_admin' => 'مدیر کل',
        'organization' => 'سازمان',
        'coffeenet' => 'مدیر کافی‌نت',
        'operator' => 'اپراتور',
    ];

    /** بازده بخش‌های محتوا به‌شکل امن */
    public function sections(): array
    {
        $sections = (array) ($this->content ?? []);

        return array_values(array_filter($sections, fn ($s) => is_array($s) && ! empty($s['h'])));
    }

    /** مجموع گام‌های همهٔ بخش‌ها (برای شمارش مراحل) */
    public function stepsCount(): int
    {
        $n = 0;

        foreach ($this->sections() as $s) {
            $n += count((array) ($s['steps'] ?? []));
        }

        return $n;
    }
}
