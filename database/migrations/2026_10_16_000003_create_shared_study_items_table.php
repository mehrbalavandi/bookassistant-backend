<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 ST-7: «مرورِ آماده» — واژه‌ها و نکته‌هایی که ادمین برای همه آماده می‌کند و اپ‌ها
// خودکار در «مرورِ من» می‌گیرند. payload همان قالبِ یکسان است (App\Support\StudyItemFormat).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_study_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);               // word | tip
            $table->string('text', 300);
            $table->string('key', 330)->unique();     // نوع + متنِ کوچک‌شده (تکراری‌ها)
            $table->json('payload');
            $table->boolean('published')->default(true);
            // زمانِ آخرین تغییر به میلی‌ثانیه، یکتا و صعودی — نشانگرِ همگام‌سازیِ اپ‌ها.
            $table->unsignedBigInteger('changed_ms')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_study_items');
    }
};
