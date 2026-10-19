<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 🌟 FB-1 (اپ):
 * - GET  /api/feedback        گزارش‌های من (با پاسخِ ادمین)؛ unseen = تعدادِ پاسخ‌های ندیده
 * - POST /api/feedback        multipart: kind, subject, message, book_title?, images[]
 *                             (+ app_version, platform)
 * - POST /api/feedback/seen   پاسخ‌ها دیده شدند
 */
class FeedbackController extends Controller
{
    public function __construct(private readonly FeedbackService $service) {}

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->feedback()->with('attachments')->latest('id')->limit(50)->get();

        return response()->json([
            'success' => true,
            'kinds' => Feedback::KINDS,
            'unseen' => $items->filter->hasUnseenReply()->count(),
            'items' => $items->map->toAppArray()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), FeedbackService::rules() + [
            'app_version' => 'nullable|string|max:32',
            'platform' => 'nullable|string|max:120',
        ], FeedbackService::messages());
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();
        $f = $this->service->submit($request->user(), $data, $request->file('images', []), 'app',
            $data['app_version'] ?? null, $data['platform'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'ممنون! پیامتان ثبت شد. پاسخ را در همین بخش می‌بینید.',
            'item' => $f->load('attachments')->toAppArray(),
        ], 201);
    }

    public function seen(Request $request): JsonResponse
    {
        $request->user()->feedback()->whereNotNull('replied_at')->update(['seen_by_user_at' => now()]);

        return response()->json(['success' => true]);
    }
}
