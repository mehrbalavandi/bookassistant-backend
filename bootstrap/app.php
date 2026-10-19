<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 🌟 PAY-2: درگاه نتیجه را با POST برمی‌گرداند و توکنِ CSRFِ ما را ندارد.
        $middleware->validateCsrfTokens(except: ['payment/callback/*']);
        // 🌟 SITE-8: سرآیندهای امنیتی روی همه‌چیز (پنلِ ادمین هم)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // 🌟 SITE-4: حالتِ تعمیر برای سایت و API (پنلِ ادمین گروهِ میدل‌ویرِ خودش را دارد)
        $middleware->appendToGroup('web', \App\Http\Middleware\SiteMaintenance::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\SiteMaintenance::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        // 🌟 SITE-9: فرمِ سایت با توکنِ CSRFِ کهنه (صفحه مدت‌ها باز مانده، یا نشست تمام شده):
        // به‌جای صفحه‌ی «419 | PAGE EXPIRED»، برگشت به همان فرم با مقدارهای واردشده و یک پیام.
        // (پنلِ ادمین ۴۱۹ِ Livewire را خودش با بارگذاریِ دوباره‌ی صفحه حل می‌کند.)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->is('api/*') || $request->expectsJson()
                || $request->hasHeader('X-Livewire')) {
                return null;
            }
            $back = url()->previous();
            if (! $back || $back === $request->fullUrl()) {
                return null; // صفحه‌ی errors/419
            }

            return redirect()->to($back)
                ->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']))
                ->withErrors(['session' => 'مهلتِ صفحه تمام شده بود. لطفاً دوباره بفرستید.']);
        });
        // 🌟 DEV-1: ۴۰۱ِ API دلیل را هم می‌گوید (device_revoked، device_mismatch، unbound، …)
        // تا اپ بداند فقط دوباره وارد شود یا محتوای کاملِ کتاب‌ها را هم پاک کند.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $reason = $request->attributes->get(\App\Services\DeviceService::DENIED_ATTRIBUTE);

            return response()->json([
                'message' => \App\Services\DeviceService::deniedMessage($reason),
                'reason' => $reason ?? 'unauthenticated',
            ], 401);
        });
    })->create();
