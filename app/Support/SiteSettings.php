<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * 🌟 SITE-1: تنظیماتی که ادمین در پنل («تنظیماتِ سایت») عوض می‌کند.
 *
 * - **فقط کلیدهای فهرستِ FIELDS** قابلِ تغییرند؛ هر کلید همان کلیدِ config است (مثلاً
 *   `sms.driver` یا `shop.card.enabled`).
 * - **روی config نوشته می‌شوند:** در AppServiceProvider::boot مقدارهای ذخیره‌شده روی config
 *   نوشته می‌شوند. پس کدِ موجود (که config('shop.card.enabled') و مانندش را می‌خواند) بی‌تغییر
 *   از پنل پیروی می‌کند. مقدارِ .env فقط پیش‌فرض است.
 * - **کلیدهای مخفی** (secret: کلیدِ API، رمزِ SMTP، مرچنت) رمزنگاری‌شده ذخیره می‌شوند و در
 *   پنل هیچ‌وقت نمایش داده نمی‌شوند. خالی گذاشتنشان یعنی «همان قبلی بماند».
 * - **کش:** ردیف‌ها (هنوز رمزنگاری‌شده) کش می‌شوند و با هر ذخیره پاک می‌شوند.
 * - **بی‌جدول:** اگر جدول هنوز ساخته نشده (پیش از migrate)، بی‌صدا هیچ کاری نمی‌کند.
 */
final class SiteSettings
{
    private const CACHE_KEY = 'site_settings.rows.v1';

    /**
     * کلیدِ config => نوع. نوع‌ها: bool، int، string، text (چندخطی)، html، secret، select:a,b,c
     *
     * @var array<string, string>
     */
    public const FIELDS = [
        // سایت
        'site.name' => 'string',
        'site.tagline' => 'string',
        'site.seo_description' => 'text',
        'site.footer_text' => 'text',
        'site.support.phone' => 'string',
        'site.support.email' => 'string',
        'site.support.telegram' => 'string',
        'site.support.whatsapp' => 'string',
        'site.support.hours' => 'string',

        // ورود و ثبت‌نام
        'site.auth.mobile_enabled' => 'bool',
        'site.auth.email_enabled' => 'bool',
        'site.auth.registration_enabled' => 'bool',
        'site.auth.password_mode' => 'select:strong,medium,lax',
        'site.auth.mobile_disabled_message' => 'string',
        'site.auth.email_disabled_message' => 'string',
        'site.auth.registration_disabled_message' => 'string',

        // اطلاعیه و تعمیر
        'site.announcement.enabled' => 'bool',
        'site.announcement.text' => 'text',
        'site.announcement.level' => 'select:info,warning,success',
        'site.maintenance.enabled' => 'bool',
        'site.maintenance.message' => 'text',

        // صفحه‌ها
        'site.pages.about' => 'html',
        'site.pages.terms' => 'html',
        'site.pages.privacy' => 'html',

        // پیامک
        'sms.driver' => 'select:log,kavenegar,smsir',
        'sms.otp.expose_in_debug' => 'bool',
        'sms.otp.length' => 'int',
        'sms.otp.ttl_seconds' => 'int',
        'sms.otp.resend_seconds' => 'int',
        'sms.otp.max_attempts' => 'int',
        'sms.kavenegar.api_key' => 'secret',
        'sms.kavenegar.templates.otp' => 'string',
        'sms.kavenegar.templates.order_paid' => 'string',
        'sms.kavenegar.templates.order_rejected' => 'string',
        'sms.smsir.api_key' => 'secret',
        'sms.smsir.templates.otp' => 'string',
        'sms.smsir.templates.order_paid' => 'string',
        'sms.smsir.templates.order_rejected' => 'string',

        // ایمیل
        'mail.default' => 'select:log,smtp',
        'mail.mailers.smtp.host' => 'string',
        'mail.mailers.smtp.port' => 'int',
        'mail.mailers.smtp.scheme' => 'select:,smtp,smtps',
        'mail.mailers.smtp.username' => 'string',
        'mail.mailers.smtp.password' => 'secret',
        'mail.from.address' => 'string',
        'mail.from.name' => 'string',

        // فروش
        'shop.gateway_enabled' => 'bool',
        'shop.gateway' => 'select:zarinpal,zibal,idpay,payir,sep,behpardakht',
        'payment.drivers.zarinpal.merchantId' => 'secret',
        'payment.drivers.zarinpal.mode' => 'select:normal,sandbox',
        'shop.card.enabled' => 'bool',
        'shop.card.number' => 'string',
        'shop.card.holder' => 'string',
        'shop.card.bank' => 'string',
        'shop.card.sheba' => 'string',
        'shop.card.review_hint' => 'text',
        'shop.card.unique_max' => 'int',
        'shop.pay_link_minutes' => 'int',
        'shop.pending_expire_hours' => 'int',
        'shop.notify_email' => 'string',

        // دستگاه‌ها
        'devices.max_per_user' => 'int',
        'devices.release_cooldown_days' => 'int',
        'devices.license.days' => 'int',
        'devices.require_binding' => 'bool',

        // 🌟 BKP-1: پشتیبان‌گیری (صفحه‌ی «پشتیبان‌گیری»)
        'backup.daily' => 'bool',
        'backup.keep' => 'int',
    ];

