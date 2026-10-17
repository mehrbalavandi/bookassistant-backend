<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 SITE-1: مقدارهایی که ادمین در «تنظیماتِ سایت» عوض کرده. کلید = کلیدِ config (مثلاً
// site.auth.mobile_enabled یا sms.driver)؛ مقدار = JSON، و برای کلیدهای مخفی رمزنگاری‌شده.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('key', 150)->primary();
            $table->longText('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
