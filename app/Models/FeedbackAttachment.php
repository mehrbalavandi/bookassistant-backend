<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 🌟 FB-1: تصویرِ پیوستِ یک گزارش (دیسکِ خصوصی؛ فقط ادمین و صاحبِ گزارش می‌بینند). */
class FeedbackAttachment extends Model
{
    protected $fillable = ['feedback_id', 'path', 'original_name', 'size'];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }
}
