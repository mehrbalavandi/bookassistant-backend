<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'folder_name',
        'json_file',
        'json_version',
        'audio_files',
        'audio_version',
        'images',
        'images_version',
        'sample_file_path',
        'sample_version',
        'sample_audio_files', // <--- جدید
        'sample_images',      // <--- جدید
        'price',     // 🌟 اضافه شد
        'discount',  // 🌟 اضافه شد
    ];

    protected $casts = [
        'audio_files' => 'array',
        'images' => 'array',
        'sample_audio_files' => 'array', // <--- جدید
        'sample_images' => 'array',      // <--- جدید
        'json_version' => 'integer',
        'audio_version' => 'integer',
        'images_version' => 'integer',
        'sample_version' => 'integer',
    ];

    /**
     * 🐞 حذفِ پوشه‌ی محتوای کتاب هنگام حذفِ رکورد.
     * قبلاً با حذفِ کتاب، پوشه‌ی storage/app/private/books/{folder_name}
     * (صفحات، صوت‌ها، تصاویر، آرشیوِ zip) دست‌نخورده باقی می‌ماند و فضا را
     * اشغال می‌کرد. این‌جا (در خودِ مدل) انجام می‌شود نه در صفحه‌ی ویرایش، تا
     * *همه‌ی* مسیرهای حذف پوشش داده شوند: دکمه‌ی Delete در صفحه‌ی ویرایش،
     * حذف از ردیفِ جدولِ فهرست، و حذفِ گروهی.
     */
    protected static function booted(): void
    {
        static::deleting(function (Book $book) {
            $folder = trim((string) $book->folder_name);
            // محافظت: نامِ خالی یا حاوی مسیرگردی هرگز نباید به deleteDirectory برسد
            // (وگرنه "books/" یعنی حذفِ محتوای همه‌ی کتاب‌ها).
            if ($folder === '' || str_contains($folder, '..') || str_contains($folder, '/') || str_contains($folder, '\\')) {
                return;
            }
            Storage::disk('local')->deleteDirectory("books/{$folder}");
        });
    }

    public function purchasers()
    {
        return $this->belongsToMany(User::class, 'book_user')->withTimestamps();
    }

    /**
     * 🌟 اکسسور هوشمند برای محاسبه قیمت نهایی پس از کسر تخفیف
     */
    public function getFinalPriceAttribute()
    {
        if ($this->discount > 0) {
            // محاسبه مبلغ تخفیف و کسر آن از قیمت اصلی
            $discountAmount = ($this->price * $this->discount) / 100;
            return $this->price - $discountAmount;
        }

        return $this->price;
    }
}
