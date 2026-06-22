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
        'json_version',       // <---
        'audio_files',
        'audio_version',      // <---
        'images',
        'images_version',     // <---
        'sample_file_path',
        'sample_version',     // <---
    ];

    protected $casts = [
        'audio_files' => 'array',
        'images' => 'array',
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