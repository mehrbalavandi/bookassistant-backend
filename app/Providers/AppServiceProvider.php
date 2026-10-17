<?php

namespace App\Providers;

use App\Services\DeviceService;
use App\Services\Sms\KavenegarSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsIrSmsSender;
use App\Services\Sms\SmsSender;
use App\Support\Mobile;
use App\Support\SiteSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 🌟 AUTH-3: سامانه‌ی پیامک از روی SMS_DRIVER (log | kavenegar | smsir).
        $this->app->singleton(SmsSender::class, function () {
            return match (config('sms.driver')) {
                'kavenegar' => new KavenegarSmsSender(
                    (string) config('sms.kavenegar.api_key'),
                    (array) config('sms.kavenegar.templates'),
                ),
                'smsir' => new SmsIrSmsSender(
                    (string) config('sms.smsir.api_key'),
                    (array) config('sms.smsir.templates'),
                ),
                default => new LogSmsSender,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🌟 SITE-1: مقدارهایی که ادمین در «تنظیماتِ سایت» عوض کرده روی config نوشته می‌شوند
        // (پیش از هر چیز، تا پیامک، پرداخت، دستگاه و ورود همه از همان پیروی کنند).
        SiteSettings::apply();

        // 🌟 AUTH-2: سقفِ درخواستِ کد — هم به‌ازای IP و هم به‌ازای شماره (جلوی هزینه‌تراشی
        // با پیامک و حدس‌زدنِ کد را می‌گیرد). تأییدِ کد هم سقفِ جدا دارد.
        // 🌟 DEV-1: هر توکنِ API فقط از دستگاهی که به آن گره خورده و هنوز فعال است پذیرفته
        // می‌شود (هدرِ X-Device-Id). این‌جاست تا هیچ مسیری — حتی مسیرهای عمومی‌ای که
        // auth('sanctum')->user() را می‌خوانند — بی‌این وارسی نماند.
        Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $isValid) => $isValid
                && app(DeviceService::class)->tokenAllowed($token, request()),
        );

        RateLimiter::for('otp-request', function (Request $request) {
            $mobile = Mobile::normalize($request->input('mobile')) ?? 'invalid';

            return [
                Limit::perMinute(5)->by('ip:'.$request->ip()),
                Limit::perHour(20)->by('ip-h:'.$request->ip()),
                Limit::perHour(6)->by('m:'.$mobile),
            ];
        });
        RateLimiter::for('otp-verify', function (Request $request) {
            return [
                Limit::perMinute(10)->by('ip:'.$request->ip()),
            ];
        });
        // 🌟 SITE-6: ورود/ثبت‌نام با ایمیل و فراموشیِ رمز — جلوی حدس‌زدنِ رمز و ارسالِ انبوهِ ایمیل.
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('le:'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by('li:'.$request->ip()),
            ];
        });
        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinute(3)->by('pr:'.$request->ip()),
                Limit::perHour(10)->by('prh:'.mb_strtolower(trim((string) $request->input('email')))),
            ];
        });

        // 🌟 SITE-6: ایمیلِ بازیابیِ رمز به فارسی
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
            $site = (string) config('site.name');

            return (new MailMessage)
                ->subject('بازیابیِ رمزِ عبور — '.$site)
                ->greeting('سلام!')
                ->line('درخواستِ بازیابیِ رمزِ حسابِ شما در '.$site.' ثبت شد.')
                ->action('ساختنِ رمزِ تازه', $url)
                ->line('این لینک تا '.$minutes.' دقیقه معتبر است.')
                ->line('اگر شما درخواست نداده‌اید، این ایمیل را نادیده بگیرید؛ رمزتان عوض نمی‌شود.')
                ->salutation('با احترام، '.$site);
        });
    }
}
