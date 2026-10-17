<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * 🌟 ADM-2: قانونِ رمز برای ثبت‌نامِ خودِ کاربران (اپ و سایت) — یک‌جا.
 * 🌟 SITE-2: از config('site.auth.password_mode')، که در پنل («ورود و ثبت‌نام») عوض می‌شود:
 *   strong (پیش‌فرض) → حداقل ۸ کاراکتر + حروفِ بزرگ و کوچک + عدد + نماد
 *   medium           → حداقل ۸ کاراکتر + حرف و عدد
 *   lax              → هر رمزی (فقط برای تست)
 * رمزی که ادمین در پنل می‌گذارد مشمولِ این قانون نیست (حتی یک‌رقمی مجاز است).
 */
final class PasswordRules
{
    public static function mode(): string
    {
        return (string) config('site.auth.password_mode', 'strong');
    }

    public static function forUsers(): Password
    {
        return match (self::mode()) {
            'lax' => Password::min(1),
            'medium' => Password::min(8)->letters()->numbers(),
            default => Password::min(8)->mixedCase()->numbers()->symbols(),
        };
    }

    /** توضیحِ کوتاه برای فرم‌های ثبت‌نام (سایت و اپ). */
    public static function hint(): string
    {
        return match (self::mode()) {
            'lax' => '',
            'medium' => 'حداقل ۸ کاراکتر، شاملِ حرف و عدد.',
            default => 'حداقل ۸ کاراکتر، شاملِ حروفِ بزرگ و کوچکِ انگلیسی، عدد و یک نماد (مثلِ ! یا @).',
        };
    }

    /**
     * پیامِ فارسیِ مشکلِ رمز، یا null اگر رمز با قانونِ فعلی جور است. (پیام‌های پیش‌فرضِ لاراول
     * انگلیسی‌اند؛ فرم‌های ثبت‌نام و بازیابی این را به‌جای قانونِ خام به کار می‌برند.)
     */
    public static function problem(?string $password): ?string
    {
        $v = \Illuminate\Support\Facades\Validator::make(['password' => (string) $password], ['password' => [self::forUsers()]]);
        if (! $v->fails()) {
            return null;
        }
        $hint = self::hint();

        return 'رمزِ عبور مناسب نیست'.($hint !== '' ? ': '.$hint : '.');
    }

    /** پیام‌های فارسیِ فیلدهای فرم‌های ورود و ثبت‌نام. */
    public static function messages(): array
    {
        return [
            'required' => 'پرکردنِ «:attribute» لازم است.',
            'required_without' => 'پرکردنِ «:attribute» لازم است.',
            'required_without_all' => 'پرکردنِ «:attribute» لازم است.',
            'email' => 'ایمیل معتبر نیست.',
            'max' => '«:attribute» بیش از اندازه طولانی است.',
            'confirmed' => 'تکرارِ رمز با خودِ رمز یکی نیست.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است؛ وارد شوید یا رمز را بازیابی کنید.',
        ];
    }

    /** نامِ فارسیِ فیلدها برای همان پیام‌ها. */
    public static function attributes(): array
    {
        return [
            'first_name' => 'نام', 'last_name' => 'نام خانوادگی', 'name' => 'نام',
            'email' => 'ایمیل', 'password' => 'رمزِ عبور', 'code' => 'کد', 'mobile' => 'موبایل',
        ];
    }
}
