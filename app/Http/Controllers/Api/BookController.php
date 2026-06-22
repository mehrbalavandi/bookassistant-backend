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
        // فلاتر باید مسیر فایلی که می‌خواهد دانلود کند را در کوئری پارامتر بفرستد
        // مثال: api/books/1/download?path=books/ielts-1/audio/track1.mp3
        $requestedPath = $request->query('path');

        if (!$requestedPath) {
            return response()->json(['message' => 'مسیر فایل درخواستی مشخص نشده است.'], 400);
        }

        // بررسی اینکه آیا فایل در سرور وجود فیزیکی دارد یا خیر
        if (!Storage::exists($requestedPath)) {
            return response()->json(['message' => 'فایل مورد نظر در سرور یافت نشد.'], 404);
        }

        // ==========================================
        // حالت اول: آیا فایل درخواستی جزو فایل‌های نمونه (رایگان) است؟
        // ==========================================
        if ($this->isSampleFile($book, $requestedPath)) {
            // اجازه دانلود بی‌قید و شرط برای همه
            return Storage::download($requestedPath);
        }

        // ==========================================
        // حالت دوم: فایل نمونه نیست، پس حتماً فایل اصلی است. آیا کاربر حقرسی دائمی دارد؟
        // ==========================================

        // ۱. بررسی داشتن توکن
        if (!$request->bearerToken()) {
            return response()->json(['message' => 'این فایل پولی است. لطفاً ابتدا لاگین کنید.'], 401);
        }

        /** @var \App\Models\User $user */
        $user = auth('sanctum')->user();

        // ۲. بررسی معتبر بودن کاربر و داشتن حق اشتراک دائمی
        if (!$user || !$user->purchasedBooks()->where('books.id', $book->id)->exists()) {
            return response()->json(['message' => 'شما این کتاب را خریداری نکرده‌اید.'], 403);
        }

        // ۳. بررسی نهایی: آیا مسیر درخواستی واقعاً متعلق به فایل‌های اصلی همین کتاب است؟
        if ($this->isMainFile($book, $requestedPath)) {
            return Storage::download($requestedPath);
        }

        // اگر فایل نه نمونه بود و نه اصلی، درخواست مشکوک است
        return response()->json(['message' => 'درخواست غیرمجاز.'], 403);
    }

    // ---------------------------------------------------------
    // توابع کمکی (Helper Methods) برای تمیز نگه داشتن کد
    // ---------------------------------------------------------

    private function isSampleFile(Book $book, $path)
    {
        if ($book->sample_file_path === $path) return true;
        if (is_array($book->sample_audio_files) && in_array($path, $book->sample_audio_files)) return true;
        if (is_array($book->sample_images) && in_array($path, $book->sample_images)) return true;
        return false;
    }

    private function isMainFile(Book $book, $path)
    {
        if ($book->json_file === $path) return true;
        if (is_array($book->audio_files) && in_array($path, $book->audio_files)) return true;
        if (is_array($book->images) && in_array($path, $book->images)) return true;
        return false;
    }
}
