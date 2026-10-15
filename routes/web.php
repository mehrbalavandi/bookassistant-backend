<?php

use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\BookController as AdminBookController;
// Route::get('/', function () {
//     return redirect('/admin');
// });

// صفحه اصلی سایت (معرفی اپلیکیشن و محصول)
Route::get('/', [WebController::class, 'homePage'])->name('home');

// 🌟 دریافتِ اپ از خودِ سایت (بدونِ فروشگاه): صفحه‌ی دریافت و فایلِ آخرین نسخه.
Route::get('/app', [\App\Http\Controllers\AppReleaseController::class, 'page'])->name('app.page');
Route::get('/app/download/{platform}', [\App\Http\Controllers\AppReleaseController::class, 'download'])
    ->where('platform', 'android|windows')
    ->name('app.download');

use App\Http\Controllers\WebAuthController;

Route::get('/register', [WebController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [WebController::class, 'register'])->name('register.store');

// مسیرهای مربوط به ورود
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login']);

// مسیر خروج
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    // پاس دادن آیدی کتاب در URL — سفارش می‌سازد و به لینکِ امضاشده‌ی پرداخت می‌رود (PAY-2)
    Route::get('/checkout/{book}', [WebController::class, 'checkout'])->name('checkout');
});

// 🌟 PAY-2: شروعِ پرداخت با لینکِ امضاشده (اپ و سایت) و برگشت از درگاه. نشستِ وب لازم نیست.
Route::get('/pay/{order}', [\App\Http\Controllers\PaymentController::class, 'start'])
    ->middleware('signed')->name('payment.start');
Route::match(['get', 'post'], '/payment/callback/{order}', [\App\Http\Controllers\PaymentController::class, 'callback'])
    ->name('payment.callback');

// 🌟 AUTH-1: ورود با موبایل و کدِ پیامکی در سایت
Route::get('/login/otp', [\App\Http\Controllers\WebOtpController::class, 'show'])->name('login.otp');
Route::post('/login/otp/request', [\App\Http\Controllers\WebOtpController::class, 'request'])
    ->middleware('throttle:otp-request')->name('login.otp.request');
Route::post('/login/otp/verify', [\App\Http\Controllers\WebOtpController::class, 'verify'])
    ->middleware('throttle:otp-verify')->name('login.otp.verify');

// 🌟 PAY-3: تصویرِ رسیدِ کارت‌به‌کارت برای ادمین (فایل در دیسکِ خصوصی است)
Route::middleware('auth')
    ->get('/admin/order-receipt/{order}', [\App\Http\Controllers\Admin\OrderReceiptController::class, 'show'])
    ->name('admin.order-receipt');

Route::post('/logout', [WebController::class, 'logout'])->name('web.logout');

// 🐞 پیش‌نمایشِ فایل‌های صوتی/تصویریِ کتاب در پنلِ ادمین (پلیر و بندانگشتی).
// خودِ کنترلر ادمین‌بودن را چک می‌کند؛ auth این‌جا فقط لایه‌ی اول است.
Route::middleware('auth')
    ->get('/admin/book-asset/{book}/{scope}/{kind}/{file}', [\App\Http\Controllers\Admin\BookAssetController::class, 'show'])
    ->where('file', '.*')
    ->name('admin.book-asset');
