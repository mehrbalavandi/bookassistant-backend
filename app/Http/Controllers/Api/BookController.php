<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Book;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class BookController extends Controller
{
    // ۱. ویترین عمومی: برگرداندن لیست تمام کتاب‌ها
    public function index(Request $request)
    {
        $books = Book::select([
            'id',
            'title',
            'folder_name',
            'sample_file_path',
            'sample_audio_files',
            'sample_images',
            'sample_version',
            'json_file',
            'json_version',
            'audio_files',
            'audio_version',
            'images',
            'images_version'
        ])->get();

        $purchasedBookIds = [];

        if ($request->bearerToken()) {
            /** @var \App\Models\User $user */
            $user = auth('sanctum')->user();
            if ($user) {
                $purchasedBookIds = $user->purchasedBooks()->pluck('books.id')->toArray();
            }
        }

        $books->transform(function ($book) use ($purchasedBookIds) {
            $book->is_purchased = in_array($book->id, $purchasedBookIds);

            // قفل امنیتی سخت‌گیرانه:
            // اگر کاربر هزینه کتاب را پرداخت نکرده باشد، مسیر فایل‌های اصلی کاملاً null و خالی ارسال می‌شود.
            if (!$book->is_purchased) {
                $book->json_file = null;
                $book->audio_files = [];
                $book->images = [];
            }

            return $book;
        });

        return response()->json([
            'success' => true,
            'data' => $books
        ], 200);
    }

    // ۲. دانلود نسخه نمونه (برای همه آزاد است)
    public function downloadSample(Book $book)
    {
        $samplePath = $book->sample_file_path;

        if (!$samplePath || !Storage::exists($samplePath)) {
            return response()->json([
                'success' => false,
                'message' => 'نسخه نمونه برای این کتاب موجود نیست.'
            ], 404);
        }

        return Storage::download($samplePath);
    }

    public function myBooks(Request $request)
    {
        // استخراج کاربری که توکن را ارسال کرده است
        $user = $request->user();

        // دریافت کتاب‌های کاربر به همراه فیلدهای ضروری
        $books = $user->purchasedBooks()->select('books.id', 'title')->get();

        return response()->json([
            'success' => true,
            'data' => $books
        ], 200);
    }

    public function download(Request $request, Book $book)
    {
        $user = $request->user();

        // ۱. بررسی امنیتی: آیا این کتاب در لیست خریدهای کاربر وجود دارد؟
        $hasPurchased = $user->purchasedBooks()->where('book_id', $book->id)->exists();

        if (!$hasPurchased) {
            // اگر حق اشتراک نداشت، با کد 403 (Forbidden) دسترسی را مسدود می‌کنیم
            return response()->json([
                'success' => false,
                'message' => 'شما حق اشتراک این کتاب را تهیه نکرده‌اید و اجازه دانلود ندارید.'
            ], 403);
        }

        // ۲. پیدا کردن مسیر فایل در سرور
        // فرض می‌کنیم در جدول books فیلدی به نام file_path دارید که آدرس فایل دانلودی در آن ذخیره شده است.
        // مثال: 'books/ielts_vocab_package.zip'
        $filePath = $book->file_path;

        // بررسی اینکه آیا فایل واقعاً در سرور وجود دارد یا نه
        if (!$filePath || !Storage::exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'فایل این کتاب در سرور یافت نشد. لطفاً با پشتیبانی تماس بگیرید.'
            ], 404);
        }

        // ۳. ارسال مستقیم فایل برای دانلود به سمت فلاتر
        // این دستور، فایل را به صورت Stream به کلاینت می‌فرستد و آدرس واقعی فایل در سرور را مخفی نگه می‌دارد.
        return Storage::download($filePath);
    }
}
