<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** برای توسعه و تست: پیامک فقط در لاگ نوشته می‌شود. */
class LogSmsSender implements SmsSender
{
    public function sendOtp(string $mobile, string $code): void
    {
        Log::info("[SMS:OTP] {$mobile} → {$code}");
    }

    public function sendTemplate(string $mobile, string $templateKey, array $params): void
    {
        Log::info("[SMS:{$templateKey}] {$mobile} → ".json_encode($params, JSON_UNESCAPED_UNICODE));
    }
}
