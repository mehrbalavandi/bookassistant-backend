<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

/**
 * 🌟 DEV-4: کلیدِ امضای مجوزِ آفلاین.
 *
 *   php artisan license:keygen            ← جفت‌کلیدِ تازه
 *   php artisan license:keygen --show     ← کلیدِ عمومیِ کلیدی که الان در .env است
 *
 * کلیدِ خصوصی فقط در .env سرور (LICENSE_SIGNING_KEY). کلیدِ عمومی در اپ:
 * lib/core/license/license_public_key.dart.
 */
class LicenseKeygen extends Command
{
    protected $signature = 'license:keygen {--show : فقط کلیدِ عمومیِ کلیدِ فعلی را نشان بده}';

    protected $description = 'ساختِ جفت‌کلیدِ Ed25519 برای مجوزِ آفلاینِ اپ';

    public function handle(LicenseService $licenses): int
    {
        if ($this->option('show')) {
            $pk = $licenses->publicKeyBase64();
            if (! $pk) {
                $this->error('LICENSE_SIGNING_KEY در .env نیست یا نامعتبر است.');

                return self::FAILURE;
            }
            $this->line("const String kLicensePublicKey = '{$pk}';");

            return self::SUCCESS;
        }

        if ($licenses->enabled() && ! $this->confirm(
            'کلیدی از قبل تنظیم شده. کلیدِ تازه یعنی مجوزهای قبلی در اپِ تازه اعتبار ندارند تا کاربر یک‌بار آنلاین شود. ادامه؟',
            false,
        )) {
            return self::SUCCESS;
        }

        $pair = sodium_crypto_sign_keypair();
        $secret = base64_encode(sodium_crypto_sign_secretkey($pair));
        $public = base64_encode(sodium_crypto_sign_publickey($pair));

        $this->info('۱) این خط را در .env سرور بگذارید (و هرگز در گیت نگذارید):');
        $this->line("LICENSE_SIGNING_KEY={$secret}");
        $this->newLine();
        $this->info('۲) این خط را در lib/core/license/license_public_key.dart اپ بگذارید:');
        $this->line("const String kLicensePublicKey = '{$public}';");
        $this->newLine();
        $this->comment('بعد: php artisan config:clear (یا config:cache) و ساختنِ نسخه‌ی تازه‌ی اپ.');

        return self::SUCCESS;
    }
}
