<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 🌟 PAY-8: سفارش‌های ناتمام (در انتظارِ پرداخت، لغوشده، ناموفق؛ بی‌رسید) برای کتاب‌هایی که کاربر
 * از راهِ دیگری خریده است، پاک می‌شوند. از این به بعد OrderService::grant همین کار را می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->whereIn('status', ['pending', 'canceled', 'failed'])
            ->whereNull('receipt_path')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('book_user')
                ->whereColumn('book_user.user_id', 'orders.user_id')
                ->whereColumn('book_user.book_id', 'orders.book_id'))
            ->delete();
    }

    public function down(): void
    {
        // حذفِ داده؛ برگشت‌پذیر نیست.
    }
};
