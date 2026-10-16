<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\UserDeletionService;
use App\Support\Mobile;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * 🌟 ADM-1..3: کاربران.
 * - فهرست: موبایل، تعدادِ خرید، تعدادِ موردهای «مرورِ من»، دستگاه‌ها، نسخه‌ی اپ، آخرین بازدید.
 * - ویرایش (ADM-1): نام، موبایل، ایمیل، ادمین‌بودن، کتاب‌های خریداری‌شده (فعال‌سازیِ دستی).
 * - رمز (ADM-2): ادمین هر رمزی می‌تواند بگذارد، حتی یک‌رقمی. قوانینِ رمزِ قوی فقط برای
 *   ثبت‌نامِ خودِ کاربران در سایت/اپ است.
 * - حذف (ADM-3): هر بار می‌پرسد «داده‌ها نگه داشته شوند یا پاک؟» (UserDeletionService).
 *   حذفِ خودِ ادمینِ واردشده ممکن نیست.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $modelLabel = 'کاربر';
    protected static ?string $pluralModelLabel = 'کاربران';
    protected static ?int $navigationSort = 5;

    /** کاربرانِ غیرفعال‌شده هم با فیلترِ «غیرفعال‌ها» دیده شوند. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('مشخصات')->schema([
                Grid::make(2)->schema([
                    TextInput::make('first_name')->label('نام')->maxLength(100),
                    TextInput::make('last_name')->label('نام خانوادگی')->maxLength(100),
                ]),
                TextInput::make('name')
                    ->label('نامِ نمایشی')
                    ->helperText('اگر نام و نام خانوادگی پر باشند، هنگامِ ذخیره خودکار از آن‌ها ساخته می‌شود.')
                    ->maxLength(255),
                Grid::make(2)->schema([
                    TextInput::make('mobile')
                        ->label('موبایل')
                        // ->tel() نه: الگوی پیش‌فرضش ارقامِ فارسی را رد می‌کند؛ Mobile::normalize
                        // هر شکلی را (۰۹…، ‎+98…) یکسان می‌کند.
                        ->placeholder('09121234567')
                        ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'tel'])
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (filled($value) && ! Mobile::normalize($value)) {
                                $fail('شماره‌ی موبایل معتبر نیست.');
                            }
                        })
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Mobile::normalize($state) : null)
                        ->unique(table: 'users', column: 'mobile', ignoreRecord: true),
                    TextInput::make('email')
                        ->label('ایمیل')
                        ->email()
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                        ->unique(table: 'users', column: 'email', ignoreRecord: true),
                ]),
                Toggle::make('is_admin')
                    ->label('ادمین (دسترسی به این پنل)')
                    ->disabled(fn (?User $record) => $record && $record->id === Auth::id())
                    ->helperText(fn (?User $record) => $record && $record->id === Auth::id()
                        ? 'ادمین‌بودنِ حسابِ خودتان را از این‌جا نمی‌شود برداشت.' : null),
            ]),

            Section::make('رمز عبور')
                ->description('برای کاربرانی که با ایمیل وارد می‌شوند (کاربرانِ موبایلی رمز لازم ندارند). ادمین هر رمزی می‌تواند بگذارد، حتی یک‌رقمی.')
                ->schema([
                    TextInput::make('password')
                        ->label(fn (string $operation) => $operation === 'create' ? 'رمز عبور' : 'رمزِ تازه')
                        ->password()
                        ->revealable()
                        ->minLength(1)
                        ->maxLength(255)
                        ->formatStateUsing(fn () => null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('خالی بگذارید تا رمزِ فعلی عوض نشود.'),
                ]),

            Section::make('خریدها')->schema([
                Select::make('purchasedBooks')
                    ->label('کتاب‌های خریداری‌شده')
                    ->relationship('purchasedBooks', 'title')
                    ->multiple()
                    ->preload()
                    ->helperText('افزودنِ کتاب این‌جا = فعال‌سازیِ دستی (بدونِ سفارش). سابقه‌ی پرداخت‌ها در «سفارش‌ها» است.'),
            ]),

            // 🌟 DEV-2: سقفِ دستگاه. فهرستِ دستگاه‌ها و «آزادسازی» در پایینِ صفحه‌ی ویرایش است.
            Section::make('دستگاه‌ها')->schema([
                TextInput::make('max_devices')
                    ->label('سقفِ دستگاه')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20)
                    ->placeholder(fn () => (string) config('devices.max_per_user', 1))
                    ->helperText(fn () => 'خالی = پیش‌فرض ('.config('devices.max_per_user', 1).'). مثلاً ۲ برای کسی که هم گوشی و هم کامپیوتر دارد.'),
            ]),

            Section::make('سابقه')
                ->visible(fn (string $operation) => $operation === 'edit')
                ->columns(3)
                ->schema([
                    Placeholder::make('created')->label('عضویت')
                        ->content(fn (?User $record) => $record?->created_at?->format('Y-m-d H:i')),
                    Placeholder::make('seen')->label('آخرین بازدید')
                        ->content(fn (?User $record) => $record?->last_seen_at?->diffForHumans() ?? '—'),
                    Placeholder::make('study')->label('موردهای «مرورِ من»')
                        ->content(fn (?User $record) => $record?->studyItems()->where('deleted', false)->count()),
                    Placeholder::make('orders')->label('سفارش‌ها')
                        ->content(fn (?User $record) => $record?->orders()->count()),
                    Placeholder::make('devices')->label('دستگاه‌های فعال')
                        ->content(fn (?User $record) => $record?->devices()->active()
                            ->get()->map(fn ($d) => $d->label())->join('، ') ?: '—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['purchasedBooks', 'studyItems' => fn ($s) => $s->where('deleted', false), 'devices' => fn ($d) => $d->active()])
                ->with(['devices' => fn ($d) => $d->latest('last_seen_at')]))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable()
                    ->description(fn (User $u) => $u->trashed() ? 'غیرفعال' : null),
                Tables\Columns\TextColumn::make('mobile')->label('موبایل')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('email')->label('ایمیل')->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_admin')->label('ادمین')->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('purchased_books_count')->label('خرید')->sortable(),
                Tables\Columns\TextColumn::make('study_items_count')->label('مرور')->sortable(),
                Tables\Columns\TextColumn::make('devices_count')->label('دستگاهِ فعال'),
                Tables\Columns\TextColumn::make('app_version')->label('نسخه‌ی اپ')
                    ->state(fn (User $u) => ($d = $u->devices->first()) ? trim($d->platform.' '.$d->app_version) : null),
                Tables\Columns\TextColumn::make('last_seen_at')->label('آخرین بازدید')->since()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('عضویت')->date()->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('buyers')->label('فقط خریداران')
                    ->query(fn (Builder $query) => $query->has('purchasedBooks')),
                Tables\Filters\TrashedFilter::make()->label('غیرفعال‌ها')
                    ->placeholder('فقط فعال‌ها')
                    ->trueLabel('همه')
                    ->falseLabel('فقط غیرفعال‌ها'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                static::configureDelete(Tables\Actions\Action::make('deleteUser')),
                Tables\Actions\RestoreAction::make()->label('فعال‌سازیِ دوباره'),
            ]);
    }

    /**
     * ADM-3: کنشِ حذف — برای جدول و صفحه‌ی ویرایش (هر دو نوع Action همین متدها را دارند).
     *
     * @template T of \Filament\Actions\Action|\Filament\Tables\Actions\Action
     *
     * @param  T  $action
     * @return T
     */
    public static function configureDelete($action)
    {
        return $action
            ->label('حذف')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (User $record) => $record->id !== Auth::id())
            ->modalHeading(fn (User $record) => 'حذفِ «'.$record->name.'»')
            ->modalDescription('داده‌های این کاربر (خریدها، سفارش‌ها و رسیدها، «مرورِ من»، تنظیمات، دستگاه‌ها) چه شوند؟')
            ->modalSubmitActionLabel('انجام شود')
            ->form([
                Radio::make('mode')
                    ->label('')
                    ->required()
                    ->default('keep')
                    ->options([
                        'keep' => 'نگه‌داشته شوند — حساب غیرفعال می‌شود (دیگر وارد نمی‌شود) و بعداً می‌شود برش گرداند.',
                        'purge' => 'پاک شوند — حساب و همه‌ی داده‌هایش برای همیشه حذف می‌شود.',
                    ]),
            ])
            ->action(function (User $record, array $data) {
                $service = app(UserDeletionService::class);
                if ($data['mode'] === 'purge') {
                    $service->purge($record);
                    Notification::make()->title('کاربر و همه‌ی داده‌هایش حذف شد.')->success()->send();
                } else {
                    $service->deactivate($record);
                    Notification::make()->title('حساب غیرفعال شد؛ داده‌ها نگه داشته شد.')->success()->send();
                }
            });
    }

    public static function getRelations(): array
    {
        return [
            UserResource\RelationManagers\DevicesRelationManager::class,
            UserResource\RelationManagers\OrdersRelationManager::class, // 🌟 ADM-4
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
