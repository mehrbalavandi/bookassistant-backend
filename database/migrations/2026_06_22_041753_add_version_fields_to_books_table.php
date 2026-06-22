<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->integer('json_version')->default(1)->after('json_file');
            $table->integer('audio_version')->default(1)->after('audio_files');
            $table->integer('images_version')->default(1)->after('images');
            $table->integer('sample_version')->default(1)->after('sample_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['json_version', 'audio_version', 'images_version', 'sample_version']);
        });
    }
};
