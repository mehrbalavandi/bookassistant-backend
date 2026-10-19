<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * 🌟 BKP-1: پشتیبانِ داده‌های سایت در یک فایلِ ZIP.
 *
 * داخلِ ZIP:
 * - manifest.json: زمان، نامِ سایت، تعدادِ ردیفِ هر جدول، فهرستِ migrationها، فایل‌ها
 * - db/<table>.jsonl: هر ردیف یک خطِ JSON (حافظه‌ی کم، حتی برای جدول‌های بزرگ)
 * - files/<path>: فایل‌هایی که ردیف‌ها به آن‌ها اشاره می‌کنند — رسیدهای کارت‌به‌کارت، تصویرهای
 *   «انتقادات و پیشنهادات»، جلدِ کتاب‌ها. محتوای کتاب‌ها (books/) و فایل‌های نصبیِ اپ عمداً نیستند:
 *   حجیم‌اند و از خروجیِ اکسترکتور و فایلِ نصب دوباره آپلود می‌شوند.
 *
 * جدول‌های موقت (نشست، کش، صف، کدهای پیامکی، توکن‌های ورود، اعلان‌ها) پشتیبان نمی‌شوند؛ پس بعد
 * از بازگردانی همه دوباره وارد می‌شوند.
 *
 * بازگردانی: اول خودکار از وضعیتِ فعلی پشتیبان گرفته می‌شود؛ بعد هر جدولِ داخلِ پشتیبان خالی و
 * از نو پر می‌شود (ستون‌هایی که در جدولِ فعلی نیستند کنار گذاشته می‌شوند) و فایل‌ها برمی‌گردند.
 * ⚠️ تنظیماتِ مخفی (کلیدِ پیامک، رمزِ ایمیل، مرچنت) با APP_KEY رمز شده‌اند؛ روی سروری با APP_KEYِ
 * دیگر دوباره باید وارد شوند.
 */
class SiteBackupService
{
    public const DIR = 'backups';

    /** به ترتیبِ وابستگی (پدرها اول). */
    public const TABLES = [
        'users', 'book_categories', 'books', 'book_user', 'user_devices', 'user_settings', 'study_items',
        'orders', 'shared_study_items', 'site_settings', 'app_releases', 'contents',
        'feedback', 'feedback_attachments',
    ];

    public const TABLE_LABELS = [
        'users' => 'کاربران', 'book_categories' => 'دسته‌ها', 'books' => 'کتاب‌ها', 'book_user' => 'خریدها',
        'user_devices' => 'دستگاه‌ها', 'user_settings' => 'تنظیماتِ اپِ کاربران', 'study_items' => '«مرورِ من»ِ کاربران',
        'orders' => 'سفارش‌ها', 'shared_study_items' => '«مرورِ آماده»', 'site_settings' => 'تنظیماتِ سایت',
        'app_releases' => 'نسخه‌های اپ', 'contents' => 'محتوا', 'feedback' => 'انتقادات و پیشنهادات',
        'feedback_attachments' => 'تصویرهای پیام‌ها',
    ];

    private function disk()
    {
        return Storage::disk('local');
    }

