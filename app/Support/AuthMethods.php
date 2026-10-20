<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * 🌟 SITE-2: روش‌های ورود و ثبت‌نامِ فعال (پنل ← «تنظیماتِ سایت» ← «ورود و ثبت‌نام»).
 * سایت و API هر دو از این‌جا می‌پرسند؛ اپ همین را از GET /api/app/config می‌گیرد.
 *
 * - موبایل خاموش: نه ورود و نه ثبت‌نام با کدِ پیامکی (پیامکی هم فرستاده نمی‌شود).
 * - ایمیل خاموش: نه ورود و نه ثبت‌نام با ایمیل — **جز ادمین‌ها**، تا ادمین هیچ‌وقت پشتِ در نماند.
 * - ثبت‌نام خاموش: کاربرانِ موجود وارد می‌شوند، حسابِ تازه ساخته نمی‌شود.
 */
final class AuthMethods
{
    public const MOBILE = 'mobile';
    public const EMAIL = 'email';

    public static function mobile(): bool
    {
        return (bool) config('site.auth.mobile_enabled', true);
    }

    public static function email(): bool
    {
        return (bool) config('site.auth.email_enabled', true);
    }

    public static function registration(): bool
    {
        return (bool) config('site.auth.registration_enabled', true);
    }

    /** 🌟 SITE-12: آیا ورودِ سایت «مرا به خاطر بسپار» دارد (ماندن در حساب بعد از پایانِ نشست)؟ */
    public static function remember(): bool
    {
        return (bool) config('site.auth.remember', true);
    }

    /** ورودِ ایمیلی برای این کاربر مجاز است؟ (ادمین همیشه) */
    public static function emailAllowedFor(?User $user): bool
    {
        return self::email() || ($user && $user->is_admin);
    }

    public static function disabledMessage(string $method): string
    {
        return (string) ($method === self::MOBILE
            ? config('site.auth.mobile_disabled_message', 'ورود و ثبت‌نام با شماره‌ی موبایل فعلاً فعال نیست.')
            : config('site.auth.email_disabled_message', 'ورود و ثبت‌نام با ایمیل فعلاً فعال نیست.'));
    }

    public static function registrationClosedMessage(): string
    {
        return (string) config('site.auth.registration_disabled_message', 'ثبت‌نامِ حسابِ تازه فعلاً بسته است.');
    }

    /** پاسخِ ۴۰۳ِ API برای روشِ خاموش. */
    public static function disabledResponse(string $method): JsonResponse
    {
        return response()->json([
            'success' => false,
            'reason' => 'method_disabled',
            'method' => $method,
            'message' => self::disabledMessage($method),
        ], 403);
    }

    public static function registrationClosedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'reason' => 'registration_closed',
            'message' => self::registrationClosedMessage(),
        ], 403);
    }

    /** خلاصه برای اپ و صفحه‌ها. */
    public static function toArray(): array
    {
        return [
            'mobile' => self::mobile(),
            'email' => self::email(),
            'registration' => self::registration(),
            'messages' => [
                'mobile' => self::disabledMessage(self::MOBILE),
                'email' => self::disabledMessage(self::EMAIL),
                'registration' => self::registrationClosedMessage(),
            ],
            'otp_length' => (int) config('sms.otp.length', 5),
            'password_hint' => PasswordRules::hint(),
        ];
    }
}
