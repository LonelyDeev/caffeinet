<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * گارد پیش‌فرض درخواست‌های API → sanctum.
 *
 * تا وقتی auth()->id() / auth()->user() در سرویس‌های مشترک
 * (WalletService، AuditLogger و…) در زمینه API هم کار کند.
 */
class ApiDefaultGuard
{
    public function __construct(protected AuthManager $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $this->auth->setDefaultDriver('sanctum');
        }

        return $next($request);
    }
}
