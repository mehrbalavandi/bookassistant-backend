<?php

namespace App\Support;

/**
 * سقفِ واقعیِ حجمِ آپلود در پنلِ ادمین.
 *
 * یک فایل فقط وقتی به سرور می‌رسد که از *هر سه* محدودیتِ زیر کوچک‌تر باشد:
 *   ۱) قانونِ max در config/livewire.php (endpointِ آپلودِ موقتِ Livewire)
 *   ۲) upload_max_filesize در php.ini
 *   ۳) post_max_size در php.ini (کلِ بدنه‌ی درخواست)
 *
 * 🐞 اگر فقط یکی از این‌ها کوچک باشد، فیلامنت روی «Uploading 100%» گیر
 * می‌کند و هیچ پیامی نمی‌دهد. این کلاس کمینه‌ی هر سه را حساب می‌کند تا
 * FileUpload همان را به‌عنوانِ maxSize بگیرد و فایلِ بزرگ‌تر *قبل از ارسال*،
 * با پیامِ روشن، در خودِ مرورگر رد شود.
 */
final class UploadLimits
{
    /** سقفِ مؤثر به کیلوبایت (واحدی که maxSize در فیلامنت می‌خواهد). */
    public static function effectiveMaxKb(): int
    {
        return min(array_column(self::limits(), 'kb'));
    }

    /** برچسبِ محدودیتی که گلوگاه است (کوچک‌ترین). */
    public static function bottleneck(): string
    {
        $limits = self::limits();
        usort($limits, fn($a, $b) => $a['kb'] <=> $b['kb']);
        return $limits[0]['label'];
    }

    /** آیا گلوگاه php.ini است (یعنی تغییرِ .env به‌تنهایی کافی نیست)؟ */
    public static function phpIsBottleneck(): bool
    {
        return self::bottleneck() !== 'livewire';
    }

    /** نمایشِ خوانا، مثلاً «۱۲ مگابایت» یا «1.5 گیگابایت». */
    public static function human(int $kb): string
    {
        if ($kb >= 1024 * 1024) {
            return rtrim(rtrim(number_format($kb / 1024 / 1024, 1), '0'), '.') . ' گیگابایت';
        }
        return rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.') . ' مگابایت';
    }

    /** متنِ راهنمای زیرِ فیلدِ آپلود. */
    public static function helperText(): string
    {
        $text = 'حداکثر حجمِ مجاز: ' . self::human(self::effectiveMaxKb()) . '.';

        // راهنمای php.ini فقط وقتی نشان داده می‌شود که سقف واقعاً تنگ باشد
        // (زیرِ ۱۰۰ مگابایت)؛ با سقفِ چندصد مگابایتی، این متن فقط شلوغی است.
        if (self::phpIsBottleneck() && self::effectiveMaxKb() < 100 * 1024) {
            $text .= ' (محدودیت از php.ini است: upload_max_filesize = '
                . ini_get('upload_max_filesize') . '، post_max_size = '
                . ini_get('post_max_size')
                . '. برای فایل‌های بزرگ‌تر هر دو را در php.ini افزایش دهید و سرور را دوباره اجرا کنید.)';
        }

        return $text;
    }

    // ───────────────────────── داخلی ─────────────────────────

    /** @return array<int, array{label: string, kb: int}> */
    private static function limits(): array
    {
        $limits = [
            ['label' => 'livewire', 'kb' => self::livewireMaxKb()],
        ];

        $upload = self::iniBytes('upload_max_filesize');
        if ($upload > 0) {
            $limits[] = ['label' => 'upload_max_filesize', 'kb' => intdiv($upload, 1024)];
        }

        // post_max_size کلِ بدنه‌ی multipart است؛ چند ده کیلوبایت برای
        // هدرها و فیلدهای دیگر کنار گذاشته می‌شود. مقدارِ 0 یعنی نامحدود.
        $post = self::iniBytes('post_max_size');
        if ($post > 0) {
            $limits[] = ['label' => 'post_max_size', 'kb' => max(1, intdiv($post, 1024) - 64)];
        }

        return $limits;
    }

    /** عددِ max از قوانینِ آپلودِ موقتِ Livewire (پیش‌فرضِ خودش ۱۲۲۸۸). */
    private static function livewireMaxKb(): int
    {
        $rules = config('livewire.temporary_file_upload.rules') ?? ['max:12288'];
        $rules = is_string($rules) ? explode('|', $rules) : (array) $rules;

        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'max:')) {
                return (int) substr($rule, 4);
            }
        }

        return PHP_INT_MAX;
    }

    /** تبدیلِ مقدارهایی مثل «512M» یا «2G» از php.ini به بایت. */
    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '') {
            return 0;
        }

        $number = (float) $value;
        return (int) match (strtolower(substr($value, -1))) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }
}
