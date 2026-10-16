<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\UserDevice;
use App\Services\DeviceService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 🌟 DEV-2: دستگاه‌های یک کاربر در صفحه‌ی ویرایشِ کاربر.
 * ادمین هر دستگاهِ فعالی را «آزاد» می‌کند (بی‌محدودیتِ زمانی؛ در فاصله‌ی آزادسازیِ خودِ کاربر
 * حساب نمی‌شود). دستگاهِ آزادشده در اولین اتصال از حساب خارج و محتوای کاملِ کتاب‌هایش پاک می‌شود.
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

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->orderByRaw('revoked_at is not null')
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
                    ->state(fn (UserDevice $d) => $d->label()),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()
                    ->state(fn (UserDevice $d) => match (true) {
                        $d->isActive() => 'فعال',
                        $d->revoked_at !== null => $d->revoked_by === 'admin' ? 'آزادشده (ادمین)' : 'آزادشده (کاربر)',
                        default => 'ثبت‌نشده (نسخه‌ی قدیمی)',
                    })
                    ->color(fn (UserDevice $d) => $d->isActive() ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('app_version')->label('نسخه‌ی اپ'),
                Tables\Columns\TextColumn::make('activated_at')->label('فعال از')->dateTime('Y-m-d H:i'),
                Tables\Columns\TextColumn::make('last_seen_at')->label('آخرین بازدید')->since(),
                Tables\Columns\TextColumn::make('revoked_at')->label('آزادسازی')->dateTime('Y-m-d H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('device_id')->label('شناسه')->limit(14)
                    ->tooltip(fn (UserDevice $d) => $d->device_id)->toggleable(isToggledHiddenByDefault: true),
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
            ])
            ->paginated([10, 25]);
    }
}
