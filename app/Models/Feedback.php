<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * 🌟 FB-1: انتقاد، پیشنهاد یا گزارشِ ایراد از یک کاربر (سایت یا اپ)، با تصویرهای پیوست و
 * پاسخِ ادمین. فایل‌ها در دیسکِ خصوصی (local) زیرِ feedback/{id}/ اند.
 */
class Feedback extends Model
{
    protected $table = 'feedback';

    public const KINDS = [
        'bug' => 'گزارشِ ایراد',
        'suggestion' => 'پیشنهاد',
        'criticism' => 'انتقاد',
        'other' => 'سایر',
    ];

    public const STATUSES = [
        'new' => 'تازه',
        'in_progress' => 'در حالِ بررسی',
        'resolved' => 'برطرف شد',
        'closed' => 'بسته شد',
    ];

    public const MAX_IMAGES = 5;

    public const MAX_IMAGE_KB = 5120;

    protected $fillable = [
        'user_id', 'kind', 'subject', 'message', 'source', 'app_version', 'platform', 'book_title',
        'status', 'admin_reply', 'replied_by', 'replied_at', 'seen_by_user_at',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
        'seen_by_user_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $f) {
            Storage::disk('local')->deleteDirectory("feedback/{$f->id}");
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FeedbackAttachment::class);
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** پاسخِ ادمین هست و کاربر بعد از آن هنوز ندیده. */
    public function hasUnseenReply(): bool
    {
        return $this->replied_at !== null
            && ($this->seen_by_user_at === null || $this->seen_by_user_at->lt($this->replied_at));
    }

    /** برای «گزارش‌های من» در اپ. */
    public function toAppArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'kind_label' => $this->kind_label,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'admin_reply' => $this->admin_reply,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'unseen_reply' => $this->hasUnseenReply(),
            'attachments' => $this->attachments->count(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
