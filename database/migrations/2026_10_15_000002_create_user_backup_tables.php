<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 پشتیبانِ داده‌های کاربر (BK-1 تا BK-3): «مرورِ من»، تنظیمات و دستگاه‌ها.
return new class extends Migration
{
    public function up(): void
    {
        // هر ردیف = یک موردِ «مرورِ من» در اپ. item_id همان شناسه‌ی اپ است (w|… یا p|…).
        // ستون‌های جدا (kind، book_id، importance، status) برای گزارش و جستجو در پنل؛
        // خودِ مورد کامل در payload است تا اپ هرچه فرستاده عیناً پس بگیرد.
        Schema::create('study_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_id', 191);
            $table->string('kind', 16);
            $table->string('book_id', 64)->nullable();
            $table->unsignedTinyInteger('importance')->default(2);
            $table->string('status', 16)->nullable();
            $table->boolean('deleted')->default(false);
            $table->json('payload');
            // زمانِ آخرین تغییر در اپ (میلی‌ثانیه) — قاعده‌ی «آخرین تغییر برنده است».
            $table->unsignedBigInteger('client_updated_ms');
            // زمانِ ثبت در سرور (میلی‌ثانیه) — برای «چه چیزی از دفعه‌ی قبل عوض شده؟».
            $table->unsignedBigInteger('server_ms')->index();
            $table->timestamps();
            $table->unique(['user_id', 'item_id']);
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('data');
            $table->unsignedBigInteger('client_updated_ms');
            $table->timestamps();
        });

        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 64);
            $table->string('platform', 16)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->unsignedInteger('build_number')->nullable();
            $table->string('os_version', 64)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('study_items');
    }
};
