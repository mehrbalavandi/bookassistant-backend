<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Forms\Components\Textarea;
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }
}
