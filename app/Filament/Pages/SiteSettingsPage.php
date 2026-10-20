<?php

namespace App\Filament\Pages;

use App\Services\Sms\SmsSender;
use App\Support\SiteSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

/**
 * 🌟 SITE-1: «تنظیماتِ سایت» — هرچه پیش‌تر فقط در .env بود، حالا از پنل:
 * ورود و ثبت‌نام، پیامک، ایمیل، فروش، دستگاه‌ها، اطلاعیه/تعمیر، اطلاعاتِ سایت و صفحه‌ها.
 * مقدارها در جدولِ site_settings ذخیره و روی config نوشته می‌شوند (App\Support\SiteSettings).
 * نامِ فیلدهای فرم = کلیدِ config با «__» به‌جای «.» (Filament نقطه را آرایه‌ی تو در تو می‌فهمد).
 */
class SiteSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'تنظیماتِ سایت';

    protected static ?string $title = 'تنظیماتِ سایت';

    protected static ?string $slug = 'site-settings';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.site-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->currentState());
    }

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** @return array<string, mixed> */
    private function currentState(): array
    {
        $state = [];
        foreach (SiteSettings::FIELDS as $key => $type) {
            $state[self::field($key)] = $type === 'secret' ? '' : config($key);
        }

        return $state;
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Tabs::make('settings')->persistTabInQueryString()->tabs([
                $this->authTab(),
                $this->smsTab(),
                $this->mailTab(),
                $this->shopTab(),
                $this->devicesTab(),
                $this->noticeTab(),
                $this->siteTab(),
                $this->pagesTab(),
            ]),
        ]);
    }

    // ───────────────────────── تب‌ها ─────────────────────────

    private function authTab(): Tabs\Tab
    {
        return Tabs\Tab::make('ورود و ثبت‌نام')->icon('heroicon-o-key')->schema([
            Placeholder::make('auth_warning')->hiddenLabel()
                ->visible(fn (Get $get) => ! $get(self::field('site.auth.mobile_enabled')) && ! $get(self::field('site.auth.email_enabled')))
                ->content(new HtmlString('<div style="color:#b45309;font-weight:600">⚠ هر دو روش خاموش‌اند: هیچ کاربری (جز ادمین‌ها از ‎/admin/login‎) نمی‌تواند وارد شود.</div>')),
            Section::make('روش‌ها')->description('هم در سایت و هم در اپ. روشِ خاموش دیده می‌شود، ولی با انتخابش پیامِ «فعلاً فعال نیست» می‌آید و سرور هم درخواستش را رد می‌کند.')
                ->columns(3)->schema([
                    $this->toggle('site.auth.mobile_enabled', 'ورود و ثبت‌نام با موبایل (کدِ پیامکی)'),
                    $this->toggle('site.auth.email_enabled', 'ورود و ثبت‌نام با ایمیل و رمز', 'ادمین‌ها همیشه می‌توانند با ایمیل وارد شوند.'),
                    $this->toggle('site.auth.registration_enabled', 'ساختنِ حسابِ تازه', 'خاموش: فقط کاربرانِ موجود وارد می‌شوند.'),
                ]),
            Section::make('پیام‌ها')->columns(1)->collapsed()->schema([
                $this->text('site.auth.mobile_disabled_message', 'پیام وقتی موبایل خاموش است'),
                $this->text('site.auth.email_disabled_message', 'پیام وقتی ایمیل خاموش است'),
                $this->text('site.auth.registration_disabled_message', 'پیام وقتی ثبت‌نام بسته است'),
            ]),
            Section::make('رمزِ عبور و کدِ ورود')->columns(3)->schema([
                Select::make(self::field('site.auth.password_mode'))->label('قانونِ رمزِ ثبت‌نامِ ایمیلی')->native(false)->required()
                    ->options([
                        'strong' => 'قوی: ۸+ با حروفِ بزرگ و کوچک، عدد و نماد',
                        'medium' => 'متوسط: ۸+ با حرف و عدد',
                        'lax' => 'آزاد (فقط برای تست)',
                    ]),
                $this->int('sms.otp.length', 'طولِ کدِ پیامکی', 4, 8),
                $this->int('sms.otp.ttl_seconds', 'اعتبارِ کد (ثانیه)', 60, 1800),
                $this->int('sms.otp.resend_seconds', 'فاصله‌ی ارسالِ دوباره (ثانیه)', 30, 900),
                $this->int('sms.otp.max_attempts', 'حداکثرِ تلاشِ نادرست', 3, 10),
            ]),
            // 🌟 SITE-12
            Section::make('نشست')->description('برای سایت و پنلِ ادمین (اپ با توکن کار می‌کند و تحتِ تأثیر نیست).')
                ->columns(2)->schema([
                    $this->int('session.lifetime', 'مدتِ نشست بی‌فعالیت (دقیقه)', 5, 43200)
                        ->helperText('اگر این مدت هیچ صفحه‌ای باز نشود، نشست تمام می‌شود. هر بار که صفحه‌ای باز شود از نو شمرده می‌شود.'),
                    $this->toggle('site.auth.remember', 'ماندن در حساب بعد از پایانِ نشست («مرا به خاطر بسپار»)',
                        'روشن: کاربر با کوکیِ ماندگار خودکار دوباره وارد می‌شود و صفحه‌ی ورود را نمی‌بیند. خاموش: بعد از پایانِ نشست صفحه‌ی ورود می‌آید (کوکی‌های ماندگارِ فعلی هم با ذخیره باطل می‌شوند).'),
                ]),
        ]);
    }

    private function smsTab(): Tabs\Tab
    {
        $driver = fn (Get $get) => $get(self::field('sms.driver'));

        return Tabs\Tab::make('پیامک')->icon('heroicon-o-chat-bubble-left-ellipsis')->schema([
            Section::make('سامانه')->columns(2)->schema([
                Select::make(self::field('sms.driver'))->label('سامانه‌ی پیامک')->native(false)->required()->live()
                    ->options(['log' => 'هیچ (فقط لاگ — توسعه)', 'smsir' => 'sms.ir', 'kavenegar' => 'کاوه‌نگار']),
                $this->toggle('sms.otp.expose_in_debug', 'نمایشِ کد روی صفحه در حالتِ توسعه',
                    'فقط وقتی سامانه «هیچ (لاگ)» و APP_DEBUG=true است. با sms.ir یا کاوه‌نگار کد هرگز نمایش داده نمی‌شود.'),
                Placeholder::make('sms_log_hint')->hiddenLabel()->columnSpanFull()
                    ->visible(fn (Get $get) => $driver($get) === 'log')
                    ->content(new HtmlString('<div style="color:#b45309">⚠ هیچ پیامکی فرستاده نمی‌شود؛ کد فقط در storage/logs نوشته می‌شود. روی سرورِ واقعی sms.ir یا کاوه‌نگار را انتخاب کنید.</div>')),
            ]),
            Section::make('sms.ir')->description('پنلِ sms.ir ← «برنامه‌نویسان» ← کلیدِ API، و «ارسالِ وریفای» ← شناسه‌ی قالب. قالبِ ورود باید پارامترِ CODE داشته باشد.')
                ->visible(fn (Get $get) => $driver($get) === 'smsir')->columns(2)->schema([
                    $this->secret('sms.smsir.api_key', 'کلیدِ API'),
                    $this->text('sms.smsir.templates.otp', 'شناسه‌ی قالبِ کدِ ورود (CODE)'),
                    $this->text('sms.smsir.templates.order_paid', 'شناسه‌ی قالبِ «سفارش فعال شد» (اختیاری)'),
                    $this->text('sms.smsir.templates.order_rejected', 'شناسه‌ی قالبِ «رسید رد شد» (اختیاری)'),
                ]),
            Section::make('کاوه‌نگار')->description('قالبِ ورود در پنلِ کاوه‌نگار باید %token% داشته باشد.')
                ->visible(fn (Get $get) => $driver($get) === 'kavenegar')->columns(2)->schema([
                    $this->secret('sms.kavenegar.api_key', 'کلیدِ API'),
                    $this->text('sms.kavenegar.templates.otp', 'نامِ قالبِ کدِ ورود'),
                    $this->text('sms.kavenegar.templates.order_paid', 'نامِ قالبِ «سفارش فعال شد» (اختیاری)'),
                    $this->text('sms.kavenegar.templates.order_rejected', 'نامِ قالبِ «رسید رد شد» (اختیاری)'),
                ]),
        ]);
    }

    private function mailTab(): Tabs\Tab
    {
        return Tabs\Tab::make('ایمیل')->icon('heroicon-o-envelope')->schema([
            Section::make('ارسالِ ایمیل')->description('برای بازیابیِ رمزِ حساب‌های ایمیلی. با «log» ایمیل فقط در لاگ نوشته می‌شود.')
                ->columns(2)->schema([
                    Select::make(self::field('mail.default'))->label('روشِ ارسال')->native(false)->required()->live()
                        ->options(['log' => 'هیچ (فقط لاگ)', 'smtp' => 'SMTP']),
                    $this->text('mail.from.address', 'ایمیلِ فرستنده')->email(),
                    $this->text('mail.from.name', 'نامِ فرستنده'),
                ]),
            Section::make('SMTP')->visible(fn (Get $get) => $get(self::field('mail.default')) === 'smtp')->columns(3)->schema([
                $this->text('mail.mailers.smtp.host', 'میزبان (host)'),
                $this->int('mail.mailers.smtp.port', 'درگاه (port)', 1, 65535)->helperText('معمولاً 587 یا 465'),
                Select::make(self::field('mail.mailers.smtp.scheme'))->label('رمزنگاری')->native(false)
                    ->placeholder('خودکار (از روی درگاه)')
                    ->options(['smtp' => 'STARTTLS (درگاهِ 587)', 'smtps' => 'SSL (درگاهِ 465)']),
                $this->text('mail.mailers.smtp.username', 'نامِ کاربری'),
                $this->secret('mail.mailers.smtp.password', 'رمز'),
            ]),
        ]);
    }

    private function shopTab(): Tabs\Tab
    {
        return Tabs\Tab::make('فروش')->icon('heroicon-o-credit-card')->schema([
            Placeholder::make('shop_warning')->hiddenLabel()
                ->visible(fn (Get $get) => ! $get(self::field('shop.gateway_enabled')) && ! $get(self::field('shop.card.enabled')))
                ->content(new HtmlString('<div style="color:#b45309;font-weight:600">⚠ هیچ روشِ خریدی روشن نیست: کاربران نه در سایت و نه در اپ نمی‌توانند کتابی بخرند.</div>')),
            Section::make('روش‌های خرید')->description('هر روشی که روشن باشد در سایت و اپ پیشنهاد می‌شود؛ روشِ خاموش اصلاً نمایش داده نمی‌شود و سرور هم سفارشش را نمی‌پذیرد.')
                ->columns(3)->schema([
                    $this->int('shop.pending_expire_hours', 'لغوِ خودکارِ سفارشِ پرداخت‌نشده (ساعت)', 0, 720)
                        ->helperText('0 = هرگز. سفارشی که رسیدش فرستاده شده لغو نمی‌شود.'),
                    $this->text('shop.notify_email', 'ایمیلِ خبرِ رسیدِ تازه (اختیاری)')->email()
                        ->helperText('اعلانِ پنل (زنگوله‌ی بالا) همیشه هست.'),
                ]),
            Section::make('درگاهِ پرداخت')->columns(3)->schema([
                $this->toggle('shop.gateway_enabled', 'پرداختِ اینترنتی'),
                Select::make(self::field('shop.gateway'))->label('درگاه')->native(false)->required()
                    ->options(['zarinpal' => 'زرین‌پال', 'zibal' => 'زیبال', 'idpay' => 'آیدی‌پی', 'payir' => 'Pay.ir', 'sep' => 'سامان (سپ)', 'behpardakht' => 'به‌پرداخت ملت'])
                    ->helperText('مشخصاتِ درگاه‌های غیر از زرین‌پال در config/payment.php است.'),
                $this->int('shop.pay_link_minutes', 'اعتبارِ لینکِ پرداخت (دقیقه)', 5, 1440),
                $this->secret('payment.drivers.zarinpal.merchantId', 'مرچنت‌کدِ زرین‌پال'),
                Select::make(self::field('payment.drivers.zarinpal.mode'))->label('حالتِ زرین‌پال')->native(false)
                    ->options(['normal' => 'واقعی', 'sandbox' => 'آزمایشی (sandbox)']),
            ]),
            Section::make('کارت‌به‌کارت')->columns(3)->schema([
                $this->toggle('shop.card.enabled', 'کارت‌به‌کارت با بررسیِ رسید'),
                $this->text('shop.card.number', 'شماره‌ی کارت (۱۶ رقم)'),
                $this->text('shop.card.holder', 'نامِ صاحبِ کارت'),
                $this->text('shop.card.bank', 'بانک'),
                $this->text('shop.card.sheba', 'شبا (اختیاری)'),
                $this->int('shop.card.unique_max', 'سقفِ «تومانِ شناسه»', 0, 9999)->helperText('عددی تصادفی تا این مقدار به مبلغ اضافه می‌شود. 0 = خاموش.'),
                Textarea::make(self::field('shop.card.review_hint'))->label('پیام پس از ثبتِ رسید')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    private function devicesTab(): Tabs\Tab
    {
        return Tabs\Tab::make('دستگاه‌ها')->icon('heroicon-o-device-phone-mobile')->schema([
            Section::make()->columns(2)->schema([
                $this->int('devices.max_per_user', 'سقفِ دستگاهِ هر حساب', 1, 10)->helperText('برای یک کاربرِ خاص در صفحه‌ی همان کاربر عوض می‌شود.'),
                $this->int('devices.release_cooldown_days', 'فاصله‌ی جدا کردنِ دستگاه توسطِ کاربر (روز)', 0, 365)->helperText('0 = بی‌محدودیت. ادمین همیشه می‌تواند.'),
                $this->int('devices.license.days', 'اعتبارِ مجوزِ آفلاین (روز)', 1, 365),
                $this->toggle('devices.require_binding', 'اجبارِ شناسه‌ی دستگاه', 'خاموش فقط برای دوره‌ی کوتاهِ انتشارِ نسخه‌ی تازه‌ی اپ.'),
            ]),
        ]);
    }

    private function noticeTab(): Tabs\Tab
    {
        return Tabs\Tab::make('اطلاعیه و تعمیر')->icon('heroicon-o-megaphone')->schema([
            Section::make('اطلاعیه')->description('نواری بالای همه‌ی صفحه‌های سایت و بالای کتابخانه‌ی اپ.')->columns(2)->schema([
                $this->toggle('site.announcement.enabled', 'نمایشِ اطلاعیه'),
                Select::make(self::field('site.announcement.level'))->label('رنگ')->native(false)
                    ->options(['info' => 'آبی (اطلاع)', 'warning' => 'نارنجی (هشدار)', 'success' => 'سبز (خبرِ خوب)']),
                Textarea::make(self::field('site.announcement.text'))->label('متن')->rows(2)->columnSpanFull(),
            ]),
            Section::make('حالتِ تعمیر')->description('کاربرانِ عادی پیامِ زیر را می‌بینند (سایت و اپ). پنلِ ادمین، ورود و برگشت از درگاه باز می‌مانند؛ ادمین‌ها سایت را عادی می‌بینند.')
                ->columns(1)->schema([
                    $this->toggle('site.maintenance.enabled', 'سایت در حالِ تعمیر است'),
                    Textarea::make(self::field('site.maintenance.message'))->label('پیام')->rows(2),
                ]),
        ]);
    }

    private function siteTab(): Tabs\Tab
    {
        return Tabs\Tab::make('اطلاعاتِ سایت')->icon('heroicon-o-globe-alt')->schema([
            Section::make('سایت')->columns(2)->schema([
                $this->text('site.name', 'نامِ سایت')->required(),
                $this->text('site.tagline', 'شعار (زیرِ نام)'),
                Textarea::make(self::field('site.seo_description'))->label('توضیحِ سایت برای موتورهای جست‌وجو')->rows(2)->maxLength(300)->columnSpanFull(),
                Textarea::make(self::field('site.footer_text'))->label('متنِ پایینِ صفحه')->rows(2)->columnSpanFull(),
            ]),
            Section::make('پشتیبانی')->description('در «تماس با ما»، پایینِ صفحه‌ها و اپ نمایش داده می‌شود.')->columns(3)->schema([
                $this->text('site.support.phone', 'تلفن'),
                $this->text('site.support.email', 'ایمیل')->email(),
                $this->text('site.support.telegram', 'تلگرام')->placeholder('@my_support'),
                $this->text('site.support.whatsapp', 'واتس‌اپ')->placeholder('989121234567'),
                $this->text('site.support.hours', 'ساعتِ پاسخ‌گویی')->columnSpan(2),
            ]),
        ]);
    }

    private function pagesTab(): Tabs\Tab
    {
        $editor = fn (string $key, string $label) => RichEditor::make(self::field($key))->label($label)
            ->toolbarButtons(['bold', 'italic', 'underline', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo']);

        return Tabs\Tab::make('صفحه‌ها')->icon('heroicon-o-document-text')->schema([
            Placeholder::make('pages_hint')->hiddenLabel()->content(new HtmlString(
                'نشانی‌ها: <span dir="ltr">'.e(url('/page/about')).'</span>، <span dir="ltr">/page/terms</span>، <span dir="ltr">/page/privacy</span>، <span dir="ltr">/contact</span>. پیوندشان پایینِ همه‌ی صفحه‌ها و در اپ هست.'
            )),
            $editor('site.pages.about', 'درباره‌ی ما'),
            $editor('site.pages.terms', 'قوانین و مقررات')->helperText('برای درگاهِ پرداخت و نمادِ اعتماد معمولاً لازم است.'),
            $editor('site.pages.privacy', 'حریمِ خصوصی'),
        ]);
    }

    // ───────────────────────── فیلدها ─────────────────────────

    private function toggle(string $key, string $label, ?string $help = null): Toggle
    {
        return Toggle::make(self::field($key))->label($label)->helperText($help)->live();
    }

    private function text(string $key, string $label): TextInput
    {
        return TextInput::make(self::field($key))->label($label)->maxLength(500);
    }

    private function int(string $key, string $label, int $min, int $max): TextInput
    {
        return TextInput::make(self::field($key))->label($label)->numeric()->integer()->minValue($min)->maxValue($max)->required();
    }

    private function secret(string $key, string $label): TextInput
    {
        $has = SiteSettings::hasStored($key) || (string) config($key) !== '';

        return TextInput::make(self::field($key))->label($label)->password()->autocomplete('new-password')
            ->placeholder($has ? '•••••••• ذخیره شده — برای تغییر، مقدارِ تازه بنویسید' : '')
            ->helperText($has ? null : 'هنوز تنظیم نشده.');
    }

    // ───────────────────────── ذخیره و اکشن‌ها ─────────────────────────

    public function save(): void
    {
        $state = $this->form->getState();
        $values = [];
        foreach (SiteSettings::FIELDS as $key => $type) {
            $f = self::field($key);
            if (array_key_exists($f, $state)) {
                $values[$key] = $state[$f];
            }
        }
        SiteSettings::save($values, auth()->id());
        self::forgetResolvedServices();

        Notification::make()->title('تنظیمات ذخیره شد.')->success()->send();
        if (! config('site.auth.mobile_enabled') && ! config('site.auth.email_enabled')) {
            Notification::make()->title('هیچ روشِ ورودی برای کاربران فعال نیست.')->warning()->send();
        }
        $this->form->fill($this->currentState());
    }

    /** سرویس‌هایی که config را هنگامِ ساخته‌شدن خوانده‌اند (پیامک، ایمیل) از نو ساخته شوند. */
    private static function forgetResolvedServices(): void
    {
        app()->forgetInstance(SmsSender::class);
        try {
            app('mail.manager')->purge('smtp');
            app('mail.manager')->purge('log');
        } catch (\Throwable) {
        }
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [Action::make('save')->label('ذخیره‌ی تنظیمات')->submit('save')->keyBindings(['mod+s'])];
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testSms')->label('پیامکِ آزمایشی')->icon('heroicon-o-chat-bubble-left')->color('gray')
                ->modalDescription('یک کدِ ورودِ آزمایشی (12345) با سامانه‌ی ذخیره‌شده فرستاده می‌شود. اول تغییرات را ذخیره کنید.')
                ->form([TextInput::make('mobile')->label('شماره‌ی موبایل')->required()->placeholder('09121234567')])
                ->action(function (array $data) {
                    $mobile = \App\Support\Mobile::normalize($data['mobile']);
                    if (! $mobile) {
                        Notification::make()->title('شماره‌ی موبایل معتبر نیست.')->danger()->send();

                        return;
                    }
                    try {
                        app(SmsSender::class)->sendOtp($mobile, '12345');
                        Notification::make()->title(config('sms.driver') === 'log'
                            ? 'سامانه «لاگ» است؛ پیامک فقط در storage/logs نوشته شد.'
                            : 'پیامک فرستاده شد.')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('ارسال نشد')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('testMail')->label('ایمیلِ آزمایشی')->icon('heroicon-o-envelope')->color('gray')
                ->modalDescription('یک ایمیلِ آزمایشی با تنظیماتِ ذخیره‌شده فرستاده می‌شود.')
                ->form([TextInput::make('email')->label('ایمیلِ گیرنده')->email()->required()])
                ->action(function (array $data) {
                    try {
                        Mail::raw('این ایمیلِ آزمایشیِ «'.config('site.name').'» است. تنظیماتِ ایمیلِ سایت درست کار می‌کند.',
                            fn ($m) => $m->to($data['email'])->subject('ایمیلِ آزمایشی — '.config('site.name')));
                        Notification::make()->title(config('mail.default') === 'log'
                            ? 'روشِ ارسال «لاگ» است؛ ایمیل فقط در storage/logs نوشته شد.'
                            : 'ایمیل فرستاده شد.')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('ارسال نشد')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('reset')->label('بازگردانی به پیش‌فرض')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                ->requiresConfirmation()
                ->modalDescription('همه‌ی مقدارهایی که در این صفحه عوض شده‌اند پاک می‌شوند و مقدارهای .env برمی‌گردند (کلیدهای مخفیِ ذخیره‌شده هم).')
                ->action(function () {
                    SiteSettings::forget(array_keys(SiteSettings::FIELDS));
                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
