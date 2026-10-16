<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
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
        'category_id', // 🌟 دسته‌ی کتاب (null = «سایر کتاب‌ها»)
        'sort_order',  // 🌟 شماره‌ی ردیف داخلِ دسته — یکتا در هر دسته
        // 🌟 SF-1: معرفی در ویترین
        'cover_image',
        'description',
        'author',
        'publisher',
        'level',
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
        'sort_order' => 'integer',
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

        // 🌟 SF-1: جلدِ قبلی با جایگزینی/حذف، و جلدِ کتاب با حذفِ کتاب پاک می‌شود.
        static::updated(function (Book $book) {
            $old = $book->getOriginal('cover_image');
            if ($book->wasChanged('cover_image') && $old && $old !== $book->cover_image) {
                Storage::disk('local')->delete($old);
            }
        });
        static::deleted(function (Book $book) {
            if ($book->cover_image) {
                Storage::disk('local')->delete($book->cover_image);
            }
        });
    }

    /** 🌟 SF-1: نمونه‌ی رایگان واقعاً روی سرور هست (نه فقط مسیرش در رکورد). */
    public function hasSample(): bool
    {
        $path = trim((string) $this->sample_file_path);
        return $path !== '' && Storage::disk('local')->exists($path);
    }

    /** 🌟 SF-1: جلد واقعاً روی سرور هست. */
    public function hasCover(): bool
    {
        $path = trim((string) $this->cover_image);
        return $path !== '' && Storage::disk('local')->exists($path);
    }

    /**
     * نسخه‌ی جلد برای کشِ اپ: FileUpload هر فایلِ تازه را با نامِ تصادفی ذخیره می‌کند، پس
     * عوض‌شدنِ مسیر یعنی جلدِ تازه.
     */
    public function coverVersion(): ?string
    {
        return $this->cover_image ? substr(md5($this->cover_image), 0, 12) : null;
    }

    /** تعدادِ صفحه‌های نسخه‌ی کامل (برای معرفی در ویترین)؛ با هر آپلودِ تازه دوباره شمرده می‌شود. */
    public function pageCount(): int
    {
        $folder = trim((string) $this->folder_name);
        if ($folder === '') {
            return 0;
        }
        return (int) \Illuminate\Support\Facades\Cache::remember(
            "book-pages:{$this->id}:{$this->json_version}",
            now()->addDay(),
            fn () => count(Storage::disk('local')->files("books/{$folder}/pages")),
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BookCategory::class, 'category_id');
    }

    /** شماره‌ی ردیفِ پیشنهادی در یک دسته: یکی بعد از آخرین کتابِ همان دسته. */
    public static function nextSortOrder(?int $categoryId): int
    {
        $query = static::query();
        $categoryId === null ? $query->whereNull('category_id') : $query->where('category_id', $categoryId);

        return ((int) $query->max('sort_order')) + 1;
    }

    /**
     * 🌟 ترتیبِ نمایش در سایت و API (هر دو از همین‌جا، تا هرگز با هم فرق نکنند):
     * ۱) دسته‌ها به ترتیبِ sort_orderِ دسته؛ کتاب‌های بی‌دسته («سایر») آخر.
     * ۲) داخلِ هر دسته به ترتیبِ sort_orderِ کتاب؛ بعد id (فقط برای پایداری).
     * کتاب‌ها باید با رابطه‌ی category لود شده باشند.
     */
    public static function sortForCatalog(Collection $books): Collection
    {
        $key = fn (Book $b) => [
            $b->category === null ? 1 : 0,
            $b->category?->sort_order ?? 0,
            $b->sort_order ?? PHP_INT_MAX,
            $b->id,
        ];

        return $books->sort(fn (Book $a, Book $b) => $key($a) <=> $key($b))->values();
    }

    /**
     * 🌟 گروه‌بندیِ کتاب‌های مرتب‌شده بر اساسِ دسته، به همان ترتیب.
     * هر گروه: key (شناسه‌ی دسته یا "other")، title، description، books.
     */
    public static function groupForCatalog(Collection $sortedBooks): Collection
    {
        return $sortedBooks
            ->groupBy(fn (Book $b) => $b->category_id === null ? 'other' : (string) $b->category_id)
            ->map(fn (Collection $books, string $key) => (object) [
                'key' => $key,
                'title' => $books->first()->category?->title ?? 'سایر کتاب‌ها',
                'description' => $books->first()->category?->description,
                'books' => $books->values(),
            ])
            ->values();
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
