<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index()
    {
        // گرفتن تمام کتاب‌ها از دیتابیس
        $books = Book::all();

        // ارسال پاسخ به صورت JSON با کد وضعیت 200 (موفقیت‌آمیز)
        return response()->json([
            'success' => true,
            'data' => $books
        ], 200);
    }

    // ۱. دریافت لیست تمام کتاب‌ها (برای صفحه اصلی اپلیکیشن)
    public function index2()
    {
        $books = Book::select('id', 'title', 'folder_name', 'images')->get();

        $data = $books->map(function ($book) {
            // ساخت لینک کامل برای تصویر کاور تا فلاتر بتواند آن را دانلود کند
            $coverUrl = null;
            if (!empty($book->images) && is_array($book->images)) {
                $coverUrl = asset('storage/' . $book->images[0]);
            }

            return [
                'id' => $book->id,
                'title' => $book->title,
                'cover_image' => $coverUrl,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // ۲. دریافت جزئیات یک کتاب خاص (فایل‌های صوتی و محتوای آموزشی JSON)
    public function show($id)
    {
        $book = Book::findOrFail($id);

        // خواندن مستقیم فایل JSON از سرور و تبدیل آن به آرایه برای ارسال به فلاتر
        $jsonContent = null;
        if ($book->json_file) {
            $jsonContent = json_decode(Storage::disk('public')->get($book->json_file), true);
        }

        // ساخت لینک کامل برای تمام فایل‌های صوتی
        $audioUrls = [];
        if (!empty($book->audio_files) && is_array($book->audio_files)) {
            foreach ($book->audio_files as $audio) {
                $audioUrls[] = asset('storage/' . $audio);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $book->id,
                'title' => $book->title,
                'audio_files' => $audioUrls,
                // کل ساختار درسی، لغات و ترجمه‌ها مستقیماً اینجا به فلاتر پاس داده می‌شود:
                'course_content' => $jsonContent 
            ]
        ]);
    }

    public function myBooks(Request $request)
    {
        // استخراج کاربری که توکن را ارسال کرده است
        $user = $request->user();

        // دریافت کتاب‌های کاربر به همراه فیلدهای ضروری
        $books = $user->purchasedBooks()->select('books.id', 'title', 'cover_image', 'cefr_level')->get();

        return response()->json([
            'success' => true,
            'data' => $books
        ], 200);
    }    
}