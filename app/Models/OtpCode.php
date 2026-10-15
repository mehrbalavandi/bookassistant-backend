<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** کدِ یک‌بارمصرفِ ورود (AUTH-2). خودِ کد ذخیره نمی‌شود، فقط هشِ آن. */
class OtpCode extends Model
{
    protected $fillable = ['mobile', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'ip'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
