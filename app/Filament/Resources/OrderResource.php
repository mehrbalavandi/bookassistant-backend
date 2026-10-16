<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * 🌟 PAY-4: سفارش‌ها در پنل. همه‌ی تلاش‌های خرید (درگاه و کارت‌به‌کارت) با وضعیت؛
 * رسیدهای کارت‌به‌کارت با «تأیید» (کتاب فعال می‌شود و پیامک می‌رود) یا «رد» (با دلیل).
 * شمارِ رسیدهای منتظرِ بررسی روی منو دیده می‌شود.
 * 🌟 ADM-4: «ویرایش» (مبلغ، وضعیت، روش، کدهای پیگیری، یادداشت) و «حذف»؛ هر دو در صفحه‌ی ویرایشِ
 * کاربر هم هست (OrdersRelationManager). پرداخت‌شده‌کردن کتاب را فعال می‌کند؛ برگرداندن یا حذفِ
 * سفارشِ پرداخت‌شده فقط با تیکِ «کتاب پس گرفته شود» دسترسی را برمی‌دارد.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $modelLabel = 'سفارش';
    protected static ?string $pluralModelLabel = 'سفارش‌ها';
    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $n = Order::where('status', Order::AWAITING_REVIEW)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'book']))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('کاربر')
                    ->description(fn (Order $o) => $o->user?->mobile ?? $o->user?->email)
                    ->searchable(['name', 'mobile', 'email']),
                Tables\Columns\TextColumn::make('book.title')->label('کتاب')->limit(30),
                Tables\Columns\TextColumn::make('amount')->label('مبلغ (تومان)')
                    ->formatStateUsing(fn ($state) => number_format((int) $state))->sortable(),
                Tables\Columns\TextColumn::make('method')->label('روش')
                    ->formatStateUsing(fn ($state) => $state === Order::METHOD_CARD ? 'کارت‌به‌کارت' : 'درگاه'),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn ($state) => Order::STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        Order::PAID => 'success',
                        Order::AWAITING_REVIEW => 'warning',
                        Order::FAILED, Order::REJECTED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('card_tracking_code')->label('کدِ پیگیریِ کارت')
                    ->description(fn (Order $o) => $o->card_last4 ? '****'.$o->card_last4 : null)
                    ->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('reference_id')->label('کدِ پیگیریِ درگاه')
                    ->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('source')->label('از')
                    ->formatStateUsing(fn ($state) => $state === 'web' ? 'سایت' : 'اپ')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('زمان')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('وضعیت')->options(Order::STATUS_LABELS),
                Tables\Filters\SelectFilter::make('method')->label('روش')
                    ->options([Order::METHOD_GATEWAY => 'درگاه', Order::METHOD_CARD => 'کارت‌به‌کارت']),
            ])
            ->actions([
                Tables\Actions\Action::make('receipt')
                    ->label('رسید')
                    ->icon('heroicon-o-photo')
                    ->visible(fn (Order $o) => (bool) $o->receipt_path)
                    ->modalHeading(fn (Order $o) => 'رسیدِ سفارشِ '.$o->id)
                    ->modalContent(fn (Order $o) => new HtmlString(
                        '<div style="line-height:2">'
                        .'<div>کدِ پیگیری: <b dir="ltr">'.e($o->card_tracking_code).'</b></div>'
                        .($o->card_last4 ? '<div>چهار رقمِ آخرِ کارتِ پرداخت‌کننده: <b dir="ltr">'.e($o->card_last4).'</b></div>' : '')
                        .($o->card_paid_at ? '<div>زمانِ واریز (به گفته‌ی کاربر): '.e($o->card_paid_at).'</div>' : '')
                        .'<div>مبلغ: <b>'.number_format($o->amount).' تومان</b></div>'
                        .'<img src="'.route('admin.order-receipt', $o).'" style="max-width:100%;margin-top:12px;border-radius:8px">'
                        .'</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن'),

                Tables\Actions\Action::make('approve')
                    ->label('تأیید و فعال‌سازی')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $o) => $o->method === Order::METHOD_CARD
                        && in_array($o->status, [Order::AWAITING_REVIEW, Order::REJECTED, Order::PENDING], true))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Order $o) => 'پیش از تأیید، واریزِ '.number_format($o->amount).' تومان با کدِ پیگیریِ «'.$o->card_tracking_code.'» را در حسابِ بانکی ببینید.')
                    ->action(function (Order $o) {
                        app(OrderService::class)->approve($o, Auth::user());
                        Notification::make()->title('کتاب برای کاربر فعال شد.')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('رد')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $o) => $o->method === Order::METHOD_CARD && $o->status === Order::AWAITING_REVIEW)
                    ->form([
                        Textarea::make('reason')->label('دلیل (به کاربر نشان داده می‌شود)')
                            ->default('واریزی با این مشخصات پیدا نشد.')->required()->maxLength(500),
                    ])
                    ->action(function (Order $o, array $data) {
                        app(OrderService::class)->reject($o, Auth::user(), $data['reason']);
                        Notification::make()->title('سفارش رد شد.')->warning()->send();
                    }),

                static::editAction(),
                static::deleteAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('deleteOrders')
                    ->label('حذفِ انتخاب‌شده‌ها')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Toggle::make('revoke')->label('کتابِ سفارش‌های پرداخت‌شده هم از کاربران گرفته شود')->default(false),
                    ])
                    ->action(function ($records, array $data) {
                        foreach ($records as $o) {
                            app(OrderService::class)->adminDelete($o, (bool) $data['revoke']);
                        }
                        Notification::make()->title(count($records).' سفارش حذف شد.')->success()->send();
                    }),
            ]);
    }

    /** 🌟 ADM-4: ویرایشِ سفارش (جدولِ سفارش‌ها و صفحه‌ی ویرایشِ کاربر). */
    public static function editAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('editOrder')
            ->label('ویرایش')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading(fn (Order $o) => 'ویرایشِ سفارشِ '.$o->id)
            ->fillForm(fn (Order $o) => $o->only([
                'amount', 'status', 'method', 'card_tracking_code', 'card_last4', 'reference_id', 'admin_note',
            ]))
            ->form(fn (Order $o) => [
                Grid::make(2)->schema([
                    TextInput::make('amount')->label('مبلغ (تومان)')->numeric()->minValue(0)->required(),
                    Select::make('method')->label('روش')->required()
                        ->options([Order::METHOD_GATEWAY => 'درگاه', Order::METHOD_CARD => 'کارت‌به‌کارت']),
                    Select::make('status')->label('وضعیت')->options(Order::STATUS_LABELS)->required()->live(),
                    TextInput::make('reference_id')->label('کدِ پیگیریِ درگاه')->maxLength(191),
                    TextInput::make('card_tracking_code')->label('کدِ پیگیریِ کارت')->maxLength(64),
                    TextInput::make('card_last4')->label('۴ رقمِ آخرِ کارت')->maxLength(4),
                ]),
                Textarea::make('admin_note')->label('یادداشت / دلیلِ رد (کاربر می‌بیند)')->rows(2)->maxLength(500),
                Toggle::make('notify')->label('پیامکِ «کتاب فعال شد» به کاربر')
                    ->visible(fn (Get $get) => $o->status !== Order::PAID && $get('status') === Order::PAID),
                Toggle::make('revoke')->label('کتاب از کاربر پس گرفته شود')
                    ->helperText('فقط اگر کاربر سفارشِ پرداخت‌شده‌ی دیگری برای همین کتاب نداشته باشد.')
                    ->visible(fn (Get $get) => $o->status === Order::PAID && $get('status') !== Order::PAID),
            ])
            ->action(function (Order $o, array $data) {
                app(OrderService::class)->adminUpdate(
                    $o,
                    $data,
                    Auth::user(),
                    revoke: (bool) ($data['revoke'] ?? false),
                    notify: (bool) ($data['notify'] ?? false),
                );
                Notification::make()->title('سفارش ذخیره شد.')->success()->send();
            });
    }

    /** 🌟 ADM-4: حذفِ سفارش (و فایلِ رسید). */
    public static function deleteAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('deleteOrder')
            ->label('حذف')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Order $o) => 'حذفِ سفارشِ '.$o->id)
            ->modalDescription('سفارش و تصویرِ رسیدش برای همیشه پاک می‌شوند.')
            ->form(fn (Order $o) => $o->status === Order::PAID ? [
                Toggle::make('revoke')->label('کتاب هم از کاربر پس گرفته شود')
                    ->helperText('خاموش: کاربر کتاب را نگه می‌دارد (فقط سابقه‌ی سفارش پاک می‌شود).')
                    ->default(false),
            ] : [])
            ->action(function (Order $o, array $data) {
                app(OrderService::class)->adminDelete($o, (bool) ($data['revoke'] ?? false));
                Notification::make()->title('سفارش حذف شد.')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }
}
