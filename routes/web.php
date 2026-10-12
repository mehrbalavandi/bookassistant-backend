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
    // پاس دادن آیدی کتاب در URL
    Route::get('/checkout/{book}', [WebController::class, 'checkout'])->name('checkout');
    Route::get('/payment/verify', [WebController::class, 'verifyPayment'])->name('payment.verify');
});

Route::post('/logout', [WebController::class, 'logout'])->name('web.logout');

// 🐞 پیش‌نمایشِ فایل‌های صوتی/تصویریِ کتاب در پنلِ ادمین (پلیر و بندانگشتی).
// خودِ کنترلر ادمین‌بودن را چک می‌کند؛ auth این‌جا فقط لایه‌ی اول است.
Route::middleware('auth')
    ->get('/admin/book-asset/{book}/{scope}/{kind}/{file}', [\App\Http\Controllers\Admin\BookAssetController::class, 'show'])
    ->where('file', '.*')
    ->name('admin.book-asset');
