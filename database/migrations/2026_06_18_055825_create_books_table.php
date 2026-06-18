<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // عنوان نمایشی کتاب
            $table->string('folder_name')->unique(); // نام پوشه به انگلیسی (بسیار مهم)
            $table->string('json_file')->nullable(); // مسیر فایل جیسون
            $table->json('audio_files')->nullable(); // مسیر فایل‌های صوتی (آرایه)
            $table->json('images')->nullable(); // مسیر تصاویر (آرایه)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
