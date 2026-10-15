<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * کاوه‌نگار — «ارسالِ سریع با قالب» (verify/lookup).
 * مستندات: https://kavenegar.com/rest.html#sms-Lookup
 * توکن‌ها فاصله نمی‌پذیرند (جز token10/token20)؛ فاصله‌ها با «‌_» جایگزین می‌شوند.
 */
class KavenegarSmsSender implements SmsSender
{
    public function __construct(private readonly string $apiKey, private readonly array $templates) {}

    public function sendOtp(string $mobile, string $code): void
    {
        $this->lookup($mobile, $this->templates['otp'] ?? 'login-otp', ['token' => $code]);
    }

    public function sendTemplate(string $mobile, string $templateKey, array $params): void
    {
        $template = $this->templates[$templateKey] ?? null;
        if (! $template) {
            return;
        }
        $tokens = [];
        $names = ['token', 'token2', 'token3'];
        foreach (array_values($params) as $i => $value) {
            if (! isset($names[$i])) {
                break;
            }
            $tokens[$names[$i]] = str_replace(' ', '_', mb_substr((string) $value, 0, 100));
        }
        $this->lookup($mobile, $template, $tokens);
    }

    private function lookup(string $mobile, string $template, array $tokens): void
    {
        $response = Http::timeout(10)->asForm()->post(
            "https://api.kavenegar.com/v1/{$this->apiKey}/verify/lookup.json",
            array_merge(['receptor' => $mobile, 'template' => $template], $tokens),
        );
        $status = $response->json('return.status');
        if (! $response->ok() || (int) $status !== 200) {
            throw new RuntimeException('Kavenegar: '.($response->json('return.message') ?? $response->status()));
        }
    }
}