    /** @var array<string, mixed>|null مقدارهای رمزگشایی‌شده‌ی همین درخواست */
    private static ?array $values = null;

    /** مقدارهای ذخیره‌شده را روی config می‌نویسد (AppServiceProvider::boot). */
    public static function apply(): void
    {
        self::$values = null; // هر بوت (و هر تست) از نو می‌خواند؛ کش همچنان کارِ اصلی را می‌کند
        foreach (self::stored() as $key => $value) {
            config([$key => $value]);
        }
    }

    /** @return array<string, mixed> فقط کلیدهایی که ادمین عوض کرده */
    public static function stored(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }
        try {
            $rows = Cache::rememberForever(self::CACHE_KEY, fn () => SiteSetting::query()
                ->get(['key', 'value', 'encrypted'])
                ->map(fn ($r) => ['key' => $r->key, 'value' => $r->value, 'encrypted' => (bool) $r->encrypted])
                ->all());
        } catch (\Throwable) {
            return self::$values = []; // جدول هنوز نیست (پیش از migrate) یا پایگاه در دسترس نیست
        }

        $values = [];
        foreach ($rows as $row) {
            if (! isset(self::FIELDS[$row['key']])) {
                continue;
            }
            try {
                $raw = $row['encrypted'] ? Crypt::decryptString((string) $row['value']) : $row['value'];
                $values[$row['key']] = self::cast($row['key'], json_decode((string) $raw, true));
            } catch (\Throwable $e) {
                Log::warning("SiteSettings: could not read {$row['key']}: ".$e->getMessage());
            }
        }

        return self::$values = $values;
    }

    /**
     * ذخیره‌ی مقدارهای فرمِ پنل. کلیدِ مخفیِ خالی = همان قبلی. کلیدهای ناشناخته نادیده.
     *
     * @param  array<string, mixed>  $input  کلیدِ config => مقدار
     */
    public static function save(array $input, ?int $userId = null): void
    {
        foreach ($input as $key => $value) {
            $type = self::FIELDS[$key] ?? null;
            if ($type === null) {
                continue;
            }
            if ($type === 'secret' && ($value === null || $value === '')) {
                continue;
            }
            $json = json_encode(self::cast($key, $value), JSON_UNESCAPED_UNICODE);
            SiteSetting::updateOrCreate(['key' => $key], [
                'value' => $type === 'secret' ? Crypt::encryptString($json) : $json,
                'encrypted' => $type === 'secret',
                'updated_by' => $userId,
            ]);
        }
        self::flush();
        self::apply();
    }

    /** برگرداندنِ کلیدها به پیش‌فرضِ .env/config (حذفِ مقدارِ پنل). */
    public static function forget(array $keys): void
    {
        SiteSetting::whereIn('key', $keys)->delete();
        self::flush();
    }

    /** آیا برای این کلیدِ مخفی مقداری در پنل ذخیره شده؟ (برای نمایشِ «ذخیره شده»). */
    public static function hasStored(string $key): bool
    {
        return array_key_exists($key, self::stored());
    }

    public static function flush(): void
    {
        self::$values = null;
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
        }
    }

    public static function cast(string $key, mixed $value): mixed
    {
        $type = self::FIELDS[$key] ?? 'string';

        return match (true) {
            $type === 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            $type === 'int' => (int) $value,
            str_starts_with($type, 'select:') => in_array((string) $value, explode(',', substr($type, 7)), true)
                ? (string) $value
                : (string) explode(',', substr($type, 7))[0],
            default => $value === null ? '' : (string) $value,
        };
    }
}
