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

// 🌟 SITE-2: ورود (موبایل یا ایمیل) و ثبت‌نامِ ایمیلی — هر روش از پنل روشن/خاموش می‌شود.
Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [WebAuthController::class, 'register'])->middleware('throttle:login')->name('register.store');

// مسیرهای مربوط به ورود
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:login');

// 🌟 SITE-6: بازیابیِ رمزِ حساب‌های ایمیلی
Route::get('/forgot-password', [\App\Http\Controllers\PasswordResetController::class, 'showForgot'])->name('password.request');
Route::post('/forgot-password', [\App\Http\Controllers\PasswordResetController::class, 'sendLink'])
    ->middleware('throttle:password-reset')->name('password.email');
Route::get('/reset-password/{token}', [\App\Http\Controllers\PasswordResetController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [\App\Http\Controllers\PasswordResetController::class, 'reset'])
    ->middleware('throttle:login')->name('password.update');

// 🌟 SITE-5: صفحه‌های ثابت (متن از پنل)
Route::get('/contact', [\App\Http\Controllers\PageController::class, 'contact'])->name('contact');
Route::get('/page/{slug}', [\App\Http\Controllers\PageController::class, 'show'])
    ->where('slug', 'about|terms|privacy')->name('page');

// مسیر خروج
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    // 🌟 PAY-6: خرید در سایت — انتخابِ روش (درگاه یا کارت‌به‌کارت) و سفارش‌های من
    Route::get('/checkout/{book}', [\App\Http\Controllers\WebShopController::class, 'checkout'])->name('checkout');
    Route::post('/checkout/{book}', [\App\Http\Controllers\WebShopController::class, 'placeOrder'])
        ->middleware('throttle:20,1')->name('checkout.store');
    Route::get('/orders', [\App\Http\Controllers\WebShopController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [\App\Http\Controllers\WebShopController::class, 'show'])->whereNumber('order')->name('orders.show');
    Route::post('/orders/{order}/receipt', [\App\Http\Controllers\WebShopController::class, 'receipt'])
        ->whereNumber('order')->middleware('throttle:10,1')->name('orders.receipt');

    // 🌟 SITE-13: تغییرِ رمزِ عبور
    Route::get('/account/password', [\App\Http\Controllers\AccountController::class, 'editPassword'])->name('account.password');
    Route::post('/account/password', [\App\Http\Controllers\AccountController::class, 'updatePassword'])
        ->middleware('throttle:login')->name('account.password.update');

    // 🌟 FB-1: انتقادات و پیشنهادات / گزارشِ ایراد (با تصویر)
    Route::get('/feedback', [\App\Http\Controllers\FeedbackController::class, 'index'])->name('feedback.index');
    Route::post('/feedback', [\App\Http\Controllers\FeedbackController::class, 'store'])
        ->middleware('throttle:10,60')->name('feedback.store');
    Route::get('/feedback/attachment/{attachment}', [\App\Http\Controllers\FeedbackController::class, 'attachment'])
        ->whereNumber('attachment')->name('feedback.attachment');
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
