<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** پشتیبانِ تنظیماتِ اپ (BK-2): یک JSON برای هر کاربر. */
class UserSetting extends Model
{
    protected $fillable = ['user_id', 'data', 'client_updated_ms'];

    protected $casts = [
        'data' => 'array',
        'client_updated_ms' => 'integer',
    ];
}
