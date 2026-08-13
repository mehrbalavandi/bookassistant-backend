<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminAuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            // 🌟 درخواستِ کاربر: دکمه‌های بالای صفحه (آپلود محتوا، حذف گروهی،
            // مدیریت فایل‌ها، Delete) و دکمه‌های پایینِ فرم (Save changes /
            // Cancel) در صفحه‌ی ویرایش شناور بمانند تا در فرم‌های بلند لازم
            // نباشد کاربر تا بالا/پایین اسکرول کند.
            //
            // این کار عمداً با CSSِ تزریقی انجام شده و نه با «تمِ سفارشیِ»
            // فیلامنت، چون تمِ سفارشی به npm/vite build نیاز دارد و هر بار
            // استقرار یک مرحله‌ی اضافه می‌شود؛ این‌جا فقط کافی است فایل
            // آپلود شود.
            ->renderHook(
                'panels::head.end',
                fn(): HtmlString => new HtmlString(<<<'HTML'
                <style>
                    /* نوارِ عنوانِ صفحه، که دکمه‌های اکشن داخلِ آن است.
                       top برابرِ ارتفاعِ topbarِ فیلامنت (4rem) گرفته شده تا
                       زیرِ آن پنهان نشود. */
                    .fi-main .fi-header {
                        position: sticky;
                        top: 4rem;
                        z-index: 20;
                        background-color: #f9fafb;
                        padding-block: 0.75rem;
                    }
                    .dark .fi-main .fi-header {
                        background-color: #030712;
                    }

                    /* دکمه‌های پایینِ فرم (Save changes / Cancel) */
                    .fi-main .fi-form-actions {
                        position: sticky;
                        bottom: 0;
                        z-index: 20;
                        background-color: #f9fafb;
                        padding-block: 0.75rem;
                        border-top: 1px solid rgba(0, 0, 0, 0.06);
                    }
                    .dark .fi-main .fi-form-actions {
                        background-color: #030712;
                        border-top-color: rgba(255, 255, 255, 0.08);
                    }

                    /* position:sticky داخلِ ظرفی با overflow مخفی کار نمی‌کند */
                    .fi-main,
                    .fi-main .fi-page {
                        overflow: visible;
                    }
                </style>
                HTML)
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // 🌟 به‌جای Filament\Http\Middleware\AuthenticateSession —
                // فقط برای اینکه مقصدِ ریدایرکتِ انقضای جلسه /login باشد.
                AdminAuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                // 🌟 به‌جای Filament\Http\Middleware\Authenticate
                AdminAuthenticate::class,
            ]);
    }
}
