<?php

use \App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BookController;

// 🟢 مسیرهای عمومی (بدون نیاز به قفل Sanctum - برای همه باز است)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/books', [BookController::class, 'index']);
// این مسیر خارج از گروه میدل‌ویر قرار می‌گیرد
Route::get('/books/{book}/download', [BookController::class, 'download']);

// 🌟 دانلودِ یکجا به‌صورت ZIP (سریع‌تر از صدها درخواستِ فایل‌به‌فایل) —
// zip-info حجم را می‌دهد و download-zip خودِ آرشیو را با پشتیبانیِ Range
// (یعنی قابلِ pause/resume) سرو می‌کند. مسیرِ قدیمیِ فایل‌به‌فایل بالا
// دست‌نخورده می‌ماند تا نسخه‌های قدیمیِ اپ همچنان کار کنند.
Route::get('/books/{book}/zip-info', [BookController::class, 'zipInfo']);
Route::get('/books/{book}/download-zip', [BookController::class, 'downloadZip']);

// 🔴 مسیرهای محافظت‌شده (فقط کاربران لاگین شده به همراه توکن معتبر)
Route::middleware('auth:sanctum')->group(function () {
    // خروج از حساب
    Route::post('/logout', [AuthController::class, 'logout']);
});
