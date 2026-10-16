<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 DEV-1..4: هر حساب فقط روی دستگاه‌های ثبت‌شده‌اش کار می‌کند.
// - user_devices: کدام دستگاه «فعال» است (یک جای خالی از سقفِ دستگاه را گرفته) و کی/چرا آزاد شد.
// - personal_access_tokens.user_device_id: هر توکن به یک دستگاه گره می‌خورد.
// - users.max_devices: سقفِ اختصاصیِ یک کاربر (null = پیش‌فرضِ config/devices.php).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('device_id');
            // null = هرگز فعال نشده (ردیف‌های قدیمیِ BK-3 که فقط برای گزارش بودند).
            $table->timestamp('activated_at')->nullable()->after('last_seen_at');
            $table->timestamp('revoked_at')->nullable()->after('activated_at');
            // user | admin
            $table->string('revoked_by', 16)->nullable()->after('revoked_at');
            $table->index(['user_id', 'revoked_by', 'revoked_at']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('user_device_id')->nullable()->index()->after('tokenable_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_devices')->nullable()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('max_devices'));
        Schema::table('personal_access_tokens', function (Blueprint $t) {
            $t->dropIndex(['user_device_id']);
            $t->dropColumn('user_device_id');
        });
        Schema::table('user_devices', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'revoked_by', 'revoked_at']);
            $t->dropColumn(['name', 'activated_at', 'revoked_at', 'revoked_by']);
        });
    }
};
