<?php

namespace Database\Seeders;

use App\Models\Guide;
use Database\Seeders\Guides\CoffeenetGuides;
use Database\Seeders\Guides\OperatorGuides;
use Database\Seeders\Guides\OrganizationGuides;
use Database\Seeders\Guides\SuperAdminGuides;
use Illuminate\Database\Seeder;

/**
 * فاز ۱۳ — آموزش پنل مبتنی بر نقش.
 *
 * هر کاربر فقط راهنماهای نقش خودش را می‌بیند:
 *   super_admin | organization | coffeenet | operator
 */
class GuideSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = array_merge(
            SuperAdminGuides::definitions(),
            OrganizationGuides::definitions(),
            CoffeenetGuides::definitions(),
            OperatorGuides::definitions(),
        );

        foreach ($definitions as $def) {
            Guide::updateOrCreate(
                ['role' => $def['role'], 'slug' => $def['slug']],
                [
                    'title' => $def['title'],
                    'description' => $def['description'],
                    'icon' => $def['icon'],
                    'sort_order' => $def['sort_order'],
                    'content' => $def['content'],
                    'is_active' => true,
                ],
            );
        }
    }
}
