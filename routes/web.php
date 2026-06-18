<?php

use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return redirect('/admin');
// });

// صفحه اصلی سایت (معرفی اپلیکیشن و محصول)
Route::get('/', [WebController::class, 'homePage'])->name('home');

use App\Http\Controllers\WebAuthController;

Route::get('/register', [WebController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [WebController::class, 'register'])->name('register.store');

// مسیرهای مربوط به ورود
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login']);

// مسیر خروج
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// این مسیرها فقط برای کاربرانی که لاگین کرده‌اند قابل دسترس است
Route::middleware('auth')->group(function () {
    Route::post('/payment/checkout', [App\Http\Controllers\WebController::class, 'checkout'])->name('payment.checkout');
    Route::get('/payment/verify', [App\Http\Controllers\WebController::class, 'verifyPayment'])->name('payment.verify');
});

Route::post('/logout', [WebController::class, 'logout'])->name('web.logout');