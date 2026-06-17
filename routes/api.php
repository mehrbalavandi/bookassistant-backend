<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContentController;
use Illuminate\Support\Facades\Route;

// مسیرهای عمومی (بدون نیاز به لاگین)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// مسیرهای محافظت شده (کاربر حتماً باید توکن معتبر داشته باشد)
Route::middleware('auth:sanctum')->group(function () {
    
    // دریافت لیست محتواها و نسخه‌های آن‌ها جهت همگام‌سازی در فلاتر
    Route::get('/contents', [ContentController::class, 'index']);
    
    // دانلود امن فایل صوتی (بررسی اشتراک درون این متد انجام می‌شود)
    Route::get('/contents/{id}/download', [ContentController::class, 'downloadSecureFile']);
    
});