<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * sms.ir — «ارسالِ وریفای» با قالب.
 * مستندات: https://app.sms.ir/developer/help/verify
 * پارامترِ قالبِ ورود باید CODE باشد؛ قالب‌های دیگر پارامترهای نام‌دارِ خودشان را دارند.
 */
class SmsIrSmsSender implements SmsSender
{
    public function __construct(private readonly string $apiKey, private readonly array $templates) {}

    public function sendOtp(string $mobile, string $code): void
    {
        $this->verify($mobile, (int) ($this->templates['otp'] ?? 0), ['CODE' => $code]);
    }

    public function sendTemplate(string $mobile, string $templateKey, array $params): void
    {
        $id = (int) ($this->templates[$templateKey] ?? 0);
        if ($id <= 0) {
            return;
        }
        $this->verify($mobile, $id, $params);
    }

    private function verify(string $mobile, int $templateId, array $params): void
    {
        $response = Http::timeout(10)
            ->withHeaders(['X-API-KEY' => $this->apiKey, 'Accept' => 'application/json'])
            ->post('https://api.sms.ir/v1/send/verify', [
                'mobile' => $mobile,
                'templateId' => $templateId,
                'parameters' => collect($params)
                    ->map(fn ($v, $k) => ['name' => (string) $k, 'value' => (string) $v])
                    ->values()->all(),
            ]);
        if (! $response->ok() || (int) $response->json('status') !== 1) {
            throw new RuntimeException('sms.ir: '.($response->json('message') ?? $response->status()));
        }
    }
}
