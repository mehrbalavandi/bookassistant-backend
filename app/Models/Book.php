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
        'json_version',
        'audio_files',
        'audio_version',
        'images',
        'images_version',
        'sample_file_path',
        'sample_version',
        'sample_audio_files', // <--- جدید
        'sample_images',      // <--- جدید
    ];

    protected $casts = [
        'audio_files' => 'array',
        'images' => 'array',
        'sample_audio_files' => 'array', // <--- جدید
        'sample_images' => 'array',      // <--- جدید
        'json_version' => 'integer',
        'audio_version' => 'integer',
        'images_version' => 'integer',
        'sample_version' => 'integer',
    ];

    public function purchasers()
    {
        return $this->belongsToMany(User::class, 'book_user')->withTimestamps();
    }
}
