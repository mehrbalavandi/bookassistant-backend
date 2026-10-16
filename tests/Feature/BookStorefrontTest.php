<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 🌟 SF-1: آنچه اپ در ویترین و صفحه‌ی جزئیاتِ کتاب نشان می‌دهد — نمونه‌ی واقعی، قیمت،
 * جلد و مشخصات.
 */
class BookStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function book(array $extra = []): Book
    {
        return Book::create(array_merge([
            'title' => 'Mindset 01',
            'folder_name' => 'mindset-01',
            'price' => 200000,
            'discount' => 25,
        ], $extra));
    }

    private function first(): array
    {
        return $this->getJson('/api/books')->assertOk()->json('data.0');
    }

    public function test_has_sample_only_when_the_sample_really_exists(): void
    {
        $this->book(['sample_file_path' => 'books/mindset-01/sample/index.json']);
        $this->assertFalse($this->first()['has_sample'], 'path set but no file on disk');

        Storage::disk('local')->put('books/mindset-01/sample/index.json', '{}');
        $this->assertTrue($this->first()['has_sample']);
    }

    public function test_book_without_sample_path_has_no_sample(): void
    {
        $this->book();
        $this->assertFalse($this->first()['has_sample']);
    }

    public function test_price_details_and_counts_are_sent(): void
    {
        $this->book([
            'description' => "خطِ اول\nخطِ دوم",
            'author' => 'Greg Archer',
            'publisher' => 'Cambridge',
            'level' => 'Band 4–5',
            'audio_files' => ['books/mindset-01/audio/a.mp3', 'books/mindset-01/audio/b.mp3'],
        ]);
        Storage::disk('local')->put('books/mindset-01/pages/page_0001.json', '{}');
        Storage::disk('local')->put('books/mindset-01/pages/page_0002.json', '{}');

        $b = $this->first();
        $this->assertSame(200000, (int) $b['price']);
        $this->assertSame(25, (int) $b['discount']);
        $this->assertSame(150000, (int) $b['final_price']);
        $this->assertSame("خطِ اول\nخطِ دوم", $b['description']);
        $this->assertSame('Greg Archer', $b['author']);
        $this->assertSame('Cambridge', $b['publisher']);
        $this->assertSame('Band 4–5', $b['level']);
        $this->assertSame(2, $b['page_count']);
        // تعدادِ فایل‌های صوتی دیده می‌شود ولی خودِ مسیرها برای خریدار نشده نه.
        $this->assertSame(2, $b['audio_count']);
        $this->assertSame([], $b['audio_files']);
        $this->assertNull($b['cover_url']);
        $this->assertArrayNotHasKey('cover_image', $b);
    }

    public function test_cover_is_served_with_a_versioned_url(): void
    {
        $path = UploadedFile::fake()->image('cover.jpg', 300, 400)->store('covers', 'local');
        $book = $this->book(['cover_image' => $path]);

        $b = $this->first();
        $this->assertNotNull($b['cover_version']);
        $this->assertSame("/api/books/{$book->id}/cover?v={$b['cover_version']}", $b['cover_url']);

        $this->get($b['cover_url'])->assertOk()->assertHeader('Cache-Control');
    }

    public function test_cover_route_404_without_cover(): void
    {
        $book = $this->book();
        $this->getJson("/api/books/{$book->id}/cover")->assertNotFound();
    }

    public function test_replacing_or_deleting_the_book_removes_old_cover_files(): void
    {
        $old = UploadedFile::fake()->image('a.jpg')->store('covers', 'local');
        $new = UploadedFile::fake()->image('b.jpg')->store('covers', 'local');
        $book = $this->book(['cover_image' => $old]);

        $book->update(['cover_image' => $new]);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);

        $book->delete();
        Storage::disk('local')->assertMissing($new);
    }
}
