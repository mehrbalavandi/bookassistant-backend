<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * 🌟 درخواستِ کاربر: وقتی جلسه منقضی می‌شود، به‌جای صفحه‌ی ورودِ خودِ فیلامنت
 * (‎/admin/login‎) به صفحه‌ی ورودِ سایت (‎/login‎) هدایت شویم.
 *
 * تنها کاری که لازم است، همین بازنویسیِ redirectTo است: بقیه‌ی رفتارِ
 * میدل‌ویرِ فیلامنت (چکِ canAccessPanel و ...) دست‌نخورده می‌ماند.
 *
 * نکته‌ی «برگشت به همان صفحه»: هندلرِ استثنائاتِ لاراول این آدرس را با
 * redirect()->guest() باز می‌کند و آن هم آدرسِ صفحه‌ی فعلی را در
 * session('url.intended') ذخیره می‌کند. WebAuthController::login بعد از ورودِ
 * موفق با redirect()->intended(...) دقیقاً همان‌جا برمی‌گردد.
 */
class AdminAuthenticate extends FilamentAuthenticate
{
    protected function redirectTo($request): ?string
    {
        // 🌟 SITE-2: وقتی ورودِ ایمیلی در پنل خاموش است، صفحه‌ی ورودِ سایت فرمِ ایمیل ندارد؛
        // پس ادمین به صفحه‌ی ورودِ خودِ پنل (‎/admin/login‎) می‌رود که همیشه کار می‌کند.
        // 🌟 SITE-14: حتی آن موقع هم صفحه‌ی ورودِ سایت (‎?for=admin‎)، نه فرمِ Livewireِ پنل.
        return \App\Support\AuthMethods::adminLoginUrl();
    }
}