    /** @return string مسیرِ نسبیِ فایلِ ساخته‌شده روی دیسکِ local */
    public function create(string $reason = 'manual'): string
    {
        $this->disk()->makeDirectory(self::DIR);
        $name = 'backup-'.now()->format('Y-m-d-His').($reason !== 'manual' ? "-{$reason}" : '').'.zip';
        $rel = self::DIR.'/'.$name;
        $abs = $this->disk()->path($rel);

        $zip = new ZipArchive;
        if ($zip->open($abs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ساختنِ فایلِ پشتیبان ممکن نشد.');
        }
        $tmp = [];
        $counts = [];
        try {
            foreach ($this->existingTables() as $table) {
                $file = tempnam(sys_get_temp_dir(), 'bkp');
                $tmp[] = $file;
                $fh = fopen($file, 'w');
                $n = 0;
                $query = DB::table($table);
                $ordered = Schema::hasColumn($table, 'id') ? $query->orderBy('id') : $query->orderBy(Schema::getColumnListing($table)[0]);
                foreach ($ordered->lazy(500) as $row) {
                    fwrite($fh, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                    $n++;
                }
                fclose($fh);
                $zip->addFile($file, "db/{$table}.jsonl");
                $counts[$table] = $n;
            }

            $files = $this->referencedFiles();
            foreach ($files as $path) {
                $zip->addFile($this->disk()->path($path), 'files/'.$path);
            }

            $zip->addFromString('manifest.json', json_encode([
                'format' => 1,
                'created_at' => now()->toIso8601String(),
                'reason' => $reason,
                'site' => config('site.name'),
                'app_url' => config('app.url'),
                'tables' => $counts,
                'files' => count($files),
                'migrations' => Schema::hasTable('migrations') ? DB::table('migrations')->orderBy('id')->pluck('migration')->all() : [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->close();
        } finally {
            foreach ($tmp as $f) {
                @unlink($f);
            }
        }

        return $rel;
    }

    /** @return list<array{path:string,name:string,size:int,time:Carbon,manifest:?array}> جدیدترین اول */
    public function list(): array
    {
        $out = [];
        foreach ($this->disk()->files(self::DIR) as $path) {
            if (! str_ends_with($path, '.zip')) {
                continue;
            }
            $out[] = [
                'path' => $path,
                'name' => basename($path),
                'size' => (int) $this->disk()->size($path),
                'time' => Carbon::createFromTimestamp($this->disk()->lastModified($path)),
                'manifest' => $this->manifest($path),
            ];
        }
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);

        return $out;
    }

    public function manifest(string $path): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($this->disk()->path($path)) !== true) {
            return null;
        }
        $raw = $zip->getFromName('manifest.json');
        $zip->close();
        $m = $raw === false ? null : json_decode($raw, true);

        return is_array($m) && isset($m['tables']) ? $m : null;
    }

    /** پشتیبان‌های قدیمی‌تر از «keep»ِ آخر پاک می‌شوند. */
    public function prune(?int $keep = null): int
    {
        $keep = max(1, $keep ?? (int) config('backup.keep', 14));
        $n = 0;
        foreach (array_slice($this->list(), $keep) as $b) {
            $this->disk()->delete($b['path']);
            $n++;
        }

        return $n;
    }

    public function delete(string $name): void
    {
        $this->disk()->delete($this->pathOf($name));
    }

    /** مسیرِ امنِ یک پشتیبان از روی نامش (نه مسیرِ دلخواهِ کاربر). */
    public function pathOf(string $name): string
    {
        $name = basename($name);
        if (! preg_match('/^[\w.\-]+\.zip$/', $name) || ! $this->disk()->exists(self::DIR.'/'.$name)) {
            throw new RuntimeException('پشتیبان پیدا نشد.');
        }

        return self::DIR.'/'.$name;
    }

    /** فایلِ ZIPی که از بیرون آمده (آپلود) به فهرستِ پشتیبان‌ها اضافه می‌شود. */
    public function import(string $absolutePath, string $originalName): string
    {
        $tmpZip = new ZipArchive;
        if ($tmpZip->open($absolutePath) !== true || $tmpZip->getFromName('manifest.json') === false) {
            throw new RuntimeException('این فایل پشتیبانِ همین سایت نیست (manifest.json ندارد).');
        }
        $tmpZip->close();
        $this->disk()->makeDirectory(self::DIR);
        $base = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'uploaded';
        $rel = self::DIR.'/'.$base.'-uploaded-'.now()->format('His').'.zip';
        copy($absolutePath, $this->disk()->path($rel));

        return $rel;
    }

    /**
     * بازگردانی. اول از وضعیتِ فعلی پشتیبان گرفته می‌شود (نامش برگردانده می‌شود).
     *
     * @return array{safety:string, tables:array<string,int>, files:int}
     */
    public function restore(string $name): array
    {
        $path = $this->pathOf($name);
        $manifest = $this->manifest($path) ?? throw new RuntimeException('manifest.jsonِ پشتیبان خراب است.');
        $safety = $this->create('before-restore');

        $zip = new ZipArchive;
        if ($zip->open($this->disk()->path($path)) !== true) {
            throw new RuntimeException('بازکردنِ پشتیبان ممکن نشد.');
        }
        $restored = [];
        $files = 0;
        try {
            $tables = array_values(array_filter(self::TABLES,
                fn ($t) => array_key_exists($t, $manifest['tables']) && Schema::hasTable($t)));

            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($zip, $tables, &$restored) {
                    foreach (array_reverse($tables) as $t) {
                        DB::table($t)->delete();
                    }
                    foreach ($tables as $t) {
                        $columns = array_flip(Schema::getColumnListing($t));
                        $fh = $zip->getStream("db/{$t}.jsonl");
                        $batch = [];
                        $n = 0;
                        while ($fh && ($line = fgets($fh)) !== false) {
                            if (trim($line) === '') {
                                continue;
                            }
                            $batch[] = array_intersect_key(json_decode($line, true), $columns);
                            $n++;
                            if (count($batch) >= 300) {
                                DB::table($t)->insert($batch);
                                $batch = [];
                            }
                        }
                        if ($fh) {
                            fclose($fh);
                        }
                        if ($batch) {
                            DB::table($t)->insert($batch);
                        }
                        $restored[$t] = $n;
                    }
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (! str_starts_with($entry, 'files/') || str_ends_with($entry, '/') || str_contains($entry, '..')) {
                    continue;
                }
                $this->disk()->put(substr($entry, 6), $zip->getFromIndex($i));
                $files++;
            }
        } finally {
            $zip->close();
        }
        \App\Support\SiteSettings::flush();

        return ['safety' => basename($safety), 'tables' => $restored, 'files' => $files];
    }

    /** @return list<string> */
    private function existingTables(): array
    {
        return array_values(array_filter(self::TABLES, fn ($t) => Schema::hasTable($t)));
    }

    /** @return list<string> فایل‌های دیسکِ local که ردیف‌ها به آن‌ها اشاره می‌کنند و هنوز هستند */
    private function referencedFiles(): array
    {
        $paths = collect();
        if (Schema::hasTable('orders')) {
            $paths = $paths->merge(DB::table('orders')->whereNotNull('receipt_path')->pluck('receipt_path'));
        }
        if (Schema::hasTable('feedback_attachments')) {
            $paths = $paths->merge(DB::table('feedback_attachments')->pluck('path'));
        }
        if (Schema::hasColumn('books', 'cover_image')) {
            $paths = $paths->merge(DB::table('books')->whereNotNull('cover_image')->pluck('cover_image'));
        }

        return $paths->filter(fn ($p) => is_string($p) && $p !== '' && ! str_contains($p, '..')
            && $this->disk()->exists($p))->unique()->values()->all();
    }
}
