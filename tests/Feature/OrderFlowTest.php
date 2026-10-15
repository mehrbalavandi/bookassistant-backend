<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 🌟 PAY-1..4: سفارش، لینکِ امضاشده‌ی درگاه، کارت‌به‌کارت و تأییدِ ادمین. */
class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        $this->book = Book::create(['title' => 'Mindset 01', 'folder_name' => 'mindset-01', 'price' => 200000, 'discount' => 10]);
        config([
            'shop.card.enabled' => true,
            'shop.card.number' => '6037-9911-2233-4455',
            'shop.card.holder' => 'مهرشاد بلوندی',
        ]);
    }

    public function test_card_transfer_flow_until_admin_approval(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['mobile' => '09121234567']);

        $r = $this->actingAs($user)->postJson('/api/orders', ['book_id' => $this->book->id, 'method' => 'card'])
            ->assertCreated()
            ->assertJsonPath('order.amount', 180000)
            ->assertJsonPath('card.number', '6037991122334455');
        $orderId = $r->json('order.id');

        $this->actingAs($user)->post("/api/orders/{$orderId}/receipt", [
            'tracking_code' => '123456', 'card_last4' => '9876',
            'receipt' => UploadedFile::fake()->image('r.jpg'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('order.status', Order::AWAITING_REVIEW);

        $order = Order::find($orderId);
        Storage::disk('local')->assertExists($order->receipt_path);
        $this->assertFalse($user->purchasedBooks()->exists());

        $admin = User::factory()->create(['is_admin' => true]);
        app(OrderService::class)->approve($order, $admin);
        $this->assertTrue($user->purchasedBooks()->where('books.id', $this->book->id)->exists());
        $this->assertSame(Order::PAID, $order->fresh()->status);

        // دوباره خریدن ← 409
        $this->actingAs($user)->postJson('/api/orders', ['book_id' => $this->book->id, 'method' => 'card'])->assertStatus(409);
    }

    public function test_receipt_only_by_owner_and_card_disabled(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->actingAs($user)->postJson('/api/orders', ['book_id' => $this->book->id, 'method' => 'card'])->json('order.id');
        $this->actingAs($other)->postJson("/api/orders/{$id}/receipt", ['tracking_code' => '1'])->assertNotFound();

        config(['shop.card.enabled' => false]);
        $this->actingAs($user)->postJson('/api/orders', ['book_id' => $this->book->id, 'method' => 'card'])->assertStatus(422);
        $this->getJson('/api/shop/options')->assertOk()->assertJsonPath('card', null);
    }

    public function test_gateway_order_returns_signed_link_and_unsigned_is_rejected(): void
    {
        $user = User::factory()->create();
        $r = $this->actingAs($user)->postJson('/api/orders', ['book_id' => $this->book->id, 'method' => 'gateway'])
            ->assertCreated();
        $url = $r->json('pay_url');
        $this->assertStringContainsString('signature=', $url);

        $orderId = $r->json('order.id');
        $this->get("/pay/{$orderId}")->assertForbidden();
    }

    public function test_free_book_is_granted_immediately_and_old_pending_is_canceled(): void
    {
        $user = User::factory()->create();
        $first = $this->actingAs($user)->postJson('/api/orders', ['book_id' => $this->book->id, 'method' => 'gateway'])->json('order.id');
        $this->actingAs($user)->postJson('/api/orders', ['book_id' => $this->book->id, 'method' => 'gateway']);
        $this->assertSame(Order::CANCELED, Order::find($first)->status);

        $free = Book::create(['title' => 'Free', 'folder_name' => 'free', 'price' => 0]);
        $this->actingAs($user)->postJson('/api/orders', ['book_id' => $free->id, 'method' => 'gateway'])
            ->assertCreated()->assertJsonPath('order.status', Order::PAID);
        $this->assertTrue($user->purchasedBooks()->where('books.id', $free->id)->exists());
    }

    public function test_admin_pages_render_and_approve_action_works(): void
    {
        $user = User::factory()->create(['mobile' => '09121234567']);
        $order = Order::create([
            'user_id' => $user->id, 'book_id' => $this->book->id, 'amount' => 180000, 'method' => 'card',
            'status' => Order::AWAITING_REVIEW, 'card_tracking_code' => '777',
        ]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        \Livewire\Livewire::test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->callTableAction('approve', $order);
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertTrue($user->purchasedBooks()->exists());

        \Livewire\Livewire::test(\App\Filament\Resources\UserResource\Pages\ListUsers::class)
            ->assertCanSeeTableRecords([$user]);
    }

    public function test_receipt_image_is_admin_only(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'book_id' => $this->book->id, 'amount' => 1, 'method' => 'card',
            'status' => Order::AWAITING_REVIEW, 'receipt_path' => 'receipts/x.jpg',
        ]);
        Storage::disk('local')->put('receipts/x.jpg', 'img');
        $this->actingAs($user)->get("/admin/order-receipt/{$order->id}")->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get("/admin/order-receipt/{$order->id}")->assertOk();
    }
}
