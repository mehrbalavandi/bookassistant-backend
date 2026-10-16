<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 DEV-5: نوعِ ساختِ اپ (debug | profile | release). روی اندروید شناسه‌ی سخت‌افزاری به کلیدِ
// امضای اپ بسته است؛ پس نسخه‌ی debug (flutter run) و release روی یک گوشی دو دستگاه حساب
// می‌شوند. این ستون در پنل نشان می‌دهد کدام است.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->string('build_mode', 10)->nullable()->after('build_number');
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', fn (Blueprint $t) => $t->dropColumn('build_mode'));
    }
};
