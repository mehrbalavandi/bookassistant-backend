<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** زبانه‌ی «منتظرِ بررسی» اول، چون کارِ روزانه‌ی ادمین همین است. */
    public function getTabs(): array
    {
        return [
            'review' => Tab::make('منتظرِ بررسی')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Order::AWAITING_REVIEW))
                ->badge(Order::where('status', Order::AWAITING_REVIEW)->count() ?: null),
            'paid' => Tab::make('پرداخت‌شده')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Order::PAID)),
            'all' => Tab::make('همه'),
        ];
    }
}
