<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 ورود با شماره‌ی موبایل و کدِ پیامکی (AUTH-1): کاربرِ تازه فقط موبایل + نام دارد؛
// ایمیل و رمز اختیاری می‌شوند (حساب‌های قدیمیِ ایمیلی و ادمین‌ها دست‌نخورده می‌مانند).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile', 11)->nullable()->unique()->after('name');
            $table->timestamp('mobile_verified_at')->nullable()->after('mobile');
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 11)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mobile']);
            $table->dropColumn(['mobile', 'mobile_verified_at', 'first_name', 'last_name', 'last_seen_at']);
        });
    }
};
