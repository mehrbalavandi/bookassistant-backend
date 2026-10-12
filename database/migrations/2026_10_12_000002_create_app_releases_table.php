<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🌟 نسخه‌های اپ برای توزیع از خودِ سایت (بدونِ بازار/مایکت/گوگل‌پلی).
 *
 * - platform: «android» (APK) یا «windows» (نصب‌کننده/ZIP)؛ هر سکو جدا نسخه می‌گیرد.
 * - build_number: همان عددِ بعد از «+» در pubspec.yaml (versionCode اندروید). اپ فقط
 *   همین عدد را مقایسه می‌کند؛ پس برای هر انتشار باید بزرگ‌تر از قبلی باشد (یکتا در هر سکو).
 * - version_name: همان «1.2.0» که به کاربر نشان داده می‌شود.
 * - is_mandatory: کاربرانِ نسخه‌های قدیمی‌تر از این انتشار باید به‌روز کنند (پنجره‌ی
 *   به‌روزرسانی بسته نمی‌شود).
 * - is_published: فقط انتشارهای منتشرشده به اپ و سایت اعلام می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->default('android');
            $table->string('version_name', 50);
            $table->unsignedInteger('build_number');
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('changelog')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['platform', 'build_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
