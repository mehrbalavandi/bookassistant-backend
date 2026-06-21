<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    // ۱. اضافه شدن فیلد نسخه نمونه به آرایه Fillable
    protected $fillable = [
        'title',
        'folder_name',
        'json_file',
        'audio_files',
        'images',
        'sample_file_path', 
    ];

    // ۲. تبدیل نوع (Casting) بسیار مهم برای Filament
    protected $casts = [
        'audio_files' => 'array',
        'images' => 'array',
    ];

    // ۳. رابطه با جدول کاربران (خریدهای دائمی) که قبلاً اضافه کردیم
    public function purchasers()
    {
        return $this->belongsToMany(User::class, 'book_user')->withTimestamps();
    }
}