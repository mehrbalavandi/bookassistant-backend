<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 🌟 SITE-1: یک مقدارِ عوض‌شده در «تنظیماتِ سایت» (App\Support\SiteSettings). */
class SiteSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'encrypted', 'updated_by'];

    protected function casts(): array
    {
        return ['encrypted' => 'boolean'];
    }
}
