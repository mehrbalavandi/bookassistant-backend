<?php

namespace App\Services\Sms;

/** ارسالِ پیامک با قالب‌های از پیش تأییدشده‌ی سامانه (AUTH-3). */
interface SmsSender
{
    /** کدِ ورود. استثنا اگر ارسال نشد. */
    public function sendOtp(string $mobile, string $code): void;

    /**
     * پیامکِ قالب‌دار (مثلاً «سفارش تأیید شد»). اگر قالبی برای این کلید تنظیم نشده،
     * بی‌صدا هیچ کاری نمی‌کند — اطلاع‌رسانی اختیاری است.
     *
     * @param  array<string, string>  $params  به ترتیب: token، token2، token3 (کاوه‌نگار) یا نام‌دار (sms.ir)
     */
    public function sendTemplate(string $mobile, string $templateKey, array $params): void;
}
