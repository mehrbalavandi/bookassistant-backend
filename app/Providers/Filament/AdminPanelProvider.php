<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminAuthenticateSession;
use Filament\FontProviders\LocalFontProvider;
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
            // 🌟 نام و نشانِ خودِ پروژه به‌جای Laravel/Filament (همان آیکونِ اپ).
            ->brandName('آکادمی زبان و آیلتس')
            ->brandLogo(fn () => new HtmlString(
                '<span style="display:flex;align-items:center;gap:.5rem;font-weight:700">'
                .'<img src="'.asset('images/logo-192.png').'" alt="" style="height:2.25rem;width:2.25rem;border-radius:.5rem">'
                .'آکادمی زبان و آیلتس</span>'
            ))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Amber,
            ])
            // 🌟 فونتِ فارسیِ پنل. LocalFontProvider یعنی فایلِ CSS از خودِ
            // سایت خوانده شود (نه Google Fonts)، و فیلامنت خودش متغیرِ
            // ‎--font-family‎ را روی همین خانواده ست می‌کند — پس کلِ پنل،
            // شاملِ مودال‌ها و نوتیفیکیشن‌ها، فارسی می‌شود.
            ->font('Vazirmatn', url: asset('css/persian-font.css'), provider: LocalFontProvider::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                // FilamentInfoWidget (نشان و پیوندهای Filament) عمداً حذف شد.
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
                <!-- 🌟 راست‌به‌چپ کردنِ پنلِ ادمین.
                     همه‌ی برچسب‌های این پنل فارسی‌اند ولی قالبِ فیلامنت
                     به‌صورت پیش‌فرض dir="ltr" می‌دهد. این اسکریپت داخلِ
                     <head> و قبل از رندرِ <body> اجرا می‌شود، پس هیچ
                     پرشِ چیدمانی دیده نمی‌شود. عمداً به‌جای تغییرِ
                     app.locale انجام شده تا پیام‌های اعتبارسنجی و بقیه‌ی
                     رشته‌های لاراول دست‌نخورده بمانند. -->
                <script>
                    document.documentElement.setAttribute('dir', 'rtl');
                    document.documentElement.setAttribute('lang', 'fa');
                </script>
                <style>
                    /* 🐞 رفعِ ایرادِ گزارش‌شده: در صفحه‌ی کوچک، منویِ کاربر
                       (که «Sign Out» داخلِ آن است) از topbar باز می‌شود و
                       زیرِ دکمه‌های شناور می‌افتاد. علتش این بود که
                       .fi-topbar در فیلامنت z-index: 20 دارد و عناصرِ شناورِ
                       ما هم ۲۰ داشتند — با z-indexِ برابر، هرکدام که در DOM
                       بعدتر بیاید رویِ دیگری کشیده می‌شود، و هدرِ صفحه
                       بعدِ topbar می‌آید. پس topbar (و هرچه داخلش باز
                       می‌شود) به لایه‌ی بالاتر برده شد و عناصرِ شناور به
                       لایه‌ی پایین‌تر — که هنوز از محتوای صفحه بالاترند و
                       کارِ خودشان را می‌کنند. */
                    .fi-topbar {
                        z-index: 30;
                    }

                    /* نوارِ عنوانِ صفحه، که دکمه‌های اکشن داخلِ آن است.
                       top برابرِ ارتفاعِ topbarِ فیلامنت (4rem) گرفته شده تا
                       زیرِ آن پنهان نشود. */
                    .fi-main .fi-header {
                        position: sticky;
                        top: 4rem;
                        z-index: 10;
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
                        z-index: 10;
                        background-color: #f9fafb;
                        padding-block: 0.75rem;
                        border-top: 1px solid rgba(0, 0, 0, 0.06);
                    }
                    .dark .fi-main .fi-form-actions {
                        background-color: #030712;
                        border-top-color: rgba(255, 255, 255, 0.08);
                    }

                    /* هر منویِ بازشونده‌ای (منویِ کاربر، ActionGroupها) باید
                       بالاتر از نوارهای شناور بماند، هر جایِ صفحه که باشد */
                    .fi-dropdown-panel {
                        z-index: 40;
                    }

                    /* position:sticky داخلِ ظرفی با overflow مخفی کار نمی‌کند */
                    .fi-main,
                    .fi-main .fi-page {
                        overflow: visible;
                    }

                    /* ── چند اصلاحِ کوچکِ RTL ──
                       فیلامنت ۳ عمدتاً از ویژگی‌های منطقیِ CSS (ms-/me-/
                       start/end) استفاده می‌کند و با dir="rtl" خودش
                       می‌چرخد؛ این‌ها فقط چند موردِ باقی‌مانده‌اند. */

                    /* شماره‌ها و مسیرهای انگلیسی (نامِ فایل، نسخه، آدرس)
                       داخلِ متنِ فارسی نباید جابه‌جا دیده شوند */
                    [dir='rtl'] .fi-input,
                    [dir='rtl'] input[type='email'],
                    [dir='rtl'] input[type='url'],
                    [dir='rtl'] input[type='number'] {
                        text-align: right;
                    }
                    [dir='rtl'] input[type='email'],
                    [dir='rtl'] input[type='url'],
                    [dir='rtl'] input[type='number'] {
                        direction: ltr;
                        text-align: left;
                    }

                    /* متنِ کمکیِ زیرِ فیلدها و پیام‌های خطا */
                    [dir='rtl'] .fi-fo-field-wrp-hint,
                    [dir='rtl'] .fi-fo-field-wrp-error-message {
                        text-align: right;
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
