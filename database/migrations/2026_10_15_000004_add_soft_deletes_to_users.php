<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 ADM-3: «حذفِ کاربر با نگه‌داشتنِ داده‌ها» = غیرفعال‌سازی (soft delete). حساب دیگر
// وارد نمی‌شود ولی خریدها، سفارش‌ها و «مرورِ من» می‌مانند و ادمین می‌تواند برش گرداند.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
