<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\UserDevice;
use App\Services\DeviceService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 🌟 DEV-2/5: دستگاه‌های یک کاربر در صفحه‌ی ویرایشِ کاربر.
 * - **آزادسازی:** دستگاهِ فعال از حساب جدا می‌شود (بی‌محدودیتِ زمانی؛ در فاصله‌ی آزادسازیِ خودِ
 *   کاربر حساب نمی‌شود). در اولین اتصال از حساب خارج و محتوای کاملِ کتاب‌هایش پاک می‌شود.
 * - **ویرایش:** نامِ دستگاه (همان که کاربر در «دستگاه‌های من» می‌بیند).
 * - **حذف:** ردیف و نشست‌هایش کامل پاک می‌شود (مثلاً ردیف‌های قدیمیِ بی‌استفاده). اگر فعال بود،
 *   مثلِ آزادسازی است ولی آن دستگاه دلیلِ خروج را نمی‌فهمد و محتوایش پاک نمی‌شود.
 * - **شناسه:** شناسه‌ی کوتاه نشان می‌دهد دو ردیف واقعاً یک دستگاه‌اند یا نه. روی اندروید نسخه‌ی
 *   debug (flutter run) و release شناسه‌ی جدا دارند؛ ستونِ «ساخت» همین را نشان می‌دهد.
 */
class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    protected static ?string $title = 'دستگاه‌ها';

    protected static ?string $modelLabel = 'دستگاه';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->label('نامِ دستگاه')->maxLength(100)
                ->helperText('همان که کاربر در «دستگاه‌های من» و پنجره‌ی سقفِ دستگاه می‌بیند.'),
            TextInput::make('model')->label('مدل')->maxLength(100),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // ⚠️ نامِ پارامتر باید دقیقاً $query باشد: Filament پارامترها را با نام تزریق می‌کند و
            // با نامِ دیگر (مثلاً $q) یک Builderِ خالی و بی‌مدل از کانتینر می‌سازد ← خطای
            // «Cannot use ::class on null» در صفحه‌ی ویرایشِ کاربر.
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByRaw('revoked_at is not null')
                ->orderByRaw('activated_at is null')->orderByDesc('last_seen_at'))
            ->description(function () {
                $user = $this->getOwnerRecord();
                $active = $user->devices()->active()->count();
                $next = app(DeviceService::class)->nextSelfReleaseAt($user);

                return "فعال: {$active} از {$user->deviceLimit()}. "
                    .($next ? 'آزادسازیِ بعدی توسطِ خودِ کاربر: '.$next->format('Y-m-d') : 'کاربر الان می‌تواند خودش دستگاهی را آزاد کند.');
            })
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('دستگاه')
                    ->state(fn (UserDevice $d) => $d->label())
                    ->description(fn (UserDevice $d) => $d->buildLabel()),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()
                    ->state(fn (UserDevice $d) => match (true) {
                        $d->isActive() => 'فعال',
                        $d->revoked_at !== null => match ($d->revoked_by) {
                            'admin' => 'آزادشده (ادمین)',
                            'system' => 'جایگزین‌شده',
                            default => 'آزادشده (کاربر)',
                        },
                        default => 'ثبت‌نشده (نسخه‌ی قدیمی)',
                    })
                    ->color(fn (UserDevice $d) => $d->isActive() ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('short_id')->label('شناسه')
                    ->state(fn (UserDevice $d) => $d->shortId())
                    ->tooltip(fn (UserDevice $d) => $d->device_id)
                    ->extraAttributes(['dir' => 'ltr', 'style' => 'font-family:monospace']),
                Tables\Columns\TextColumn::make('app_version')->label('نسخه‌ی اپ')->placeholder('—'),
                Tables\Columns\TextColumn::make('activated_at')->label('فعال از')->dateTime('Y-m-d H:i'),
                Tables\Columns\TextColumn::make('last_seen_at')->label('آخرین بازدید')->since(),
                Tables\Columns\TextColumn::make('revoked_at')->label('آزادسازی')->dateTime('Y-m-d H:i')->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('release')
                    ->label('آزادسازی')
                    ->icon('heroicon-o-link-slash')
                    ->color('danger')
                    ->visible(fn (UserDevice $d) => $d->isActive())
                    ->requiresConfirmation()
                    ->modalHeading(fn (UserDevice $d) => 'آزادسازیِ «'.$d->label().'»')
                    ->modalDescription('این دستگاه از حساب جدا می‌شود و جایش برای دستگاهِ تازه خالی می‌شود. در اولین اتصال، از حساب خارج و محتوای کاملِ کتاب‌هایش پاک می‌شود؛ اگر آنلاین نشود، با تمام‌شدنِ مجوزِ آفلاین قفل می‌شود.')
                    ->action(function (UserDevice $d) {
                        app(DeviceService::class)->release($d, 'admin');
                        Notification::make()->title('دستگاه آزاد شد.')->success()->send();
                    }),
                Tables\Actions\EditAction::make()->label('ویرایش')->modalHeading('ویرایشِ دستگاه'),
                Tables\Actions\Action::make('forget')
                    ->label('حذف')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(fn (UserDevice $d) => 'حذفِ «'.$d->label().'»')
                    ->modalDescription(fn (UserDevice $d) => $d->isActive()
                        ? 'این دستگاه فعال است. با حذف، جایش خالی و نشستش بسته می‌شود؛ ولی محتوای کتاب‌ها روی آن پاک نمی‌شود (برای آن «آزادسازی» را بزنید). ادامه؟'
                        : 'ردیف و سابقه‌ی این دستگاه کامل پاک می‌شود.')
                    ->action(function (UserDevice $d) {
                        app(DeviceService::class)->forget($d);
                        Notification::make()->title('دستگاه حذف شد.')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('forgetInactive')
                    ->label('حذفِ انتخاب‌شده‌ها')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $d) {
                            app(DeviceService::class)->forget($d);
                        }
                        Notification::make()->title(count($records).' دستگاه حذف شد.')->success()->send();
                    }),
            ])
            ->paginated([10, 25]);
    }
}
