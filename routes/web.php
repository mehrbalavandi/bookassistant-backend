<?php

use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;

// صفحه اصلی سایت (معرفی اپلیکیشن و محصول)
Route::get('/', [WebController::class, 'homePage'])->name('home');

// شروع فرآیند پرداخت (وقتی کاربر روی خرید کلیک میکنه)
Route::post('/payment/checkout', [WebController::class, 'checkout'])->name('payment.checkout');

// بازگشت از درگاه پرداخت (Callback)
Route::get('/payment/verify', [WebController::class, 'verifyPayment'])->name('payment.verify');
