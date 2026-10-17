<?php

namespace Tests\Feature;

use App\Filament\Resources\BookResource\Pages\EditBook;
use App\Http\Controllers\Api\BookController;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\User;
use App\Support\BookSampleSync;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** 🌟 SMP-1: نسخه‌ی نمونه‌ی همراهِ آرشیوِ اصلی + قیمت با جداکننده‌ی هزارگان در فرمِ کتاب. */
class BookSampleSyncTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->book = Book::create(['title' => 'Mindset 01', 'folder_name' => 'mindset-01', 'price' => 200000]);
    }

    private function putFile(string $rel, string $content): void
    {
        Storage::disk('local')->put("books/mindset-01/{$rel}", $content);
    }

    private function writeExtractorSample(): void
    {
        $this->putFile('index.json', json_encode(['Pages' => [['N' => 1, 'File' => 'pages/page_0001.json']]]));
        $this->putFile('pages/page_0001.json', '{"PageNumber":1}');
        $this->putFile('sample/index.json', json_encode([
            'Pages' => [['N' => 1, 'File' => 'pages/page_0001.json', 'Version' => 'a']],
            'AudioScripts' => [['AudioTrackName' => 'L1_02.mp3', 'File' => 'audio_scripts/L1_02.json']],
            'AudioLinksIndex' => [['PageNumber' => 1, 'ParaIndex' => 0, 'FileName' => 'L1_02.mp3']],
            'Outline' => [['Title' => 'UNIT/01', 'Level' => 1, 'PageNumber' => 1, 'ParaIndex' => 0]],
            'Sample' => ['Title' => 'UNIT/01: RELATIONSHIPS', 'FirstPage' => 1, 'LastPage' => 16, 'BookFirstPage' => 1, 'BookLastPage' => 159],
        ]));
        $this->putFile('sample/pages/page_0001.json', json_encode(['PageNumber' => 1, 'Paragraphs' => [
            ['Spans' => [['Type' => 'table', 'TableRows' => [['Cells' => [['Paragraphs' => [
                ['Spans' => [['Type' => 'image', 'Url' => 'p1_img1.png']]],
            ]]]]]]]],
        ]]));
        $this->putFile('sample/audio_scripts/L1_02.json', '{}');
        $this->putFile('sample/images/p1_img1.png', 'img');
        $this->putFile('sample/audio/L1_02.mp3', 'mp3');
        // فایل‌های کهنه‌ی نمونه‌ی قبلی (مثلاً Unitِ دیگری که قبلاً نمونه بود)
        $this->putFile('sample/pages/page_0099.json', '{}');
        $this->putFile('sample/audio_scripts/L1_40.json', '{}');
        $this->putFile('sample/images/old.png', 'img');
        $this->putFile('sample/audio/L1_40.mp3', 'mp3');
    }

    public function test_sample_folder_in_main_upload_is_registered_pruned_and_zipped(): void
    {
        $before = BookSampleSync::fingerprint($this->book);
        $this->assertNull($before);
        $this->writeExtractorSample();

        $summary = BookSampleSync::syncIfChanged($this->book, $before);

        $this->assertStringContainsString('UNIT/01: RELATIONSHIPS', $summary);
        $this->assertStringContainsString('4 فایلِ کهنه', $summary);
        $this->book->refresh();
        $this->assertGreaterThanOrEqual(1, $this->book->sample_version);
        $this->assertSame('books/mindset-01/sample/index.json', $this->book->sample_file_path);
        $this->assertSame(['books/mindset-01/sample/audio/L1_02.mp3'], $this->book->sample_audio_files);
        $this->assertSame(['books/mindset-01/sample/images/p1_img1.png'], $this->book->sample_images);
        $this->assertTrue($this->book->hasSample());

        $disk = Storage::disk('local');
        $disk->assertExists('books/mindset-01/sample/pages/page_0001.json');
        $disk->assertMissing('books/mindset-01/sample/pages/page_0099.json');
        $disk->assertMissing('books/mindset-01/sample/audio_scripts/L1_40.json');
        $disk->assertMissing('books/mindset-01/sample/images/old.png');
        $disk->assertMissing('books/mindset-01/sample/audio/L1_40.mp3');

        // ZIPِ نمونه ساخته شده و فقط محتوای نمونه را دارد
        $zipRel = BookController::zipRelativePath($this->book, 'sample');
        $disk->assertExists($zipRel);
        $zip = new \ZipArchive();
        $zip->open($disk->path($zipRel));
        $this->assertNotFalse($zip->locateName('index.json'));
        $this->assertNotFalse($zip->locateName('audio/L1_02.mp3'));
        $zip->close();

        // آرشیوِ اصلی پوشه‌ی sample را ندارد
        $mainRel = BookController::ensureZip($this->book, 'main');
        $zip->open($disk->path($mainRel));
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $this->assertStringStartsNotWith('sample/', $zip->getNameIndex($i));
        }
        $zip->close();
    }

    public function test_upload_without_new_sample_leaves_the_existing_sample_alone(): void
    {
        $this->writeExtractorSample();
        BookSampleSync::register($this->book);
        $version = $this->book->fresh()->sample_version;

        // آپلودِ بعدیِ اصلی که sample/index.json را عوض نکرده
        $this->assertNull(BookSampleSync::syncIfChanged($this->book, BookSampleSync::fingerprint($this->book)));
        $this->assertSame($version, $this->book->fresh()->sample_version);
    }

    public function test_price_field_accepts_thousands_separators_and_shows_final_price(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditBook::class, ['record' => $this->book->getRouteKey()])
            ->fillForm(['price' => '1,250,000', 'discount' => 10,
                'category_id' => BookCategory::create(['title' => 'Mindset', 'sort_order' => 1])->id, 'sort_order' => 1])
            ->assertSee('قیمتِ پس از تخفیف: 1,125,000 تومان')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1250000, (int) $this->book->fresh()->price);
    }
}
