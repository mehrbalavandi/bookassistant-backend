<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * 🌟 ADM-4: سفارش‌های همین کاربر در صفحه‌ی ویرایشِ کاربر — همان جدول و کنش‌های «سفارش‌ها»
 * (رسید، تأیید، رد، ویرایش، حذف).
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'سفارش‌ها';

    protected static ?string $modelLabel = 'سفارش';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return OrderResource::table($table)->paginated([10, 25]);
    }
}
