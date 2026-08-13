<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\AuthenticateSession as FilamentAuthenticateSession;

/**
 * 🌟 همتای AdminAuthenticate برای مسیرِ دومِ منقضی‌شدنِ جلسه.
 *
 * وقتی رمزِ کاربر عوض می‌شود یا جلسه‌ی دستگاهِ دیگری باطل می‌شود، این میدل‌ویر
 * (نه Authenticate) کاربر را بیرون می‌اندازد و خودش هم آدرسِ ورود را تعیین
 * می‌کند. بدونِ این کلاس، آن مسیر همچنان به ‎/admin/login‎ می‌رفت و نیمی از
 * حالت‌ها اصلاح‌نشده باقی می‌ماند.
 */
class AdminAuthenticateSession extends FilamentAuthenticateSession
{
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
