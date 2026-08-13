<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🐞 پیش‌نمایشِ فایل‌های کتاب در پنلِ ادمین.
 *
 * فایل‌های محتوا در storage/app/private نگه‌داری می‌شوند و عمداً URL عمومی
 * ندارند (همان چیزی که از دانلودِ بدون‌خریدِ محتوا جلوگیری می‌کند). ولی برای
 * اینکه ادمین بتواند کنارِ هر فایلِ صوتی یک پلیر و کنارِ هر تصویر یک بندانگشتی
 * ببیند، به یک آدرسِ قابل‌خواندن نیاز است. این کنترلر دقیقاً همان را می‌دهد و
 * صرفاً برای ادمینِ لاگین‌کرده کار می‌کند.
 */
class BookAssetController extends Controller
{
    public function show(Book $book, string $scope, string $kind, string $file): Response
    {
        // فقط ادمین — همان شرطی که ورود به پنل را کنترل می‌کند
        $user = Auth::user();
        if (! $user || ! (bool) ($user->is_admin ?? false)) {
            abort(403);
        }

        if (! in_array($scope, ['main', 'sample'], true)) {
            abort(404);
        }
        if (! in_array($kind, ['audio', 'images'], true)) {
            abort(404);
        }

        // فقط نامِ فایل پذیرفته می‌شود؛ هرگونه مسیرگردی رد می‌شود
        $name = basename($file);
        if ($name !== $file || $name === '' || str_contains($name, '..')) {
            abort(404);
        }

        $folder = $book->folder_name;
        $root = $scope === 'sample' ? "books/{$folder}/sample" : "books/{$folder}";
        $path = "{$root}/{$kind}/{$name}";

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        // inline تا مرورگر پخش/نمایش دهد نه اینکه دانلود کند
        return response()->file(Storage::disk('local')->path($path));
    }
}
