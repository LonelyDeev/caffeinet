<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Models\Coffeenet;
use Illuminate\Http\Request;

/**
 * دسترسی به «کافی‌نت جاری» که میدل‌ور EnsureCoffeenetContext تعیین کرده است.
 */
trait WorksInCoffeenet
{
    protected function currentCoffeenet(Request $request): Coffeenet
    {
        $coffeenet = $request->attributes->get('current_coffeenet');

        abort_unless($coffeenet instanceof Coffeenet, 403, 'زمینه کافی‌نت معتبر نیست.');

        return $coffeenet;
    }
}
