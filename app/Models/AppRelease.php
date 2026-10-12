<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * 🌟 یک انتشارِ اپ (فایلِ نصبیِ یک سکو) که از خودِ سایت دانلود می‌شود.
 * فایل در storage/app/private/app-releases است و فقط از مسیرِ دانلودِ سایت سرو
 * می‌شود (AppReleaseController)، نه مستقیم.
 */
class AppRelease extends Model
{
    public const PLATFORMS = [
        'android' => 'اندروید (APK)',
        'windows' => 'ویندوز',
    ];

    protected $fillable = [
        'platform',
        'version_name',
        'build_number',
        'file_path',
        'file_size',
        'changelog',
        'is_mandatory',
        'is_published',
    ];

    protected $casts = [
        'build_number' => 'integer',
        'file_size' => 'integer',
        'is_mandatory' => 'boolean',
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        // حجمِ فایل همیشه از خودِ فایل خوانده می‌شود (نه از فرم).
        static::saving(function (AppRelease $release) {
            if ($release->isDirty('file_path')) {
                $disk = Storage::disk('local');
                $release->file_size = ($release->file_path && $disk->exists($release->file_path))
                    ? $disk->size($release->file_path)
                    : null;
            }
        });

        // فایلِ قبلی وقتی عوض شد یا انتشار حذف شد، پاک می‌شود تا فضا پر نشود.
        static::updated(function (AppRelease $release) {
            $old = $release->getOriginal('file_path');
            if ($release->wasChanged('file_path') && $old && $old !== $release->file_path) {
                Storage::disk('local')->delete($old);
            }
        });
        static::deleted(function (AppRelease $release) {
            if ($release->file_path) {
                Storage::disk('local')->delete($release->file_path);
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereNotNull('file_path');
    }

    /** آخرین انتشارِ منتشرشده‌ی یک سکو (بزرگ‌ترین build_number)، یا null. */
    public static function latestFor(string $platform): ?self
    {
        return static::published()->where('platform', $platform)->orderByDesc('build_number')->first();
    }

    /**
     * کمترین build_numberای که هنوز پشتیبانی می‌شود: بزرگ‌ترین build_numberِ
     * انتشارهای «اجباری». اپی که build_numberش کمتر از این است باید به‌روز کند.
     */
    public static function minSupportedBuild(string $platform): int
    {
        return (int) static::published()
            ->where('platform', $platform)
            ->where('is_mandatory', true)
            ->max('build_number');
    }

    /** نامِ فایلِ دانلود: ielts-assistant-1.2.0.apk */
    public function downloadName(): string
    {
        $ext = strtolower(pathinfo((string) $this->file_path, PATHINFO_EXTENSION)) ?: ($this->platform === 'android' ? 'apk' : 'zip');
        $version = preg_replace('/[^0-9A-Za-z.\-_]/', '_', $this->version_name);

        return "ielts-assistant-{$version}.{$ext}";
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '—';
        }

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' مگابایت'
            : number_format($bytes / 1024) . ' کیلوبایت';
    }
}
