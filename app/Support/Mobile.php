<?php

namespace App\Support;

/** شماره‌ی موبایلِ ایران به شکلِ یکسانِ 09xxxxxxxxx (AUTH-1). */
final class Mobile
{
    /** null اگر شماره‌ی موبایلِ معتبرِ ایران نیست. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        // ارقامِ فارسی و عربی ← لاتین؛ هر چیزِ غیرِ رقم حذف.
        $s = strtr($raw, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $s = preg_replace('/\D+/', '', $s) ?? '';
        if (str_starts_with($s, '0098')) {
            $s = '0'.substr($s, 4);
        } elseif (str_starts_with($s, '98') && strlen($s) === 12) {
            $s = '0'.substr($s, 2);
        } elseif (strlen($s) === 10 && str_starts_with($s, '9')) {
            $s = '0'.$s;
        }

        return preg_match('/^09\d{9}$/', $s) ? $s : null;
    }

    /** نمایشِ پوشیده: 0912***4567 */
    public static function mask(string $mobile): string
    {
        return substr($mobile, 0, 4).'***'.substr($mobile, -4);
    }
}
