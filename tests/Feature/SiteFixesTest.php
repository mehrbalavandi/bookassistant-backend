<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 🌟 PAY-8، SF-4، FB-1، SITE-9 */
class SiteFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['shop.gateway_enabled' => true, 'shop.card.enabled' => true, 'shop.card.number' => '6037991122334455']);
    }

    public function test_paid_order_removes_abandoned_attempts_for_the_same_book(): void
    {
        $book = $this->bookWithContent(['title' => 'Mindset 01', 'price' => 100000]);
        $other = $this->bookWithContent(['title' => 'Mindset 02', 'price' => 100000]);
        $user = User::factory()->create();
        $mk = fn ($b, $status, $method = 'card', $receipt = null) => Order::create([
            'user_id' => $user->id, 'book_id' => $b->id, 'amount' => 100000, 'method' => $method,
            'status' => $status, 'receipt_path' => $receipt,
        ]);
        $pendingCard = $mk($book, Order::PENDING);
        $failed = $mk($book, Order::FAILED, 'gateway');
        $reviewing = $mk($book, Order::AWAITING_REVIEW, 'card', 'receipts/x.jpg');
        $otherBook = $mk($other, Order::PENDING);
        $paid = $mk($book, Order::PENDING, 'gateway');

        app(OrderService::class)->grant($paid, null);

        $this->assertNull(Order::find($pendingCard->id), 'unfinished card order removed');
        $this->assertNull(Order::find($failed->id));
        $this->assertNotNull(Order::find($reviewing->id), 'sent receipt is kept');
        $this->assertNotNull(Order::find($otherBook->id), 'other book untouched');
        $this->assertSame(Order::PAID, $paid->fresh()->status);
    }

    public function test_my_orders_hide_abandoned_orders_of_owned_books(): void
    {
        $book = $this->bookWithContent(['title' => 'Mindset 01', 'price' => 100000]);
        $user = User::factory()->create();
        $stale = Order::create(['user_id' => $user->id, 'book_id' => $book->id, 'amount' => 1, 'method' => 'card', 'status' => Order::PENDING]);
        $user->purchasedBooks()->attach($book->id); // فعال‌سازیِ دستیِ ادمین

        $ids = collect($this->actingAs($user)->getJson('/api/orders')->assertOk()->json('orders'))->pluck('id');
        $this->assertNotContains($stale->id, $ids);
    }

    public function test_book_without_content_is_not_for_sale(): void
    {
        $empty = Book::create(['title' => 'Mindset 03', 'folder_name' => 'm3', 'price' => 100000]);
        $user = User::factory()->create();

        $row = collect($this->getJson('/api/books')->assertOk()->json('data'))->firstWhere('id', $empty->id);
        $this->assertFalse($row['has_content']);
        $this->actingAs($user)->postJson('/api/orders', ['book_id' => $empty->id, 'method' => 'gateway'])->assertStatus(422);
        $this->actingAs($user)->get(route('checkout', $empty))->assertRedirect(route('home'));
        $this->actingAs($user)->get('/')->assertSee('در حالِ آماده‌سازی');
    }

    public function test_feedback_from_site_and_app_with_images_and_admin_reply(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)->post(route('feedback.store'), [
            'kind' => 'bug', 'subject' => 'صوت قطع می‌شود', 'message' => 'در صفحه‌ی ۲۴',
            'images' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect(route('feedback.index'));
        $f = Feedback::first();
        $this->assertSame(2, $f->attachments()->count());
        Storage::disk('local')->assertExists($f->attachments->first()->path);

        // تصویر: صاحب و ادمین بله، دیگران نه
        $a = $f->attachments->first();
        $this->actingAs($user)->get(route('feedback.attachment', $a))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('feedback.attachment', $a))->assertForbidden();

        // اپ: ثبت و فهرست
        $this->actingAs($user)->post('/api/feedback', [
            'kind' => 'suggestion', 'subject' => 'حالتِ شب', 'message' => 'لطفاً', 'app_version' => '1.4.0',
            'images' => [UploadedFile::fake()->image('c.png')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('item.attachments', 1);
        $this->actingAs($user)->postJson('/api/feedback', ['kind' => 'nope', 'subject' => 'x', 'message' => 'y'])->assertStatus(422);

        // پاسخِ ادمین ← کاربر می‌بیند (ندیده = ۱)
        app(\App\Services\FeedbackService::class)->reply($f, 'درست شد', 'resolved', User::where('is_admin', true)->first());
        $this->actingAs($user)->getJson('/api/feedback')->assertOk()->assertJsonPath('unseen', 1)
            ->assertJsonPath('items.1.admin_reply', 'درست شد');
        $this->actingAs($user)->postJson('/api/feedback/seen')->assertOk();
        $this->actingAs($user)->getJson('/api/feedback')->assertJsonPath('unseen', 0);
        $this->actingAs($user)->get(route('feedback.index'))->assertOk()->assertSee('درست شد');

        // حذفِ گزارش فایل‌هایش را هم پاک می‌کند
        $path = $a->path;
        $f->delete();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_expired_form_goes_back_with_a_message_instead_of_419_page(): void
    {
        Route::post('/_t419', fn () => abort(419))->middleware('web');
        $this->from('/contact')->post('/_t419', ['name' => 'x'])->assertRedirect('/contact');
        // (نشستِ json پیامِ خطا را هنگامِ ذخیره آرایه می‌کند؛ درخواستِ بعدی دوباره ViewErrorBag است.)
        $errors = session('errors');
        $messages = is_array($errors) ? $errors['default']['messages'] : $errors->getBag('default')->toArray();
        $this->assertArrayHasKey('session', $messages);
        $this->assertSame('x', session('_old_input.name'));
        // صفحه‌ی errors/419 (وقتی برگشتی نیست) هم فارسی است
        $this->assertStringContainsString('مهلتِ این صفحه تمام شد', view('errors.419')->render());
    }
}
