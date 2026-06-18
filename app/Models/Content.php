<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    use HasFactory;

    // نام جدولی که در دیتابیس ساختیم
    protected $table = 'contents';

    // فیلدهایی که اجازه دارند از طریق فرم فیلامنت پر و ذخیره شوند
    protected $fillable = [
        'title',
        'version',
        'audio_filename',
        'text_content',
    ];
}
