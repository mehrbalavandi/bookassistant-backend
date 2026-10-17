<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\SiteSettingsPage;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\AuthMethods;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * 🌟 SITE-9: نمای کلیِ داشبورد: کاربران، رسیدهای منتظرِ بررسی، فروشِ ۳۰ روز، دستگاه‌های فعال،
 * و وضعیتِ تنظیماتِ مهم (روش‌های ورود، سامانه‌ی پیامک، تعمیر) تا چیزی از چشم نیفتد.
 */
class SiteOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $since = now()->subDays(30);
        $users = User::count();
        $newUsers = User::where('created_at', '>=', now()->subDays(7))->count();
        $review = Order::where('status', Order::AWAITING_REVIEW)->count();
        $sales = (int) Order::where('status', Order::PAID)->where('paid_at', '>=', $since)->sum('amount');
        $paidCount = Order::where('status', Order::PAID)->where('paid_at', '>=', $since)->count();
        $devices = UserDevice::whereNotNull('activated_at')->whereNull('revoked_at')->count();

        $methods = array_filter([
            AuthMethods::mobile() ? 'موبایل' : null,
            AuthMethods::email() ? 'ایمیل' : null,
        ]);
        $sms = (string) config('sms.driver');
        $settings = SiteSettingsPage::getUrl();

        return [
            Stat::make('کاربران', number_format($users))
                ->description($newUsers.' نفر در ۷ روزِ اخیر')->icon('heroicon-o-users'),
            Stat::make('رسیدهای منتظرِ بررسی', number_format($review))
                ->description($review > 0 ? 'برای بررسی کلیک کنید' : 'چیزی منتظر نیست')
                ->color($review > 0 ? 'warning' : 'success')->icon('heroicon-o-receipt-percent')
                ->url(OrderResource::getUrl('index')),
            Stat::make('فروشِ ۳۰ روزِ اخیر', number_format($sales).' تومان')
                ->description($paidCount.' سفارشِ پرداخت‌شده')->icon('heroicon-o-banknotes'),
            Stat::make('دستگاه‌های فعال', number_format($devices))->icon('heroicon-o-device-phone-mobile'),
            Stat::make('روش‌های ورود', $methods ? implode(' و ', $methods) : 'هیچ!')
                ->description(AuthMethods::registration() ? 'ثبت‌نامِ تازه باز است' : 'ثبت‌نامِ تازه بسته است')
                ->color($methods ? 'success' : 'danger')->icon('heroicon-o-key')->url($settings),
            Stat::make('سامانه‌ی پیامک', match ($sms) { 'smsir' => 'sms.ir', 'kavenegar' => 'کاوه‌نگار', default => 'فقط لاگ' })
                ->description(config('site.maintenance.enabled') ? '⚠ سایت در حالتِ تعمیر است' : ($sms === 'log' ? 'هیچ پیامکی فرستاده نمی‌شود' : 'فعال'))
                ->color(config('site.maintenance.enabled') || $sms === 'log' ? 'warning' : 'success')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')->url($settings),
        ];
    }
}
