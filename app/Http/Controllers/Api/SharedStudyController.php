<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SharedStudyItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 🌟 ST-7: خوراکِ «مرورِ آماده» برای اپ (عمومی، حتی مهمان).
 *
 * GET /api/study/shared?since=<changed_ms>
 * - بی‌since: همه‌ی موردهای منتشرشده (بارِ اول).
 * - با since: هر چه بعد از آن عوض شده، شاملِ برداشته‌ها (deleted=true) تا اپ پاکشان کند.
 * - حداکثر $limit مورد در هر پاسخ؛ اگر more=true، اپ با since=cursor دوباره می‌خواهد.
 */
class SharedStudyController extends Controller
{
    public static int $limit = 500;

    public function index(Request $request): JsonResponse
    {
        $since = $request->integer('since') ?: null;

        $rows = SharedStudyItem::withTrashed()
            ->when($since === null, fn ($q) => $q->whereNull('deleted_at')->where('published', true))
            ->when($since !== null, fn ($q) => $q->where('changed_ms', '>', $since))
            ->orderBy('changed_ms')
            ->limit(self::$limit + 1)
            ->get();

        $more = $rows->count() > self::$limit;
        $rows = $rows->take(self::$limit);

        return response()->json([
            'success' => true,
            'items' => $rows->map->toFeedArray()->values(),
            // بارِ اول هم cursor = بیشترین changed_msِ کلِ جدول، تا برداشته‌های قبلی دوباره نیایند.
            'cursor' => $more
                ? (int) $rows->last()->changed_ms
                : max((int) ($since ?? 0), (int) SharedStudyItem::withTrashed()->max('changed_ms')),
            'more' => $more,
        ]);
    }
}
