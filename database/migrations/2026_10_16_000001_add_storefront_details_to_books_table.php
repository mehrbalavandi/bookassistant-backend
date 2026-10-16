<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🌟 SF-1: معرفیِ کتاب در ویترینِ اپ پیش از دانلودِ نمونه یا خرید — جلد، توضیح، نویسنده،
 * ناشر و سطح. همه اختیاری‌اند؛ کتابی که این‌ها را ندارد مثلِ قبل نمایش داده می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('title');   // مسیر روی دیسکِ local (covers/…)
            $table->text('description')->nullable()->after('cover_image');
            $table->string('author')->nullable()->after('description');
            $table->string('publisher')->nullable()->after('author');
            $table->string('level', 50)->nullable()->after('publisher'); // مثلاً «Band 4–5 (A2–B1)»
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['cover_image', 'description', 'author', 'publisher', 'level']);
        });
    }
};
