<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Book;
use App\Services\DeviceService;
use App\Services\LicenseService;
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
            'images_version',
            'category_id', // 🌟 دسته‌ی کتاب
            'sort_order',  // 🌟 شماره‌ی ردیف داخلِ دسته
            // 🌟 SF-1: معرفی در ویترین (قیمت، جلد، توضیح، …)
            'price',
            'discount',
            'cover_image',
            'description',
            'author',
            'publisher',
            'level',
        ])
            // 🌟 هر کتاب شیءِ category را هم دارد ({id, title, description, sort_order})
            // یا null برای «سایر کتاب‌ها». اپ از همین گروه‌بندی و مرتب می‌کند.
            ->with('category:id,title,description,sort_order')
            ->get();
        // 🌟 ترتیب: دسته‌ها به sort_orderِ دسته، کتاب‌ها به sort_orderِ خودشان —
        // نه ترتیبِ آپلود. نسخه‌های قدیمیِ اپ هم که گروه‌بندی نمی‌کنند، دست‌کم
        // همین ترتیبِ درست را می‌بینند.
        $books = Book::sortForCatalog($books);

        $purchasedBookIds = [];
        // 🌟 DEV-1..4: وضعیتِ نشست و مجوزِ آفلاینِ همین دستگاه. توکنی که دستگاهش آزاد شده یا
        // از دستگاهِ دیگری آمده این‌جا کاربری برنمی‌گرداند (DeviceService::tokenAllowed)؛
        // session.reason به اپ می‌گوید چرا، تا خارج شود و در صورتِ لزوم محتوا را پاک کند.
        $session = null;
        $license = null;

        if ($request->bearerToken()) {
            /** @var \App\Models\User $user */
            $user = auth('sanctum')->user();
            if ($user) {
                $purchasedBookIds = $user->purchasedBooks()->pluck('books.id')->toArray();
                if ($device = app(DeviceService::class)->current($request)) {
                    $session = ['valid' => true, 'bound' => true];
                    $license = app(LicenseService::class)->issue($user, $device);
                } else {
                    // توکنِ قدیمیِ بی‌دستگاه (فقط در دوره‌ی انتقال پذیرفته می‌شود): اپِ تازه با
                    // bound=false یک‌بار خارج می‌شود تا با ورودِ دوباره دستگاهش ثبت شود و مجوز بگیرد.
                    $session = [
                        'valid' => true,
                        'bound' => false,
                        'reason' => 'unbound',
                        'message' => DeviceService::deniedMessage('unbound'),
                    ];
                }
            } else {
                $reason = $request->attributes->get(DeviceService::DENIED_ATTRIBUTE) ?? 'invalid_token';
                $session = [
                    'valid' => false,
                    'reason' => $reason,
                    'message' => DeviceService::deniedMessage($reason),
                ];
            }
        }

        $books->transform(function ($book) use ($purchasedBookIds) {
            $book->is_purchased = in_array($book->id, $purchasedBookIds);

            // 🌟 SF-1: آنچه اپ پیش از خرید یا دانلودِ نمونه نشان می‌دهد.
            // has_sample: دکمه‌ی «دریافت نمونه» فقط وقتی نمونه واقعاً روی سرور هست فعال است.
            $book->has_sample = $book->hasSample();
            $book->final_price = (int) round($book->final_price);
            $book->page_count = $book->pageCount();
            $book->audio_count = count($book->audio_files ?? []);
            // مسیرِ نسبی تا اپ آن را با همان آدرسِ سروری که تنظیم کرده بسازد؛ ?v= برای کشِ اپ.
            $book->cover_version = $book->hasCover() ? $book->coverVersion() : null;
            $book->cover_url = $book->cover_version
                ? "/api/books/{$book->id}/cover?v={$book->cover_version}"
                : null;
            $book->makeHidden('cover_image');

            // قفل امنیتی سخت‌گیرانه:
            // اگر کاربر هزینه کتاب را پرداخت نکرده باشد، مسیر فایل‌های اصلی کاملاً null و خالی ارسال می‌شود.
            if (!$book->is_purchased) {
                $book->json_file = null;
                $book->audio_files = [];
                $book->images = [];
            }

            return $book;
        });

        return response()->json(array_filter([
            'success' => true,
            'data' => $books,
            'session' => $session,
            'license' => $license,
        ], fn ($v) => $v !== null), 200);
    }

    // 🌟 SF-1: جلدِ کتاب (عمومی). نشانی نسخه‌دار است (?v=)، پس کشِ طولانی بی‌خطر است.
    public function cover(Book $book)
    {
        if (! $book->hasCover()) {
            return response()->json(['message' => 'این کتاب جلد ندارد.'], 404);
        }

        return Storage::disk('local')->response($book->cover_image, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
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

        return response()->json(array_filter([
            'success' => true,
            'data' => $books,
            'session' => $session,
            'license' => $license,
        ], fn ($v) => $v !== null), 200);
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

            if (!$user) {
                return $this->sessionDenied($request);
            }
            if (!$user->purchasedBooks()->where('books.id', $book->id)->exists()) {
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

        if (!$user) {
            return $this->sessionDenied($request);
        }
        if (!$user->purchasedBooks()->where('books.id', $book->id)->exists()) {
            return response()->json(['message' => 'شما این کتاب را خریداری نکرده‌اید.'], 403);
        }

        return null;
    }

    /// 🌟 DEV-1: توکن هست ولی پذیرفته نشد (دستگاهِ آزادشده، توکنِ کپی‌شده، توکنِ قدیمی، …).
    private function sessionDenied(Request $request)
    {
        $reason = $request->attributes->get(DeviceService::DENIED_ATTRIBUTE) ?? 'invalid_token';

        return response()->json([
            'message' => DeviceService::deniedMessage($reason),
            'reason' => $reason,
        ], 401);
    }

    /// نامِ آرشیو شاملِ شماره‌ی نسخه‌هاست — یعنی با هر آپلودِ جدید (که نسخه‌ها
    /// را increment می‌کند) نامِ فایل عوض می‌شود و آرشیوِ تازه ساخته می‌شود؛
    /// نیازی به invalidate کردنِ دستیِ کش نیست.
    /// 🌟 public/static است تا صفحه‌ی آپلودِ فیلامنت هم بتواند فایلِ ZIPِ
    /// آپلودشده را دقیقاً با همین نام نگه دارد و این دو جا از هم جدا نیفتند.
    public static function zipRelativePath(Book $book, string $scope): string
    {
        $name = $scope === 'sample'
            ? "sample_v{$book->sample_version}.zip"
            : "main_j{$book->json_version}_a{$book->audio_version}_i{$book->images_version}.zip";

        return "books/{$book->folder_name}/archive/{$name}";
    }

    /// آرشیوهای قدیمیِ همین scope را پاک می‌کند (تا فضا هدر نرود).
    public static function pruneOldZips(Book $book, string $scope, string $keepRel): void
    {
        $prefix = $scope === 'sample' ? 'sample_' : 'main_';
        $dir = "books/{$book->folder_name}/archive";
        if (!Storage::exists($dir)) {
            return;
        }
        foreach (Storage::files($dir) as $old) {
            if (str_starts_with(basename($old), $prefix) && $old !== $keepRel) {
                Storage::delete($old);
            }
        }
    }

    /// آرشیو را برمی‌گرداند: اولویت با همان فایلِ ZIPی است که ادمین آپلود
    /// کرده و نگه داشته شده (پس هیچ ساختِ دوباره‌ای لازم نیست). فقط برای
    /// کتاب‌هایی که قبل از این تغییر آپلود شده‌اند و آرشیوِ ذخیره‌شده ندارند،
    /// یک‌بار از رویِ فایل‌های روی دیسک ساخته می‌شود.
    /// 🌟 public/static شد تا صفحه‌ی آپلودِ فیلامنت هم بتواند بعد از استخراجِ
    /// یک فایلِ RAR (یا آپلودِ حالتِ «بروزرسانی») همین‌جا آرشیوِ ZIPِ دانلود را
    /// بسازد — یعنی ساختِ آرشیو فقط یک پیاده‌سازی دارد و دو مسیر از هم جدا
    /// نمی‌افتند.
    public static function ensureZip(Book $book, string $scope): ?string
    {
        $zipRel = self::zipRelativePath($book, $scope);

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

        self::pruneOldZips($book, $scope, $zipRel);

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

            // 🌟 ساختار دقیقاً مثلِ خودِ فایلِ آپلودی حفظ می‌شود (audio/ و
            // images/ به‌صورتِ زیرپوشه). صاف‌کردنِ آن‌ها — که اپِ فلاتر لازم
            // دارد — حالا فقط سمتِ فلاتر و موقعِ استخراج انجام می‌شود، تا هر
            // دو مسیر (آرشیوِ آپلودیِ نگه‌داشته‌شده و این آرشیوِ فال‌بک) دقیقاً
            // یک شکل باشند و اپ فقط با یک ساختار سروکار داشته باشد.
            $zip->addFile($item->getPathname(), $relative);
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

        $zipRel = self::ensureZip($book, $scope);
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

        $zipRel = self::ensureZip($book, $scope);
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
