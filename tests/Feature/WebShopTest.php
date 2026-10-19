<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 🌟 PAY-6..8: خرید در سایت (درگاه و کارت‌به‌کارت)، روش‌های قابلِ‌خاموش، لغوِ خودکار، اعلانِ ادمین. */
class WebShopTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->book = $this->bookWithContent(['title' => 'Mindset 01', 'folder_name' => 'mindset-01', 'price' => 200000, 'discount' => 10]);
        $this->user = User::factory()->create();
        config([
            'shop.gateway_enabled' => true,
            'shop.card.enabled' => true,
            'shop.card.number' => '6037-9911-2233-4455',
            'shop.card.holder' => 'مهرشاد بلوندی',
            'shop.card.unique_max' => 999,
        ]);
    }

    public function test_checkout_page_offers_only_enabled_methods(): void
    {
        $this->actingAs($this->user)->get(route('checkout', $this->book))->assertOk()
            ->assertSee('پرداختِ اینترنتی')->assertSee('کارت‌به‌کارت')->assertSee('180,000');

        config(['shop.gateway_enabled' => false]);
        $this->get(route('checkout', $this->book))->assertOk()->assertDontSee('💳 پرداختِ اینترنتی')->assertSee('🏦 کارت‌به‌کارت');
        $this->post(route('checkout.store', $this->book), ['method' => 'gateway'])->assertSessionHas('error');
        $this->assertSame(0, Order::count());

        config(['shop.card.enabled' => false]);
        $this->get(route('checkout', $this->book))->assertOk()->assertSee('خرید فعلاً ممکن نیست');
        $this->getJson('/api/shop/options')->assertJson(['gateway' => false, 'card' => null]);
    }

    public function test_gateway_purchase_redirects_to_signed_pay_link(): void
    {
        $r = $this->actingAs($this->user)->post(route('checkout.store', $this->book), ['method' => 'gateway']);
        $order = Order::firstOrFail();
        $this->assertSame('web', $order->source);
        $this->assertStringContainsString('/pay/'.$order->id, $r->headers->get('Location'));
        $this->assertStringContainsString('signature=', $r->headers->get('Location'));
    }

    public function test_card_purchase_and_receipt_on_site_notifies_admins(): void
    {
        Mail::fake();
        config(['shop.notify_email' => 'boss@example.com']);
        $admin = User::factory()->create(['is_admin' => true]);

        $r = $this->actingAs($this->user)->post(route('checkout.store', $this->book), ['method' => 'card']);
        $order = Order::firstOrFail();
        $r->assertRedirect(route('orders.show', $order));
        $this->assertSame(180000 + $order->amount_suffix, $order->amount);

        $this->get(route('orders.show', $order))->assertOk()
            ->assertSee(number_format($order->amount))->assertSee('6037 9911 2233 4455')->assertSee('ثبتِ رسیدِ واریز');

        $this->post(route('orders.receipt', $order), ['tracking_code' => '۱۲۳۴۵۶', 'card_last4' => '9876',
            'receipt' => UploadedFile::fake()->image('r.jpg')])
            ->assertRedirect(route('orders.show', $order));
        $order->refresh();
        $this->assertSame(Order::AWAITING_REVIEW, $order->status);
        $this->assertSame('123456', $order->card_tracking_code);
        Storage::disk('local')->assertExists($order->receipt_path);

        // اعلانِ پنل برای ادمین و ایمیل
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('Mindset 01', json_encode($admin->notifications()->first()->data, JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $this->user->notifications()->count());

        $this->get(route('orders.index'))->assertOk()->assertSee('Mindset 01')->assertSee('در انتظارِ بررسیِ رسید');
        // سفارشِ دیگری را نمی‌بیند
        $this->actingAs(User::factory()->create())->get(route('orders.show', $order))->assertNotFound();
    }

    public function test_stale_pending_orders_expire_and_auto_canceled_card_still_takes_receipt(): void
    {
        config(['shop.pending_expire_hours' => 48]);
        $old = Order::create(['user_id' => $this->user->id, 'book_id' => $this->book->id, 'amount' => 180123,
            'amount_suffix' => 123, 'method' => 'card', 'status' => Order::PENDING]);
        $old->forceFill(['created_at' => now()->subHours(50)])->save();
        $review = Order::create(['user_id' => $this->user->id, 'book_id' => $this->book->id, 'amount' => 180124,
            'method' => 'card', 'status' => Order::AWAITING_REVIEW]);
        $review->forceFill(['created_at' => now()->subHours(50)])->save();

        $this->artisan('orders:expire')->assertExitCode(0);
        $this->assertTrue($old->fresh()->isAutoCanceled());
        $this->assertSame(Order::AWAITING_REVIEW, $review->fresh()->status, 'a sent receipt is never expired');

        // رسیدِ دیرهنگام برای سفارشِ خودکار-لغوشده پذیرفته می‌شود (اپ)
        $this->actingAs($this->user)->postJson("/api/orders/{$old->id}/receipt", ['tracking_code' => '555'])
            ->assertOk()->assertJsonPath('order.status', Order::AWAITING_REVIEW);

        // سفارشی که با سفارشِ تازه‌تر جایگزین شده رسید نمی‌پذیرد
        $first = $this->postJson('/api/orders', ['book_id' => $this->book->id, 'method' => 'gateway'])->json('order.id');
        $this->postJson('/api/orders', ['book_id' => $this->book->id, 'method' => 'gateway']);
        $this->assertSame(Order::CANCELED, Order::find($first)->status);
        $this->assertFalse(Order::find($first)->canSubmitReceipt());
    }
}
