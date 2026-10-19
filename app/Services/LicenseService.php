<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;

/**
 * 🌟 DEV-4: مجوزِ آفلاینِ امضاشده.
 *
 * اپ نسخه‌ی کاملِ کتاب را فقط با مجوزی باز می‌کند که:
 * - امضایش با کلیدِ عمومیِ داخلِ اپ درست باشد (Ed25519)،
 * - مالِ همین کاربر (uid) و همین دستگاه (did) باشد،
 * - شناسه‌ی کتاب در books باشد، و منقضی نشده باشد.
 *
 * مجوز با هر اتصالِ موفق (ورود، فهرستِ کتاب‌ها، ثبتِ دستگاه) تازه می‌شود. دستگاهی که
 * آزاد شده دیگر مجوزِ تازه نمی‌گیرد، پس حتی اگر هرگز آنلاین نشود حداکثر بعد از
 * `devices.license.days` روز قفل می‌شود.
 *
 * قالب: payload = base64(JSON)، sig = base64(امضای Ed25519ِ همان بایت‌های JSON).
 * اپ دقیقاً همان بایت‌ها را وارسی می‌کند، پس ترتیبِ کلیدها مهم نیست.
 */
class LicenseService
{
    public const VERSION = 1;

    public function enabled(): bool
    {
        return $this->secretKey() !== null;
    }

    /** کلیدِ خصوصیِ ۶۴ بایتیِ sodium، یا null اگر تنظیم نشده یا نامعتبر است. */
    public function secretKey(): ?string
    {
        $raw = config('devices.license.signing_key');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $bytes = base64_decode(trim($raw), true);
        if ($bytes === false) {
            return null;
        }
        if (strlen($bytes) === SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            return sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($bytes));
        }

        return strlen($bytes) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ? $bytes : null;
    }

    /** کلیدِ عمومیِ متناظر (base64) — همان که در اپ گذاشته می‌شود. */
    public function publicKeyBase64(): ?string
    {
        $sk = $this->secretKey();

        return $sk ? base64_encode(sodium_crypto_sign_publickey_from_secretkey($sk)) : null;
    }

    /** @return array{payload:string,sig:string,exp:int}|null */
    public function issue(User $user, UserDevice $device): ?array
    {
        $sk = $this->secretKey();
        if ($sk === null || ! $device->isActive()) {
            return null;
        }

        $now = time();
        $payload = [
            'v' => self::VERSION,
            'uid' => (string) $user->id,
            'did' => $device->device_id,
            'books' => $user->purchasedBooks()->pluck('books.id')
                ->map(fn ($id) => (string) $id)->values()->all(),
            'iat' => $now,
            'exp' => $now + max(1, (int) config('devices.license.days', 30)) * 86400,
            // 🌟 DEV-7: بعد از «خروج از حساب» به دستِ خودِ کاربر، کتاب‌های خریده‌شده‌ی دانلودشده
            // تا این‌همه روز (و نه بیش از exp) روی همین دستگاه باز می‌مانند — مثلاً وقتی بی‌اینترنت
            // اشتباهی خارج شده. همان «فاصله‌ی جدا کردنِ دستگاه»، چون کاربر زودتر از آن هم
            // نمی‌تواند دستگاه را آزاد کند. 0 = بی‌مهلت (بعد از خروج قفل).
            'gd' => max(0, (int) config('devices.release_cooldown_days', 30)),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'payload' => base64_encode($json),
            'sig' => base64_encode(sodium_crypto_sign_detached($json, $sk)),
            'exp' => $payload['exp'],
        ];
    }

    /** برای تست‌ها و عیب‌یابی: آیا این مجوز با کلیدِ فعلی درست امضا شده؟ */
    public function verify(array $license): ?array
    {
        $sk = $this->secretKey();
        $json = base64_decode((string) ($license['payload'] ?? ''), true);
        $sig = base64_decode((string) ($license['sig'] ?? ''), true);
        if (! $sk || $json === false || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }
        $pk = sodium_crypto_sign_publickey_from_secretkey($sk);

        return sodium_crypto_sign_verify_detached($sig, $json, $pk) ? json_decode($json, true) : null;
    }
}
