<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\OrdersRelationManager;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use App\Models\UserDevice;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

/** 🌟 ADM-4 / DEV-5: ویرایش و حذفِ سفارش‌ها و دستگاه‌ها در پنل. */
class AdminOrdersAndDevicesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'log']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->user = User::create(['name' => 'A B', 'first_name' => 'A', 'last_name' => 'B', 'mobile' => '09121234567']);
        $this->book = Book::create(['title' => 'Mindset 01', 'folder_name' => 'm1', 'price' => 1000]);
    }

    private function order(string $status, array $extra = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $this->user->id, 'book_id' => $this->book->id, 'amount' => 1000,
            'method' => Order::METHOD_CARD, 'status' => $status,
        ], $extra));
    }

    private function owns(): bool
    {
        return $this->user->purchasedBooks()->where('books.id', $this->book->id)->exists();
    }

    public function test_editing_to_paid_grants_and_back_with_revoke_takes_it_away(): void
    {
        $o = $this->order(Order::AWAITING_REVIEW);
        Livewire::test(ListOrders::class)->set('activeTab', 'all')
            ->callTableAction('editOrder', $o, ['amount' => 900, 'status' => Order::PAID, 'method' => Order::METHOD_CARD])
            ->assertHasNoTableActionErrors();
        $this->assertSame(900, $o->fresh()->amount);
        $this->assertTrue($this->owns());

        // بدونِ تیکِ «پس گرفته شود»: کتاب می‌ماند.
        Livewire::test(ListOrders::class)->set('activeTab', 'all')
            ->callTableAction('editOrder', $o, ['amount' => 900, 'status' => Order::CANCELED, 'method' => Order::METHOD_CARD]);
        $this->assertTrue($this->owns());

        $o->update(['status' => Order::PAID]);
        Livewire::test(ListOrders::class)->set('activeTab', 'all')
            ->callTableAction('editOrder', $o, ['amount' => 900, 'status' => Order::CANCELED, 'method' => Order::METHOD_CARD, 'revoke' => true])->assertHasNoTableActionErrors();
        $this->assertSame(Order::CANCELED, $o->fresh()->status);
        $this->assertFalse($this->owns());
    }

    public function test_delete_order_with_receipt_and_optional_revoke(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/r.jpg', 'x');
        $o = $this->order(Order::PAID, ['receipt_path' => 'receipts/r.jpg']);
        $this->user->purchasedBooks()->attach($this->book->id);
        $second = $this->order(Order::PAID);

        // سفارشِ پرداخت‌شده‌ی دیگری هست ← کتاب با revoke هم می‌ماند.
        Livewire::test(ListOrders::class)->set('activeTab', 'all')->callTableAction('deleteOrder', $o, ['revoke' => true])
            ->assertHasNoTableActionErrors();
        $this->assertNull(Order::find($o->id));
        Storage::disk('local')->assertMissing('receipts/r.jpg');
        $this->assertTrue($this->owns());

        Livewire::test(ListOrders::class)->set('activeTab', 'all')->callTableAction('deleteOrder', $second, ['revoke' => true]);
        $this->assertFalse($this->owns());
    }

    public function test_user_page_shows_orders_and_devices_with_edit_and_delete(): void
    {
        $o = $this->order(Order::PENDING);
        $d = UserDevice::create(['user_id' => $this->user->id, 'device_id' => 'h1-aaaaaaaabbbb', 'platform' => 'android',
            'model' => 'Xiaomi 2107113SG', 'build_mode' => 'debug']);
        $d->forceFill(['activated_at' => now()])->save();
        $token = $this->user->createToken('t');
        $token->accessToken->forceFill(['user_device_id' => $d->id])->save();

        $args = ['ownerRecord' => $this->user->fresh(), 'pageClass' => EditUser::class];
        Livewire::test(OrdersRelationManager::class, $args)->assertSuccessful()->assertSee('Mindset 01')
            ->callTableAction('deleteOrder', $o)->assertHasNoTableActionErrors();
        $this->assertNull(Order::find($o->id));

        Livewire::test(DevicesRelationManager::class, $args)->assertSuccessful()
            ->assertSee('h1-aaaaaa')->assertSee('debug')
            ->callTableAction('edit', $d, ['name' => 'گوشیِ مهرشاد', 'model' => 'Xiaomi'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('گوشیِ مهرشاد', $d->fresh()->name);

        Livewire::test(DevicesRelationManager::class, $args)->callTableAction('forget', $d)
            ->assertHasNoTableActionErrors();
        $this->assertNull(UserDevice::find($d->id));
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_user_page_has_tabs_and_list_links_to_orders(): void
    {
        $this->order(Order::PAID);
        Livewire::test(EditUser::class, ['record' => $this->user->id])->assertSuccessful()
            ->assertSee('مشخصات')->assertSee('سفارش‌ها')->assertSee('دستگاه‌ها');
        Livewire::test(EditUser::class, ['record' => $this->user->id])
            ->set('activeRelationManager', '1')->assertSuccessful();

        Livewire::test(ListUsers::class)->assertSuccessful()
            ->assertTableActionHasUrl('orders', UserResource::getUrl('edit', ['record' => $this->user, 'activeRelationManager' => 1]), $this->user)
            ->assertSee('سفارش‌ها (1)');
    }
}
