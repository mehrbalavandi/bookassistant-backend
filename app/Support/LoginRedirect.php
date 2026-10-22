<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * 🌟 SITE-10: بعد از تمام شدنِ نشست، صفحه‌ی ورود می‌آید؛ اگر همان کاربرِ قبلی وارد شد، از همان
 * صفحه‌ای که بود ادامه می‌دهد، وگرنه به داشبوردِ خودش (route('home')؛ ادمین ← /admin) می‌رود.
 *
 * «کاربرِ قبلی» را کوکیِ رمزشده‌ی last_login نگه می‌دارد: با هر ورود (رویدادِ Login، از هر راهی:
 * ایمیل، پیامک، صفحه‌ی ورودِ پنل، remember-me) شناسه‌ی کاربر در آن نوشته می‌شود و با خروجِ عمدی
 * (رویدادِ Logout) پاک می‌شود. پس اگر هنگامِ ورود این کوکی هست، نشستِ قبلی بی‌خروج تمام شده است.
 *
 * session('url.intended') را لاراول وقتی مهمان به صفحه‌ی محافظت‌شده می‌رسد می‌گذارد
 * (redirect()->guest). قاعده:
 * - کوکی نیست (خروجِ عمدی یا اولین ورود در این مرورگر) ← همان صفحه (مثلاً «خرید» قبل از ورود)
 * - کوکی همین کاربر است ← همان صفحه (ادامه‌ی نشستِ قبلی)
 * - کوکی کاربرِ دیگری است ← آن صفحه کنار می‌رود و داشبورد
 * - صفحه‌ی پنلِ ادمین برای کاربرِ غیرادمین هیچ‌وقت (جز ۴۰۳ چیزی ندارد) ← داشبورد
 */
class LoginRedirect
{
    public const COOKIE = 'last_login';

    private const MINUTES = 60 * 24 * 365 * 5;

    public static function previousUserId(Request $request): ?int
    {
        $id = $request->cookie(self::COOKIE);

        return is_numeric($id) ? (int) $id : null;
    }

    /** آیا این صفحه‌ی ورود بعد از تمام شدنِ یک نشست (بی‌خروج) آمده است؟ */
    public static function sessionEnded(Request $request): bool
    {
        return $request->session()->has('url.intended') && self::previousUserId($request) !== null;
    }

    /**
     * 🌟 SITE-16: نشانیِ نسبیِ url.intended (مسیر + کوئری) برای فیلدِ پنهانِ فرم‌های ورود — اگر نشستِ
     * مهمان پیش از فرستادن تمام شود، «همان صفحه» از دست نمی‌رود.
     */
    public static function intendedPath(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '') {
            return null;
        }
        $path = (string) (parse_url($intended, PHP_URL_PATH) ?: '/');
        $query = parse_url($intended, PHP_URL_QUERY);

        return $path.($query ? '?'.$query : '');
    }

    /** 🌟 SITE-16: url.intended از فیلدِ پنهانِ فرم، اگر نشست آن را ندارد (فقط مسیرِ همین سایت). */
    public static function restoreIntended(Request $request): void
    {
        if ($request->session()->has('url.intended')) {
            return;
        }
        $path = $request->input('intended');
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')
            || str_contains($path, '\\') || strlen($path) > 2000) {
            return;
        }
        $request->session()->put('url.intended', url($path));
    }

    /** مقصدِ بعد از ورودِ موفق. پیش از redirect()->intended(...) صدا زده می‌شود. */
    public static function prepare(Request $request, User $user): void
    {
        $intended = $request->session()->get('url.intended');
        if (! $intended) {
            return;
        }
        $previous = self::previousUserId($request);
        $otherUser = $previous !== null && $previous !== (int) $user->getKey();
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
        $adminOnly = ! $user->is_admin && ($path === '/admin' || str_starts_with($path, '/admin/'));

        if ($otherUser || $adminOnly) {
            $request->session()->forget('url.intended');
        }
    }

    public static function to(Request $request, User $user, ?string $default = null)
    {
        self::prepare($request, $user);

        return redirect()->intended($default ?? route('home'));
    }

    public static function onLogin(Login $event): void
    {
        if ($event->guard === 'web' && $event->user instanceof User) {
            Cookie::queue(self::COOKIE, (string) $event->user->getKey(), self::MINUTES, null, null, null, true, false, 'lax');
        }
    }

    public static function onLogout(Logout $event): void
    {
        if ($event->guard === 'web') {
            Cookie::queue(Cookie::forget(self::COOKIE));
        }
    }
}
