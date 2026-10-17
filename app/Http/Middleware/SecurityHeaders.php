<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🌟 SITE-8: سرآیندهای امنیتیِ پایه برای همه‌ی پاسخ‌ها: جلوی نمایشِ سایت داخلِ iframeِ
 * سایتِ دیگر (clickjacking)، حدس‌زدنِ نوعِ فایل توسطِ مرورگر، و نشت‌کردنِ آدرسِ کامل به سایت‌های
 * دیگر را می‌گیرد. HSTS فقط روی HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff', false);
        $h->set('X-Frame-Options', 'SAMEORIGIN', false);
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', false);
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }
}
