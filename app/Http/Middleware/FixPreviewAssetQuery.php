<?php

namespace App\Http\Middleware;

use App\Support\Gateway;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * اصلاح URLهای استاتیک برای گیت‌وی پیش‌نمایش (Sandbox).
 *
 * الگوی رایج Blade «{{ asset('...') }}?v=NN» به همراه GatewayUrlGenerator
 * URLهایی مثل «…/chat.css?XTransformPort=8000?v=17» تولید می‌کند (دو «؟»).
 * مرورگر مقدار پارامتر را «8000?v=17» می‌فرستد؛ Caddy آپ‌استریم نامعتبر
 * می‌سازد و پاسخ ۵۰۲ می‌شود. این میان‌افزار ترتیب query را در HTML نهایی
 * اصلاح می‌کند: «…/chat.css?v=17&XTransformPort=8000».
 *
 * فقط وقتی گیت‌وی فعال است اجرا می‌شود؛ در پروداکشن بی‌اثر است.
 */
class FixPreviewAssetQuery
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Gateway::active()) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || ! str_contains($content, 'XTransformPort=')) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type', '');
        if (! str_contains($type, 'html')) {
            return $response;
        }

        $port = preg_quote((string) Gateway::port(), '/');

        // «URL?XTransformPort=P?v=V[&…]» → «URL?v=V[&…]&XTransformPort=P»
        $fixed = preg_replace(
            '/\?XTransformPort='.$port.'\?([^"\'\s#]+)/',
            '?$1&XTransformPort='.str_replace('\\', '', $port),
            $content,
            -1,
            $count
        );

        if ($count > 0 && is_string($fixed)) {
            $response->setContent($fixed);
        }

        return $response;
    }
}
