<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
		Schema::create('contents', function (Blueprint $table) {
			$table->id();
			$table->string('title');
			$table->integer('version')->default(1); // نسخه این فایل برای سینک شدن با فلاتر
			$table->string('audio_filename');       // نام فایل صوتی ذخیره شده در سرور
			$table->text('text_content');           // متن مربوط به فایل صوتی (آماده برای Realm)
			$table->timestamps();
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
