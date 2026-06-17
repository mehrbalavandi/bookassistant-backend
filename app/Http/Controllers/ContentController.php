<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContentController extends Controller
{
    // ۱. ارسال لیست سبک شامل آی‌دی، عنوان و نسخه جهت مقایسه در Realm فلاتر
    public function index() {
        $contents = Content::select('id', 'title', 'version', 'text_content')->get();
        return response()->json($contents, 200);
    }

    // ۲. دانلود امن فایل صوتی پس از تایید اشتراک
    public function downloadSecureFile($id) {
        $user = auth()->user();

        // بررسی اینکه آیا کاربر هزینه را پرداخت کرده و اشتراک دارد؟
        if (!$user->is_premium) {
            return response()->json(['message' => 'برای دانلود این فایل باید اشتراک تهیه کنید.'], 403);
        }

        $content = Content::find($id);
        if (!$content) {
            return response()->json(['message' => 'فایل یافت نشد.'], 404);
        }

        // مسیر فایل صوتی در پوشه امن و غیرقابل دسترس عمومی لاراول
        $filePath = 'private_audios/' . $content->audio_filename;

        if (!Storage::exists($filePath)) {
            return response()->json(['message' => 'فایل صوتی روی سرور موجود نیست.'], 404);
        }

        // دانلود مستقیم فایل بدون لو رفتن آدرس واقعی آن
        return Storage::download($filePath, $content->title . '.mp3');
    }
}
