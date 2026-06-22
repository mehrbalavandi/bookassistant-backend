<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // فیلدهایی برای نگهداری فایل‌های صوتی و تصاویر نمونه (فصل اول)
            $table->json('sample_audio_files')->nullable()->after('sample_file_path');
            $table->json('sample_images')->nullable()->after('sample_audio_files');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['sample_audio_files', 'sample_images']);
        });
    }
};
