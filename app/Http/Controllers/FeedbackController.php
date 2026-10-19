<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\FeedbackAttachment;
use App\Services\FeedbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * 🌟 FB-1: «انتقادات و پیشنهادات» در سایت — فرم (با تصویر) و فهرستِ گزارش‌های خودِ کاربر با
 * پاسخِ ادمین. تصویرها فقط برای صاحبِ گزارش و ادمین سرو می‌شوند.
 */
class FeedbackController extends Controller
{
    public function __construct(private readonly FeedbackService $service) {}

    public function index()
    {
        $items = Auth::user()->feedback()->with('attachments')->latest('id')->limit(50)->get();
        // دیدنِ فهرست = دیدنِ پاسخ‌ها
        Auth::user()->feedback()->whereNotNull('replied_at')
            ->where(fn ($q) => $q->whereNull('seen_by_user_at')->orWhereColumn('seen_by_user_at', '<', 'replied_at'))
            ->update(['seen_by_user_at' => now()]);

        return view('feedback.index', ['items' => $items, 'kinds' => Feedback::KINDS]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(FeedbackService::rules(), FeedbackService::messages());
        $this->service->submit(Auth::user(), $data, $request->file('images', []), 'web',
            null, mb_substr((string) $request->userAgent(), 0, 120));

        return redirect()->route('feedback.index')
            ->with('success', 'ممنون! پیامتان ثبت شد و پاسخ همین‌جا (و در اپ) نمایش داده می‌شود.');
    }

    /** تصویرِ پیوست: صاحبِ گزارش یا ادمین. */
    public function attachment(FeedbackAttachment $attachment)
    {
        $user = Auth::user();
        abort_unless($user && ($user->is_admin || $attachment->feedback?->user_id === $user->id), 403);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path);
    }
}
