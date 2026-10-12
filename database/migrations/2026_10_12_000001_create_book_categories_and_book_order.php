<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 🌟 دسته‌بندیِ کتاب‌ها و ترتیبِ نمایش.
 *
 * - book_categories: هر دسته (مثلاً «Mindset for IELTS») با شماره‌ی ردیفِ یکتا
 *   (sort_order) که ترتیبِ دسته‌ها را در سایت و اپ تعیین می‌کند.
 * - books.category_id: دسته‌ی کتاب (null = «سایر کتاب‌ها»، آخرِ فهرست).
 * - books.sort_order: شماره‌ی ردیفِ کتاب **داخلِ دسته‌ی خودش**؛ یکتا در هر دسته
 *   (ایندکسِ یکتای (category_id, sort_order)). ترتیبِ نمایش فقط از همین عدد
 *   می‌آید، نه از ترتیبِ آپلود.
 *
 * کتاب‌های موجود: بدونِ دسته می‌مانند و به ترتیبِ id شماره‌ی ردیف می‌گیرند،
 * تا ترتیبِ فعلی‌شان عوض نشود. بعد در پنلِ ادمین دسته و ردیفِ درست را بدهید.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->unique();
            $table->timestamps();
        });

        Schema::table('books', function (Blueprint $table) {
            // حذفِ دسته‌ای که هنوز کتاب دارد مجاز نیست (restrict) — اول باید
            // کتاب‌هایش به دسته‌ی دیگری منتقل شوند.
            $table->foreignId('category_id')
                ->nullable()
                ->after('title')
                ->constrained('book_categories')
                ->restrictOnDelete();
            $table->unsignedInteger('sort_order')->nullable()->after('category_id');
            $table->unique(['category_id', 'sort_order']);
        });

        $i = 0;
        foreach (DB::table('books')->orderBy('id')->pluck('id') as $id) {
            DB::table('books')->where('id', $id)->update(['sort_order' => ++$i]);
        }
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['category_id', 'sort_order']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('sort_order');
        });

        Schema::dropIfExists('book_categories');
    }
};
