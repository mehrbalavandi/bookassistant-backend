<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyItem;
use App\Models\UserDevice;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 🌟 BK-1..BK-3: پشتیبانِ داده‌های کاربر روی سرور.
 *
 * همگام‌سازیِ «مرورِ من» (BK-1) دوطرفه و افزایشی است:
 *   POST /api/backup/study/sync {since_ms, items:[{id, updated, deleted, ...}]}
 *   - هر موردِ ورودی فقط اگر از نسخه‌ی سرور تازه‌تر است (updated بزرگ‌تر) نوشته می‌شود:
 *     «آخرین تغییر برنده است»، پس دو دستگاه هم‌دیگر را خراب نمی‌کنند.
 *   - حذف هم یک تغییر است (deleted=true، «سنگِ قبر»)، تا دستگاهِ دیگر هم حذفش کند.
 *   - پاسخ: همه‌ی موردهایی که از since_ms به بعد در سرور عوض شده‌اند (since_ms=null یعنی
 *     همه) + server_ms برای دفعه‌ی بعد. موردهای همین درخواست هم برمی‌گردند؛ اپ آن‌ها را
 *     با همان قاعده نادیده می‌گیرد.
 */
class BackupController extends Controller
{
    private const MAX_ITEMS = 500;

    private static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public function syncStudy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since_ms' => 'nullable|integer|min:0',
            'items' => 'present|array|max:'.self::MAX_ITEMS,
            'items.*.id' => 'required|string|max:191',
            'items.*.kind' => 'required|string|in:word,passage',
            'items.*.updated' => 'required|integer|min:0',
            'items.*.deleted' => 'sometimes|boolean',
            'items.*.text' => 'required_unless:items.*.deleted,true|nullable|string|max:5000',
            'items.*.note' => 'nullable|string|max:5000',
        ]);
        $user = $request->user();
        $since = $data['since_ms'] ?? null;

        // server_ms یکتا و صعودی برای هر ردیفِ نوشته‌شده در این درخواست.
        $serverMs = self::nowMs();

        // validate() فقط کلیدهای قاعده‌دار را برمی‌گرداند؛ payload باید کاملِ مورد باشد.
        $rawItems = array_values(array_filter((array) $request->input('items', []), 'is_array'));

        DB::transaction(function () use ($rawItems, $user, $serverMs) {
            $incoming = collect($rawItems);
            $existing = StudyItem::where('user_id', $user->id)
                ->whereIn('item_id', $incoming->pluck('id'))
                ->get()->keyBy('item_id');

            foreach ($incoming as $raw) {
                $row = $existing->get($raw['id']);
                if ($row && $row->client_updated_ms >= $raw['updated']) {
                    continue; // نسخه‌ی سرور تازه‌تر یا هم‌زمان است
                }
                $deleted = (bool) ($raw['deleted'] ?? false);
                // payload = همان JSONِ اپ، بدونِ فیلدهای همگام‌سازی.
                $payload = collect($raw)->except(['deleted'])->all();
                StudyItem::updateOrCreate(
                    ['user_id' => $user->id, 'item_id' => $raw['id']],
                    [
                        'kind' => $raw['kind'],
                        'book_id' => isset($raw['bookId']) ? mb_substr((string) $raw['bookId'], 0, 64) : null,
                        'importance' => (int) ($raw['imp'] ?? 2),
                        'status' => isset($raw['status']) ? mb_substr((string) $raw['status'], 0, 16) : null,
                        'deleted' => $deleted,
                        'payload' => $deleted ? ['id' => $raw['id'], 'kind' => $raw['kind']] : $payload,
                        'client_updated_ms' => (int) $raw['updated'],
                        'server_ms' => $serverMs,
                    ],
                );
            }
        });

        $changes = StudyItem::where('user_id', $user->id)
            ->when($since !== null, fn ($q) => $q->where('server_ms', '>=', $since))
            ->orderBy('server_ms')
            ->get()
            ->map->toClientArray()
            ->values();

        return response()->json([
            'success' => true,
            // ۱۰ ثانیه هم‌پوشانی: اگر دو دستگاه هم‌زمان همگام کنند و تراکنشِ یکی دیرتر
            // ثبت شود، دفعه‌ی بعد جا نمی‌ماند. تکرارِ چند مورد بی‌ضرر است (همان قاعده).
            'server_ms' => max(0, $serverMs - 10_000),
            'items' => $changes,
        ]);
    }

    /** GET /api/backup/settings */
    public function getSettings(Request $request): JsonResponse
    {
        $s = UserSetting::where('user_id', $request->user()->id)->first();

        return response()->json([
            'success' => true,
            'data' => $s?->data,
            'updated' => $s?->client_updated_ms,
        ]);
    }

    /** PUT /api/backup/settings {data: {...}, updated: ms} — آخرین تغییر برنده است. */
    public function putSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'data' => 'required|array',
            'updated' => 'required|integer|min:0',
        ]);
        if (strlen(json_encode($data['data'])) > 256 * 1024) {
            return response()->json(['success' => false, 'message' => 'حجمِ تنظیمات زیاد است.'], 413);
        }
        $userId = $request->user()->id;
        $s = UserSetting::where('user_id', $userId)->first();
        if (! $s || $s->client_updated_ms < $data['updated']) {
            $s = UserSetting::updateOrCreate(
                ['user_id' => $userId],
                ['data' => $data['data'], 'client_updated_ms' => $data['updated']],
            );
        }

        return response()->json(['success' => true, 'data' => $s->data, 'updated' => $s->client_updated_ms]);
    }

    /**
     * POST /api/devices {device_id, platform, app_version, build_number, os_version, model}
     * (BK-3) — هر بار که اپ با حسابِ واردشده باز می‌شود.
     */
    public function touchDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => 'required|string|max:64',
            'platform' => 'nullable|string|max:16',
            'app_version' => 'nullable|string|max:32',
            'build_number' => 'nullable|integer|min:0',
            'os_version' => 'nullable|string|max:64',
            'model' => 'nullable|string|max:100',
        ]);
        $user = $request->user();
        UserDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $data['device_id']],
            array_merge($data, ['last_ip' => $request->ip(), 'last_seen_at' => now()]),
        );
        $user->forceFill(['last_seen_at' => now()])->save();

        return response()->json(['success' => true]);
    }
}
