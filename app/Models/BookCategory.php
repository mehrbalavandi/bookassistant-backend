<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 🌟 دسته‌ی کتاب (مثلاً «Mindset for IELTS»). ترتیبِ دسته‌ها در سایت و اپ =
 * sort_order (یکتا). ترتیبِ کتاب‌های هر دسته = books.sort_order.
 */
class BookCategory extends Model
{
    protected $fillable = [
        'title',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'category_id')->orderBy('sort_order');
    }

    /** شماره‌ی ردیفِ پیشنهادی برای دسته‌ی تازه: یکی بعد از آخرین. */
    public static function nextSortOrder(): int
    {
        return ((int) static::max('sort_order')) + 1;
    }
}
