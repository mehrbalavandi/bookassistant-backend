<?php

use \App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BookController;

// 🟢 مسیرهای عمومی (بدون نیاز به قفل Sanctum - برای همه باز است)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/books', [BookController::class, 'index']);

// 🔴 مسیرهای محافظت‌شده (فقط کاربران لاگین شده به همراه توکن معتبر)
Route::middleware('auth:sanctum')->group(function () {    
    // خروج از حساب
    Route::post('/logout', [AuthController::class, 'logout']);
});