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

// 🌟 BKP-1: پشتیبانِ خودکارِ روزانه از داده‌های سایت (پنل ← پشتیبان‌گیری) و پاک‌کردنِ قدیمی‌ها.
Artisan::command('site:backup {--prune : فقط پشتیبان‌های قدیمی پاک شوند}', function (\App\Services\SiteBackupService $backups) {
    if (! $this->option('prune')) {
        $path = $backups->create('auto');
        $this->info('پشتیبان ساخته شد: '.basename($path));
    }
    $n = $backups->prune();
    if ($n) {
        $this->info("{$n} پشتیبانِ قدیمی پاک شد.");
    }
})->purpose('Back up users, orders, study items, settings … to storage/app/private/backups');

\Illuminate\Support\Facades\Schedule::command('site:backup')->dailyAt('03:30')
    ->when(fn () => (bool) config('backup.daily', true));
