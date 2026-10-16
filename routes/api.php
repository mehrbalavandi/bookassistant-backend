<?php

use \App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OtpAuthController;
use App\Http\Controllers\Api\SharedStudyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BookController;

// 🟢 مسیرهای عمومی (بدون نیاز به قفل Sanctum - برای همه باز است)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/books', [BookController::class, 'index']);
// 🌟 بررسیِ نسخه‌ی تازه‌ی اپ (عمومی): ?platform=android|windows
Route::get('/app/latest', [\App\Http\Controllers\AppReleaseController::class, 'latest']);
// این مسیر خارج از گروه میدل‌ویر قرار می‌گیرد
Route::get('/books/{book}/download', [BookController::class, 'download']);
// 🌟 SF-1: جلدِ کتاب برای ویترینِ اپ (عمومی)
Route::get('/books/{book}/cover', [BookController::class, 'cover']);

// 🌟 دانلودِ یکجا به‌صورت ZIP (سریع‌تر از صدها درخواستِ فایل‌به‌فایل) —
// zip-info حجم را می‌دهد و download-zip خودِ آرشیو را با پشتیبانیِ Range
// (یعنی قابلِ pause/resume) سرو می‌کند. مسیرِ قدیمیِ فایل‌به‌فایل بالا
// دست‌نخورده می‌ماند تا نسخه‌های قدیمیِ اپ همچنان کار کنند.
Route::get('/books/{book}/zip-info', [BookController::class, 'zipInfo']);
Route::get('/books/{book}/download-zip', [BookController::class, 'downloadZip']);

// 🌟 AUTH-1/2: ورود و ثبت‌نام با موبایل و کدِ پیامکی (با سقفِ درخواست، AppServiceProvider)
Route::post('/auth/otp/request', [OtpAuthController::class, 'request'])->middleware('throttle:otp-request');
Route::post('/auth/otp/verify', [OtpAuthController::class, 'verify'])->middleware('throttle:otp-verify');
// 🌟 DEV-2: سقفِ دستگاه پر بود ← با بلیتِ همان پاسخ یکی آزاد و این دستگاه وارد شود.
Route::post('/auth/device/replace', [DeviceController::class, 'replace'])->middleware('throttle:otp-verify');
// 🌟 ST-7: «مرورِ آماده» — واژه‌ها و نکته‌های ادمین برای «مرورِ من» (عمومی).
Route::get('/study/shared', [SharedStudyController::class, 'index'])->middleware('throttle:30,1');
// 🌟 PAY-2/3: روش‌های پرداختِ فعال (عمومی؛ اپ پیش از ورود هم می‌تواند نشان دهد)
Route::get('/shop/options', [OrderController::class, 'options']);

// 🔴 مسیرهای محافظت‌شده (فقط کاربران لاگین شده به همراه توکن معتبر)
Route::middleware('auth:sanctum')->group(function () {
    // خروج از حساب
    Route::post('/logout', [AuthController::class, 'logout']);

    // 🌟 حساب
    Route::get('/me', [OtpAuthController::class, 'me']);
    Route::patch('/me', [OtpAuthController::class, 'updateProfile']);
    Route::post('/me/mobile', [OtpAuthController::class, 'linkMobile'])->middleware('throttle:otp-verify');

    // 🌟 BK-1..3: پشتیبانِ داده‌ها
    Route::post('/backup/study/sync', [BackupController::class, 'syncStudy'])->middleware('throttle:60,1');
    Route::get('/backup/settings', [BackupController::class, 'getSettings']);
    Route::put('/backup/settings', [BackupController::class, 'putSettings'])->middleware('throttle:30,1');
    // 🌟 DEV-1..4: دستگاه‌ها و مجوزِ آفلاین (POST /devices همان ثبتِ BK-3 است و حالا مجوز را هم تمدید می‌کند)
    Route::post('/devices', [DeviceController::class, 'touch'])->middleware('throttle:30,1');
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->whereNumber('device')->middleware('throttle:10,1');
    Route::get('/license', [DeviceController::class, 'license'])->middleware('throttle:30,1');

    // 🌟 PAY-2/3: سفارش‌ها
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/orders/{order}/receipt', [OrderController::class, 'receipt'])->middleware('throttle:10,1');
});
