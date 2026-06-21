<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // اضافه کردن فیلد جدید، ترجیحاً بعد از فیلد اصلی فایل
            $table->string('sample_file_path')->nullable()->after('images');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // در صورت Rollback، این فیلد حذف می‌شود
            $table->dropColumn('sample_file_path');
        });
    }
};
