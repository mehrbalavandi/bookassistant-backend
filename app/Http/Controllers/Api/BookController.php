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
        // مثال: api/books/1/download?path=books/ielts-1/pages/page_0001.json
        $requestedPath = $request->query('path');

        if (!$requestedPath) {
            return response()->json(['message' => 'مسیر فایل درخواستی مشخص نشده است.'], 400);
        }

        // 🌟 ۱. مسیر باید داخل پوشه‌ی همین کتاب باشد (نه هر مسیرِ دلخواهی روی دیسک)
        $bookRoot = "books/{$book->folder_name}/";
        if (!str_starts_with($requestedPath, $bookRoot)) {
            return response()->json(['message' => 'مسیر متعلق به این کتاب نیست.'], 403);
        }

        // 🌟 ۲. هر مسیرِ زیرِ .../sample/ رایگان و عمومی است؛ صرف‌نظر از اینکه
        // index.json باشد یا یکی از فایل‌های pages/audio/images داخل آن
        $isSample = str_starts_with($requestedPath, $bookRoot . 'sample/');

        if (!$isSample) {
            // بقیه (محتوای اصلی) نیازمند توکن + خرید است
            if (!$request->bearerToken()) {
                return response()->json(['message' => 'این فایل پولی است. لطفاً ابتدا لاگین کنید.'], 401);
            }

            /** @var \App\Models\User $user */
            $user = auth('sanctum')->user();

            if (!$user || !$user->purchasedBooks()->where('books.id', $book->id)->exists()) {
                return response()->json(['message' => 'شما این کتاب را خریداری نکرده‌اید.'], 403);
            }
        }

        // بررسی وجودِ فیزیکیِ فایل (بعد از احرازِ هویت، تا وجودِ فایل هیچ اطلاعاتی لو ندهد)
        if (!Storage::exists($requestedPath)) {
            return response()->json(['message' => 'فایل مورد نظر در سرور یافت نشد.'], 404);
        }

        return Storage::download($requestedPath);
    }
}
