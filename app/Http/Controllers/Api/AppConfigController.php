<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AuthMethods;
use Illuminate\Http\JsonResponse;

/**
 * 🌟 SITE-7: GET /api/app/config — آنچه اپ پیش از ورود لازم دارد و ادمین در پنل تعیین می‌کند:
 * روش‌های ورود، اطلاعیه، حالتِ تعمیر، پشتیبانی و پیوندهای صفحه‌های سایت. در حالتِ تعمیر هم پاسخ
 * می‌دهد (اپ پیامِ تعمیر را از همین می‌خواند).
 */
class AppConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $announcement = config('site.announcement.enabled') && trim((string) config('site.announcement.text')) !== ''
            ? ['text' => (string) config('site.announcement.text'), 'level' => (string) config('site.announcement.level', 'info')]
            : null;

        return response()->json([
            'success' => true,
            'site' => [
                'name' => (string) config('site.name'),
                'tagline' => (string) config('site.tagline'),
            ],
            'auth' => AuthMethods::toArray(),
            'announcement' => $announcement,
            'maintenance' => config('site.maintenance.enabled')
                ? ['message' => (string) config('site.maintenance.message')]
                : null,
            'support' => array_filter((array) config('site.support', []), fn ($v) => (string) $v !== ''),
            'links' => [
                'site' => url('/'),
                'about' => route('page', 'about'),
                'terms' => route('page', 'terms'),
                'privacy' => route('page', 'privacy'),
                'contact' => route('contact'),
                'forgot_password' => route('password.request'),
            ],
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
