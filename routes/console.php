<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 🌟 PAY-7: لغوِ سفارش‌های پرداخت‌نشده‌ی قدیمی (همین کار با هر سفارشِ تازه هم انجام می‌شود؛
// این فقط برای وقتی است که مدتی سفارشی ثبت نشود). روی سرور: cron برای `php artisan schedule:run`.
Artisan::command('orders:expire', function (\App\Services\OrderService $orders) {
    $n = $orders->expireStale();
    $this->info("{$n} سفارشِ پرداخت‌نشده لغو شد.");
})->purpose('Cancel unpaid orders older than shop.pending_expire_hours');

\Illuminate\Support\Facades\Schedule::command('orders:expire')->hourly();
