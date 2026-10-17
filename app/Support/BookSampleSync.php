<?php

namespace App\Support;

use App\Http\Controllers\Api\BookController;
use App\Models\Book;
use Illuminate\Support\Facades\Storage;

/**
 * 🌟 SMP-1: نسخه‌ی نمونه‌ای که اکسترکتور (CustomLayoutGenerator) هم‌زمان با کتاب می‌سازد،
 * در پوشه‌ی sample/ِ کنارِ خروجی است. اگر ادمین کلِ خروجی را (همراهِ sample/) در «آپلودِ
 * محتوای اصلی» بفرستد، همین کلاس بخشِ نمونه‌ی کتاب را با آن یکی می‌کند — دیگر لازم نیست
 * نمونه جدا آپلود شود:
 *   - فایل‌هایی از نمونه‌ی قبلی که index.jsonِ تازه به آن‌ها اشاره نمی‌کند پاک می‌شوند
 *     (چون اکسترکتور کلِ sample/ را از نو می‌سازد، این همان معنای «جایگزینی» است)؛
 *   - ستون‌های sample_* پر می‌شوند و sample_version بالا می‌رود (اپ به‌روزرسانی را می‌بیند)؛
 *   - ZIPِ دانلودِ نمونه از نو ساخته می‌شود.
 * تشخیصِ «آرشیو نمونه داشت» با مقایسه‌ی هَشِ sample/index.json پیش و پس از استخراج است، پس
 * برای ZIP و RAR یکسان کار می‌کند و آپلودِ بی‌نمونه هیچ اثری روی نمونه‌ی موجود ندارد.
 */
final class BookSampleSync
{
    public static function root(Book $book): string
    {
        return "books/{$book->folder_name}/sample";
    }

    /** هَشِ sample/index.json، یا null اگر نیست. */
    public static function fingerprint(Book $book): ?string
    {
        $path = Storage::disk('local')->path(self::root($book) . '/index.json');
        return is_file($path) ? md5_file($path) : null;
    }

    /**
     * بعد از استخراجِ آرشیوِ اصلی: اگر sample/index.json تازه آمده یا عوض شده، نمونه را ثبت
     * می‌کند و خلاصه برمی‌گرداند؛ وگرنه null (یعنی آرشیو نمونه نداشت).
     */
    public static function syncIfChanged(Book $book, ?string $fingerprintBefore): ?string
    {
        $after = self::fingerprint($book);
        if ($after === null || $after === $fingerprintBefore) {
            return null;
        }
        return self::register($book);
    }

    /** نمونه‌ی رویِ دیسک را مرتب و ثبت می‌کند (پاکسازی، ستون‌ها، نسخه، ZIP). */
    public static function register(Book $book): string
    {
        $disk = Storage::disk('local');
        $root = self::root($book);
        $index = json_decode((string) $disk->get("{$root}/index.json"), true);
        $pruned = is_array($index) ? self::prune($book, $index) : 0;

        $audio = $disk->exists("{$root}/audio") ? $disk->files("{$root}/audio") : [];
        $images = $disk->exists("{$root}/images") ? $disk->files("{$root}/images") : [];

        $book->update([
            'sample_file_path' => "{$root}/index.json",
            'sample_audio_files' => $audio,
            'sample_images' => $images,
        ]);
        $book->increment('sample_version');
        $book->refresh();

        // نامِ آرشیو شاملِ شماره‌ی نسخه است؛ بعد از increment ساخته می‌شود.
        $zipRel = BookController::zipRelativePath($book, 'sample');
        if ($disk->exists($zipRel)) {
            $disk->delete($zipRel);
        }
        BookController::ensureZip($book, 'sample');

        $sample = is_array($index) ? ($index['Sample'] ?? null) : null;
        $pages = is_array($index) ? count($index['Pages'] ?? []) : 0;
        $what = is_array($sample) && ! empty($sample['Title'])
            ? '«' . $sample['Title'] . '» (صفحه‌های ' . ($sample['FirstPage'] ?? '?') . ' تا ' . ($sample['LastPage'] ?? '?') . ')'
            : "{$pages} صفحه";

        return "نسخه‌ی نمونه هم به‌روز شد: {$what}، " . count($audio) . ' فایلِ صوتی، ' . count($images) . ' عکس'
            . ($pruned > 0 ? "؛ {$pruned} فایلِ کهنه‌ی نمونه‌ی قبلی پاک شد." : '.');
    }

    /**
     * فایل‌های pages/، audio_scripts/، images/ و audio/ِ نمونه که index.json به آن‌ها اشاره
     * نمی‌کند پاک می‌شوند. اگر index صفحه‌ای نداشته باشد (خراب)، هیچ چیز پاک نمی‌شود.
     */
    private static function prune(Book $book, array $index): int
    {
        $pages = $index['Pages'] ?? [];
        if (! is_array($pages) || count($pages) === 0) {
            return 0;
        }

        $disk = Storage::disk('local');
        $root = self::root($book);
        $keep = ['pages' => [], 'audio_scripts' => [], 'images' => [], 'audio' => []];

        foreach ($pages as $p) {
            $file = (string) ($p['File'] ?? '');
            if ($file === '') {
                continue;
            }
            $keep['pages'][basename($file)] = true;
            // نامِ عکس‌های همین صفحه (اسپن‌های Type=image ← Url)
            $json = $disk->exists("{$root}/{$file}") ? json_decode((string) $disk->get("{$root}/{$file}"), true) : null;
            self::collectImages($json, $keep['images']);
        }
        foreach (($index['AudioScripts'] ?? []) as $t) {
            if (! empty($t['File'])) {
                $keep['audio_scripts'][basename((string) $t['File'])] = true;
            }
            if (! empty($t['AudioTrackName'])) {
                $keep['audio'][(string) $t['AudioTrackName']] = true;
            }
        }
        foreach (($index['AudioLinksIndex'] ?? []) as $l) {
            if (! empty($l['FileName'])) {
                $keep['audio'][(string) $l['FileName']] = true;
            }
        }

        $deleted = 0;
        foreach ($keep as $dir => $names) {
            if (! $disk->exists("{$root}/{$dir}")) {
                continue;
            }
            foreach ($disk->files("{$root}/{$dir}") as $f) {
                if (! isset($names[basename($f)])) {
                    $disk->delete($f);
                    $deleted++;
                }
            }
        }
        return $deleted;
    }

    private static function collectImages(mixed $node, array &$into): void
    {
        if (! is_array($node)) {
            return;
        }
        if (($node['Type'] ?? null) === 'image' && is_string($node['Url'] ?? null) && $node['Url'] !== '') {
            $into[basename($node['Url'])] = true;
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                self::collectImages($child, $into);
            }
        }
    }
}
