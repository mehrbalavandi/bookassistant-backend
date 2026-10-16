<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Book;
use App\Models\Order;
use App\Models\StudyItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** 🌟 ADM-1..3: ویرایشِ کاربر، رمزِ دلخواهِ ادمین، حذف با انتخابِ «نگه‌داشتن/پاک‌کردن». */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->book = Book::create(['title' => 'Mindset 01', 'folder_name' => 'm1', 'price' => 1000]);
        $this->user = User::create(['name' => 'A B', 'first_name' => 'A', 'last_name' => 'B', 'mobile' => '09121234567']);
        $this->user->purchasedBooks()->attach($this->book->id);
        $this->user->createToken('app');
        StudyItem::create([
            'user_id' => $this->user->id, 'item_id' => 'w|x', 'kind' => 'word', 'payload' => ['id' => 'w|x'],
            'client_updated_ms' => 1, 'server_ms' => 1,
        ]);
    }

    public function test_admin_edits_profile_and_sets_one_char_password(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->user->id])
            ->fillForm([
                'first_name' => 'مهرشاد', 'last_name' => 'بلوندی',
                'mobile' => '۰۹۳۵۱۱۱۲۲۳۳', 'email' => 'm@example.com', 'password' => '1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $u = $this->user->fresh();
        $this->assertSame('مهرشاد بلوندی', $u->name);
        $this->assertSame('09351112233', $u->mobile);
        $this->assertTrue(Hash::check('1', $u->password));

        // ورودِ ایمیلی با همان رمزِ یک‌رقمی
        $this->postJson('/api/login', ['device_id' => 'test-device-0001', 'email' => 'm@example.com', 'password' => '1'])->assertOk();
    }

    public function test_empty_password_keeps_current_and_invalid_mobile_rejected(): void
    {
        $this->user->forceFill(['password' => 'secret'])->save();
        Livewire::test(EditUser::class, ['record' => $this->user->id])
            ->fillForm(['password' => '', 'mobile' => '12345'])
            ->call('save')
            ->assertHasFormErrors(['mobile']);
        Livewire::test(EditUser::class, ['record' => $this->user->id])
            ->fillForm(['password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('secret', $this->user->fresh()->password));
    }

    public function test_admin_creates_user(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['first_name' => 'X', 'last_name' => 'Y', 'mobile' => '09120000000'])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('users', ['mobile' => '09120000000', 'name' => 'X Y']);
    }

    public function test_delete_keeping_data_deactivates_and_blocks_login(): void
    {
        Livewire::test(ListUsers::class)
            ->callTableAction('deleteUser', $this->user, data: ['mode' => 'keep'])
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted('users', ['id' => $this->user->id]);
        $this->assertDatabaseHas('book_user', ['user_id' => $this->user->id]);
        $this->assertDatabaseHas('study_items', ['user_id' => $this->user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/auth/otp/request', ['device_id' => 'test-device-0001', 'mobile' => '09121234567'])->assertForbidden();

        // بازگرداندن
        Livewire::test(ListUsers::class, ['tableFilters' => ['trashed' => ['value' => 1]]])
            ->callTableAction('restore', User::withTrashed()->find($this->user->id));
        $this->assertNotSoftDeleted('users', ['id' => $this->user->id]);
    }

    public function test_delete_purging_removes_everything(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/'.$this->user->id.'/r.jpg', 'x');
        Order::create([
            'user_id' => $this->user->id, 'book_id' => $this->book->id, 'amount' => 1000, 'method' => 'card',
            'status' => Order::AWAITING_REVIEW, 'receipt_path' => 'receipts/'.$this->user->id.'/r.jpg',
        ]);

        Livewire::test(ListUsers::class)
            ->callTableAction('deleteUser', $this->user, data: ['mode' => 'purge']);

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
        foreach (['book_user', 'orders', 'study_items'] as $t) {
            $this->assertDatabaseMissing($t, ['user_id' => $this->user->id]);
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Storage::disk('local')->assertMissing('receipts/'.$this->user->id.'/r.jpg');

        // همان شماره دوباره می‌تواند ثبت‌نام کند
        $this->postJson('/api/auth/otp/request', ['device_id' => 'test-device-0001', 'mobile' => '09121234567'])->assertOk();
    }

    public function test_panel_shows_project_brand_not_filament(): void
    {
        $this->get('/admin')->assertOk()
            ->assertSee('آکادمی زبان و آیلتس')
            ->assertSee('images/logo-192.png')
            ->assertDontSee('filamentphp.com');
        $this->get('/login')->assertOk()->assertSee('favicon.ico');
    }

    public function test_admin_cannot_delete_self(): void
    {
        Livewire::test(ListUsers::class)->assertTableActionHidden('deleteUser', $this->admin);
    }
}
