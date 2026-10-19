<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 🌟 FB-1: انتقادات، پیشنهادات و گزارشِ ایرادهای اپ/سایت از کاربران، با تصویر.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16)->default('bug');        // bug | suggestion | criticism | other
            $table->string('subject', 150);
            $table->text('message');
            $table->string('source', 8)->default('web');       // web | app
            $table->string('app_version', 32)->nullable();
            $table->string('platform', 120)->nullable();        // سکو و نسخه‌ی سیستم‌عامل
            $table->string('book_title', 150)->nullable();      // کتابی که گزارش درباره‌اش است (اختیاری)
            $table->string('status', 16)->default('new')->index(); // new | in_progress | resolved | closed
            $table->text('admin_reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('seen_by_user_at')->nullable();
            $table->timestamps();
        });

        Schema::create('feedback_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name', 190)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_attachments');
        Schema::dropIfExists('feedback');
    }
};
