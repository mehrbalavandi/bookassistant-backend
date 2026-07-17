<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * مدیریتِ محتوای هر کتاب. محتوای «اصلی» (کامل/خریداری‌شده) و «نمونه» (دمو رایگان)
 * به‌صورت فیزیکی در دو مسیرِ جدا نگهداری می‌شوند تا هیچ‌وقت با هم اشتباه نشوند:
 *
 *   books/{folder}/            ← اصلی:  index.json, pages/, audio/, images/
 *   books/{folder}/sample/     ← نمونه: index.json, pages/, audio/, images/
 */
class BookController extends Controller
{
    private const KINDS = ['pages', 'audio', 'images'];

    public function index()
    {
        $books = Book::orderBy('title')->get()->map(function ($b) {
            $b->main_stats   = $this->stats($b, 'main');
            $b->sample_stats = $this->stats($b, 'sample');
            return $b;
        });

        return view('admin.books.index', compact('books'));
    }

    public function manage(Book $book)
    {
        return view('admin.books.manage', [
            'book'   => $book,
            'main'   => $this->stats($book, 'main'),
            'sample' => $this->stats($book, 'sample'),
        ]);
    }

    /** آپلودِ گروهی: scope=main|sample ، kind=pages|audio|images (+ فایلِ index جدا برای pages) */
    public function upload(Request $request, Book $book)
    {
        $request->validate([
            'scope'   => 'required|in:main,sample',
            'kind'    => 'required|in:pages,audio,images',
            'files'   => 'required|array',
            'files.*' => 'required|file',
        ]);

        $scope = $request->input('scope');
        $kind  = $request->input('kind');
        $dir   = $this->dir($book, $scope, $kind);

        foreach ($request->file('files') as $file) {
            $file->storeAs($dir, $file->getClientOriginalName());
        }

        // برای pages، فایلِ index.json در ریشهٔ scope ذخیره و مسیرش در DB ثبت می‌شود
        if ($kind === 'pages' && $request->hasFile('index')) {
            $request->file('index')->storeAs($this->root($book, $scope), 'index.json');
            $book->update([
                $scope === 'sample' ? 'sample_file_path' : 'json_file'
                => $this->root($book, $scope) . '/index.json',
            ]);
        }

        $this->bumpVersion($book, $scope, $kind);

        return back()->with('status', "آپلودِ «{$kind}» ({$scope}) انجام شد.");
    }

    /** حذفِ گروهیِ همهٔ فایل‌های یک نوع در یک scope */
    public function destroyGroup(Book $book, string $scope, string $kind)
    {
        abort_unless(in_array($scope, ['main', 'sample'], true), 404);
        abort_unless(in_array($kind, self::KINDS, true), 404);

        Storage::deleteDirectory($this->dir($book, $scope, $kind));

        if ($kind === 'pages') {
            Storage::delete($this->root($book, $scope) . '/index.json');
            $book->update([
                $scope === 'sample' ? 'sample_file_path' : 'json_file' => null,
            ]);
        }

        $this->bumpVersion($book, $scope, $kind);

        return back()->with('status', "همهٔ فایل‌های «{$kind}» ({$scope}) حذف شد.");
    }

    // ───────────────────────── helpers ─────────────────────────

    private function root(Book $b, string $scope): string
    {
        return $scope === 'sample'
            ? "books/{$b->folder_name}/sample"
            : "books/{$b->folder_name}";
    }

    private function dir(Book $b, string $scope, string $kind): string
    {
        return $this->root($b, $scope) . '/' . $kind;
    }

    private function stats(Book $b, string $scope): array
    {
        $root = $this->root($b, $scope);
        return [
            'pages'    => count(Storage::files("$root/pages")),
            'audio'    => count(Storage::files("$root/audio")),
            'images'   => count(Storage::files("$root/images")),
            'hasIndex' => Storage::exists("$root/index.json"),
        ];
    }

    private function bumpVersion(Book $b, string $scope, string $kind): void
    {
        if ($scope === 'sample') {
            $b->increment('sample_version');
            return;
        }
        $col = ['pages' => 'json_version', 'audio' => 'audio_version', 'images' => 'images_version'][$kind];
        $b->increment($col);
    }
}
