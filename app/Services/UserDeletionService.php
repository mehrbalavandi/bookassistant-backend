<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 🌟 ADM-3: حذفِ کاربر به دو شکل — ادمین هر بار انتخاب می‌کند.
 *
 * - **غیرفعال‌سازی (نگه‌داشتنِ داده‌ها):** soft delete. همه‌ی توکن‌ها و نشست‌ها باطل
 *   می‌شوند و کاربر دیگر نه با موبایل و نه با ایمیل وارد نمی‌شود؛ خریدها، سفارش‌ها،
 *   رسیدها، «مرورِ من»، تنظیمات و دستگاه‌ها سرِ جایشان می‌مانند. قابلِ بازگرداندن.
 * - **حذفِ کامل:** حساب و همه‌ی داده‌هایش: خریدها (book_user)، سفارش‌ها و فایلِ رسیدها،
 *   «مرورِ من»، تنظیمات، دستگاه‌ها، توکن‌ها، نشست‌ها، کدهای ورود. برگشت‌ناپذیر.
 */
class UserDeletionService
{
    public function deactivate(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }

    public function purge(User $user): void
    {
        $receipts = $user->orders()->whereNotNull('receipt_path')->pluck('receipt_path')->all();

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            if ($user->mobile) {
                OtpCode::where('mobile', $user->mobile)->delete();
            }
            // جدول‌های وابسته کلیدِ خارجیِ cascade دارند (book_user، orders، study_items،
            // user_settings، user_devices)؛ صریح هم پاک می‌کنیم تا روی هر دیتابیسی مطمئن باشد.
            $user->purchasedBooks()->detach();
            $user->orders()->delete();
            $user->studyItems()->delete();
            $user->settingsBackup()->delete();
            $user->devices()->delete();
            $user->forceDelete();
        });

        foreach ($receipts as $path) {
            Storage::disk('local')->delete($path);
        }
        Storage::disk('local')->deleteDirectory('receipts/'.$user->id);
    }

    public function restore(User $user): void
    {
        $user->restore();
    }
}
