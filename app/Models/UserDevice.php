<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** دستگاه‌های هر کاربر (BK-3): سکو، نسخه‌ی اپ، آخرین بازدید. */
class UserDevice extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'platform', 'app_version', 'build_number',
        'os_version', 'model', 'last_ip', 'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'build_number' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
