<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // اضافه کردن فیلد قیمت و درصد تخفیف
            $table->unsignedBigInteger('price')->default(0)->after('folder_name');
            $table->unsignedInteger('discount')->default(0)->after('price'); // درصد تخفیف بین 0 تا 100
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['price', 'discount']);
        });
    }
};
