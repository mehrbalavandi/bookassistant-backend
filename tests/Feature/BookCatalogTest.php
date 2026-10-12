<?php

namespace Tests\Feature;

use App\Filament\Resources\BookResource\Pages\CreateBook;
use App\Filament\Resources\BookResource\Pages\EditBook;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 🌟 دسته‌بندی و ترتیبِ نمایشِ کتاب‌ها: ترتیب از «شماره‌ی ردیف» می‌آید نه ترتیبِ آپلود،
 * شماره‌ی ردیف در هر دسته یکتاست، و سایت/API/پنل همه همین ترتیب را می‌بینند.
 */
class BookCatalogTest extends TestCase
{
    use RefreshDatabase;

    private BookCategory $mindset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mindset = BookCategory::create(['title' => 'Mindset for IELTS', 'sort_order' => 1]);
        // عمداً به ترتیبِ «اشتباه» ساخته می‌شوند: اول جلدِ ۳، بعد ۱.
        $this->book('Mindset 03', $this->mindset->id, 3);
        $this->book('Mindset 01', $this->mindset->id, 1);
        $this->book('Mindset 02', $this->mindset->id, 2);
        $this->book('Old book', null, 1);
    }

    private function book(string $title, ?int $categoryId, int $sort): Book
    {
        return Book::create([
            'title' => $title,
            'folder_name' => str($title)->slug(),
            'category_id' => $categoryId,
            'sort_order' => $sort,
        ]);
    }

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_api_orders_by_category_then_row_number_not_upload_order(): void
    {
        $titles = collect($this->getJson('/api/books')->assertOk()->json('data'))->pluck('title')->all();

        $this->assertSame(['Mindset 01', 'Mindset 02', 'Mindset 03', 'Old book'], $titles);
    }

    public function test_api_exposes_category_and_row_number(): void
    {
        $first = $this->getJson('/api/books')->json('data.0');

        $this->assertSame(1, $first['sort_order']);
        $this->assertSame('Mindset for IELTS', $first['category']['title']);
        $this->assertSame(1, $first['category']['sort_order']);
    }

    public function test_row_number_is_unique_within_a_category_in_the_database(): void
    {
        $this->expectException(QueryException::class);
        $this->book('Duplicate', $this->mindset->id, 2);
    }

    public function test_same_row_number_is_allowed_in_another_category(): void
    {
        $other = BookCategory::create(['title' => 'Cambridge', 'sort_order' => 2]);
        $this->book('Cambridge 18', $other->id, 1);

        // ردیفِ ۱: Mindset 01، Cambridge 18 و کتابِ بی‌دسته.
        $this->assertSame(3, Book::where('sort_order', 1)->count());
    }

    public function test_category_with_books_cannot_be_deleted(): void
    {
        $this->expectException(QueryException::class);
        $this->mindset->delete();
    }

    public function test_site_shows_categories_then_books_of_selected_category(): void
    {
        $this->get('/')->assertOk()->assertSeeInOrder(['Mindset for IELTS', 'سایر کتاب‌ها']);

        $this->get('/?category=' . $this->mindset->id)
            ->assertOk()
            ->assertSeeInOrder(['Mindset 01', 'Mindset 02', 'Mindset 03'])
            ->assertDontSee('Old book');
    }

    public function test_site_opens_the_only_category_directly(): void
    {
        Book::where('title', 'Old book')->delete();

        $this->get('/')->assertOk()->assertSeeInOrder(['Mindset 01', 'Mindset 02', 'Mindset 03']);
    }

    public function test_admin_form_rejects_duplicate_row_number_in_same_category(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateBook::class)
            ->fillForm(['title' => 'X', 'folder_name' => 'x', 'price' => 0, 'category_id' => $this->mindset->id, 'sort_order' => 2])
            ->call('create')
            ->assertHasFormErrors(['sort_order' => 'unique']);
    }

    public function test_admin_form_suggests_next_row_number_for_selected_category(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateBook::class)
            ->fillForm(['category_id' => $this->mindset->id])
            ->assertFormSet(['sort_order' => 4]);
    }

    public function test_admin_edit_keeps_its_own_row_number(): void
    {
        $this->actingAsAdmin();
        $book = Book::where('title', 'Mindset 02')->first();

        Livewire::test(EditBook::class, ['record' => $book->getRouteKey()])
            ->fillForm(['sort_order' => 2, 'category_id' => $this->mindset->id])
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
