<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * 🌟 ADM-2: قانونِ رمز برای ثبت‌نامِ خودِ کاربران (اپ و سایت) — یک‌جا.
 *   PASSWORD_MODE=strong (پیش‌فرض) → حداقل ۸ کاراکتر + حروفِ بزرگ و کوچک + عدد + نماد
 *   PASSWORD_MODE=lax                → هر رمزی (فقط برای تست)
 * رمزی که ادمین در پنل می‌گذارد مشمولِ این قانون نیست (حتی یک‌رقمی مجاز است).
 */
final class PasswordRules
{
    public static function forUsers(): Password
    {
        if (env('PASSWORD_MODE', 'strong') === 'lax') {
            return Password::min(1);
        }

        return Password::min(8)->mixedCase()->numbers()->symbols();
    }
}
