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

Route::middleware('auth')->group(function () {
    // پاس دادن آیدی کتاب در URL
    Route::get('/checkout/{book}', [WebController::class, 'checkout'])->name('checkout');
    Route::get('/payment/verify', [WebController::class, 'verifyPayment'])->name('payment.verify');
});

Route::post('/logout', [WebController::class, 'logout'])->name('web.logout');
