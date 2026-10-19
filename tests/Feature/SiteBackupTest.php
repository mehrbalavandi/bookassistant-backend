<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupsPage;
use App\Models\Book;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\SharedStudyItem;
use App\Models\User;
use App\Services\SiteBackupService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** 🌟 BKP-1: پشتیبان، فهرست، بازگردانی (با پشتیبانِ ایمنی) و صفحه‌ی پنل. */
class SiteBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_and_restore_round_trip(): void
    {
        Storage::fake('local');
        $svc = app(SiteBackupService::class);
        $user = User::factory()->create(['name' => 'Sara']);
        $book = Book::create(['title' => 'Mindset 01', 'folder_name' => 'm1', 'price' => 1000]);
        $user->purchasedBooks()->attach($book->id);
        Storage::disk('local')->put('receipts/r1.jpg', 'img');
        Order::create(['user_id' => $user->id, 'book_id' => $book->id, 'amount' => 1000, 'method' => 'card',
            'status' => Order::AWAITING_REVIEW, 'receipt_path' => 'receipts/r1.jpg']);
        SharedStudyItem::create(['type' => 'word', 'text' => 'ability', 'payload' => ['type' => 'word', 'text' => 'ability']]);
        Feedback::create(['user_id' => $user->id, 'kind' => 'bug', 'subject' => 's', 'message' => 'm']);

        $path = $svc->create();
        $m = $svc->manifest($path);
        $this->assertSame(1, $m['tables']['users']);
        $this->assertSame(1, $m['tables']['orders']);
        $this->assertSame(1, $m['tables']['shared_study_items']);
        $this->assertSame(1, $m['files']);

        // خرابی بعد از پشتیبان
        Order::query()->delete();
        $user->update(['name' => 'Changed']);
        User::factory()->create();
        Storage::disk('local')->delete('receipts/r1.jpg');

        $r = $svc->restore(basename($path));
        $this->assertSame(1, User::count());
        $this->assertSame('Sara', User::first()->name);
        $this->assertSame(1, Order::count());
        $this->assertTrue($user->fresh()->purchasedBooks()->exists());
        Storage::disk('local')->assertExists('receipts/r1.jpg');
        // پشتیبانِ ایمنیِ پیش از بازگردانی
        $this->assertStringContainsString('before-restore', $r['safety']);
        $this->assertCount(2, $svc->list());

        $this->assertSame(1, $svc->prune(1));
        $this->assertCount(1, $svc->list());
    }

    public function test_panel_page_creates_and_lists(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(BackupsPage::class)->callAction('create')->assertNotified();
        $name = app(SiteBackupService::class)->list()[0]['name'];
        Livewire::test(BackupsPage::class)->assertSee($name);
        $this->artisan('site:backup')->assertExitCode(0);
        $this->assertCount(2, app(SiteBackupService::class)->list());

        // بی‌تأییدِ نوشتاری بازگردانی نمی‌شود
        Livewire::test(BackupsPage::class)
            ->callAction('restore', ['confirm' => 'نه'], ['name' => $name])
            ->assertHasActionErrors(['confirm']);
    }
}
