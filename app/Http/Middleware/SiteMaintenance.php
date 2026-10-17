<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🌟 SITE-4: حالتِ تعمیر از پنل («تنظیماتِ سایت» ← «اطلاعیه و تعمیر»).
 * کاربرانِ عادی در سایت صفحه‌ی «در حالِ به‌روزرسانی» و در API پاسخِ ۵۰۳ با reason=maintenance
 * می‌گیرند. باز می‌مانند: پنلِ ادمین (گروهِ میدل‌ویرِ خودش را دارد)، ورود و خروج (تا ادمین وارد
 * شود)، برگشت از درگاه (پرداختِ نیمه‌کاره گم نشود)، تنظیماتِ اپ و بررسیِ نسخه، و خودِ ادمین‌ها.
 */
class SiteMaintenance
{
    private const OPEN_PATHS = [
        'login', 'login/*', 'logout', 'up',
        'payment/callback/*',
        'api/app/config', 'api/app/latest',
        'api/login', 'api/logout',
        'admin', 'admin/*', 'livewire/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('site.maintenance.enabled') || $request->is(...self::OPEN_PATHS)) {
            return $next($request);
        }
        $user = $request->user() ?? auth('sanctum')->user();
        if ($user && $user->is_admin) {
            return $next($request);
        }

        $message = (string) config('site.maintenance.message', 'سایت در حالِ به‌روزرسانی است.');
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['success' => false, 'reason' => 'maintenance', 'message' => $message], 503)
                ->header('Retry-After', '600');
        }

        return response()->view('errors.maintenance', ['message' => $message], 503)->header('Retry-After', '600');
    }
}
