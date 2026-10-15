<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 سفارش‌ها (PAY-1): هر تلاش برای خرید یک ردیف — درگاه یا کارت‌به‌کارت — با همه‌ی
// جزئیات، تا سابقه‌ی مالی کامل بماند (قبلاً فقط جدولِ book_user بود و پرداختِ ناموفق
// یا کدِ پیگیری هیچ‌جا ثبت نمی‌شد).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // تومان، همان واحدِ قیمتِ کتاب
            $table->string('method', 16); // gateway | card
            $table->string('gateway', 32)->nullable();
            // pending → paid | failed | canceled ؛ کارت: pending → awaiting_review → paid | rejected
            $table->string('status', 20)->default('pending')->index();
            $table->string('transaction_id', 191)->nullable()->index();
            $table->string('reference_id', 191)->nullable();
            $table->string('card_tracking_code', 64)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_paid_at', 32)->nullable(); // همان‌طور که کاربر نوشته
            $table->string('receipt_path')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('source', 16)->default('app'); // app | web
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
