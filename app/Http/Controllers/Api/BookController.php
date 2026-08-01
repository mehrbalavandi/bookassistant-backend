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

    // ────────────────── دانلودِ یکجا به‌صورت ZIP ──────────────────
    // 🌟 چرا: با زیادشدنِ صفحات/صوت/تصویر، دانلودِ فایل‌به‌فایل (صدها درخواستِ
    // جدا) بسیار کند می‌شود. این‌جا یک آرشیوِ واحد ساخته و کش می‌شود و
    // به‌صورتِ یک فایلِ واقعی روی دیسک سرو می‌شود — چون response()->file()
    // یک BinaryFileResponse برمی‌گرداند، هدرِ Range به‌طور خودکار پشتیبانی
    // می‌شود و همین چیزی است که pause/resume را ممکن می‌کند.

    /// بررسیِ دسترسی (دقیقاً همان قواعدِ download): نمونه آزاد، اصلی نیازمندِ خرید.
    /// اگر مجاز باشد null برمی‌گرداند، وگرنه پاسخِ خطا.
    private function authorizeScope(Request $request, Book $book, string $scope)
    {
        if ($scope === 'sample') {
            return null;
        }

        if (!$request->bearerToken()) {
            return response()->json(['message' => 'این محتوا پولی است. لطفاً ابتدا لاگین کنید.'], 401);
        }

        /** @var \App\Models\User $user */
        $user = auth('sanctum')->user();

        if (!$user || !$user->purchasedBooks()->where('books.id', $book->id)->exists()) {
            return response()->json(['message' => 'شما این کتاب را خریداری نکرده‌اید.'], 403);
        }

        return null;
    }

    /// نامِ آرشیو شاملِ شماره‌ی نسخه‌هاست — یعنی با هر آپلودِ جدید (که نسخه‌ها
    /// را increment می‌کند) نامِ فایل عوض می‌شود و آرشیوِ تازه ساخته می‌شود؛
    /// نیازی به invalidate کردنِ دستیِ کش نیست.
    private function zipRelativePath(Book $book, string $scope): string
    {
        $name = $scope === 'sample'
            ? "sample_v{$book->sample_version}.zip"
            : "main_j{$book->json_version}_a{$book->audio_version}_i{$book->images_version}.zip";

        return "books/{$book->folder_name}/archive/{$name}";
    }

    /// اگر آرشیو وجود نداشته باشد می‌سازدش و مسیرِ نسبی‌اش را برمی‌گرداند
    /// (یا null اگر محتوایی برای بسته‌بندی نباشد).
    private function ensureZip(Book $book, string $scope): ?string
    {
        $zipRel = $this->zipRelativePath($book, $scope);

        if (Storage::exists($zipRel)) {
            return $zipRel;
        }

        $sourceRel = $scope === 'sample'
            ? "books/{$book->folder_name}/sample"
            : "books/{$book->folder_name}";

        $sourceAbs = Storage::path($sourceRel);
        if (!is_dir($sourceAbs)) {
            return null;
        }

        $zipAbs = Storage::path($zipRel);
        $zipDir = dirname($zipAbs);
        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0775, true);
        }

        // آرشیوهای قدیمیِ همین scope را پاک کن تا فضا هدر نرود
        $prefix = $scope === 'sample' ? 'sample_' : 'main_';
        foreach (Storage::files("books/{$book->folder_name}/archive") as $old) {
            if (str_starts_with(basename($old), $prefix) && $old !== $zipRel) {
                Storage::delete($old);
            }
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipAbs, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceAbs, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $added = 0;
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }

            // مسیرِ نسبی نسبت به ریشه‌ی محتوا، با اسلشِ یک‌دست
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceAbs) + 1));
            $topLevel = explode('/', $relative)[0];

            // پوشه‌ی نمونه و پوشه‌ی خودِ آرشیوها هرگز داخلِ آرشیوِ اصلی نروند
            if ($scope !== 'sample' && ($topLevel === 'sample' || $topLevel === 'archive')) {
                continue;
            }
            if ($scope === 'sample' && $topLevel === 'archive') {
                continue;
            }

            // 🌟 نکته‌ی مهم: اپِ فلاتر فایل‌های صوتی/تصویری را کنارِ خودِ
            // index.json (نه در زیرپوشه) می‌جوید — پس audio/ و images/ را
            // صاف می‌کنیم، ولی pages/ و audio_scripts/ ساختارشان حفظ می‌شود.
            $entryName = ($topLevel === 'audio' || $topLevel === 'images')
                ? basename($relative)
                : $relative;

            $zip->addFile($item->getPathname(), $entryName);
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            Storage::delete($zipRel);
            return null;
        }

        return $zipRel;
    }

    /// حجم و شناسه‌ی آرشیو — فلاتر قبل از شروع، این را می‌گیرد تا هم درصدِ
    /// پیشرفت را درست حساب کند و هم بفهمد فایلِ نیمه‌دانلودشده‌ی قبلی هنوز
    /// معتبر است یا باید از صفر شروع کند.
    public function zipInfo(Request $request, Book $book)
    {
        $scope = $request->query('scope') === 'sample' ? 'sample' : 'main';

        if ($denied = $this->authorizeScope($request, $book, $scope)) {
            return $denied;
        }

        $zipRel = $this->ensureZip($book, $scope);
        if (!$zipRel) {
            return response()->json(['message' => 'محتوایی برای دانلود موجود نیست.'], 404);
        }

        return response()->json([
            'success'    => true,
            'scope'      => $scope,
            'file_name'  => basename($zipRel),
            'size'       => Storage::size($zipRel),
            'supports_resume' => true,
        ], 200);
    }

    public function downloadZip(Request $request, Book $book)
    {
        $scope = $request->query('scope') === 'sample' ? 'sample' : 'main';

        if ($denied = $this->authorizeScope($request, $book, $scope)) {
            return $denied;
        }

        $zipRel = $this->ensureZip($book, $scope);
        if (!$zipRel) {
            return response()->json(['message' => 'محتوایی برای دانلود موجود نیست.'], 404);
        }

        // response()->file یک BinaryFileResponse می‌سازد که خودش هدرِ Range را
        // مدیریت می‌کند (۲۰۶ Partial Content) — پایه‌ی resume شدن دانلود.
        return response()->file(Storage::path($zipRel), [
            'Content-Type'        => 'application/zip',
            'Accept-Ranges'       => 'bytes',
            'Content-Disposition' => 'attachment; filename="' . basename($zipRel) . '"',
        ]);
    }
}
