<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'folder_name',
        'json_file',
        'audio_files',
        'images',
    ];

    // تبدیل فرمت ذخیره‌سازی فایل‌های چندگانه به آرایه
    protected $casts = [
        'audio_files' => 'array',
        'images' => 'array',
    ];

    // کاربرانی که این کتاب را خریده‌اند
    public function purchasers()
    {
        return $this->belongsToMany(User::class, 'book_user')->withTimestamps();
    }    
}