<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 PAY-5: «تومانِ شناسه» — چند تومانِ تصادفی که به مبلغِ هر سفارشِ کارت‌به‌کارت اضافه می‌شود
// تا هر واریز در صورت‌حسابِ بانک فوراً به سفارشِ خودش وصل شود. amount همان مبلغِ نهایی است
// (قیمت + شناسه)؛ amount_suffix فقط بخشِ اضافه را نگه می‌دارد.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('amount_suffix')->nullable()->after('amount');
            $table->index(['method', 'status', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['method', 'status', 'amount']);
            $table->dropColumn('amount_suffix');
        });
    }
};
