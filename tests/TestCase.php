<?php

namespace Tests;

use App\Models\Book;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * 🌟 SF-4: کتابی که محتوای اصلی (index.json) روی دیسک دارد — فقط چنین کتابی خریدنی است.
     * دیسکِ local باید پیش از این fake شده باشد (و بعدش دوباره fake نشود).
     */
    protected function bookWithContent(array $attributes): Book
    {
        $folder = $attributes['folder_name'] ?? 'book-'.uniqid();
        $path = "books/{$folder}/index.json";
        Storage::disk('local')->put($path, '{"Pages":[]}');

        return Book::create($attributes + ['folder_name' => $folder, 'json_file' => $path]);
    }
}
