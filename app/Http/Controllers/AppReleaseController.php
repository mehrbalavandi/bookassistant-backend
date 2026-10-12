<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 🌟 توزیعِ اپ از خودِ سایت.
 *
 * - GET /app                       صفحه‌ی دریافتِ اپ (برای کاربر؛ موبایل‌پسند)
 * - GET /app/download/{platform}   فایلِ آخرین نسخه‌ی آن سکو (با پشتیبانیِ Range، یعنی
 *                                  دانلودِ قطع‌شده ادامه پیدا می‌کند)
 * - GET /api/app/latest?platform=  برای اپ: آخرین نسخه، و این‌که به‌روزرسانی اجباری است یا نه
 */
class AppReleaseController extends Controller
{
    public function page()
    {
        $releases = collect(array_keys(AppRelease::PLATFORMS))
            ->mapWithKeys(fn (string $p) => [$p => AppRelease::latestFor($p)])
            ->filter();

        return view('app', ['releases' => $releases]);
    }

    public function download(string $platform): BinaryFileResponse
    {
        abort_unless(array_key_exists($platform, AppRelease::PLATFORMS), 404);
        $release = AppRelease::latestFor($platform);
        abort_unless($release && Storage::disk('local')->exists($release->file_path), 404);

        $headers = $platform === 'android'
            ? ['Content-Type' => 'application/vnd.android.package-archive']
            : [];

        return response()->download(
            Storage::disk('local')->path($release->file_path),
            $release->downloadName(),
            $headers,
        );
    }

    public function latest(Request $request): JsonResponse
    {
        $platform = (string) $request->query('platform', 'android');
        if (! array_key_exists($platform, AppRelease::PLATFORMS)) {
            return response()->json(['success' => false, 'message' => 'سکوی ناشناخته.'], 422);
        }

        $release = AppRelease::latestFor($platform);
        if (! $release) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'platform' => $release->platform,
                'version_name' => $release->version_name,
                'build_number' => $release->build_number,
                'changelog' => $release->changelog,
                'is_mandatory' => $release->is_mandatory,
                'min_supported_build' => AppRelease::minSupportedBuild($platform),
                'file_size' => $release->file_size,
                'published_at' => $release->updated_at?->toIso8601String(),
                // صفحه‌ی دریافت (راهنمای نصب دارد) — اپ کاربر را به همین می‌فرستد.
                'page_url' => route('app.page'),
                'download_url' => route('app.download', $platform),
            ],
        ]);
    }
}
