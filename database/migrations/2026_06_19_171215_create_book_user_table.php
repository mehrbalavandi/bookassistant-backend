<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            // می‌توانید فیلدهایی مثل قیمت پرداختی یا کد پیگیری خرید را هم اینجا اضافه کنید
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_user');
    }
};
