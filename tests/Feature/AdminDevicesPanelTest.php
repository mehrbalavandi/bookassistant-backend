<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers\DevicesRelationManager;
use App\Models\User;
use App\Models\UserDevice;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** 🌟 DEV-2: جدولِ «دستگاه‌ها» در صفحه‌ی ویرایشِ کاربر (پنل). */
class AdminDevicesPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->user = User::create(['name' => 'A B', 'first_name' => 'A', 'last_name' => 'B', 'mobile' => '09121234567']);
    }

    private function device(string $id, bool $active = true): UserDevice
    {
        $d = UserDevice::create(['user_id' => $this->user->id, 'device_id' => $id, 'platform' => 'android', 'model' => 'Pixel']);
        $d->forceFill(['activated_at' => $active ? now() : null])->save();

        return $d;
    }

    private function table()
    {
        return Livewire::test(DevicesRelationManager::class, [
            'ownerRecord' => $this->user->fresh(),
            'pageClass' => EditUser::class,
        ]);
    }

    public function test_devices_table_renders_after_saving_the_user(): void
    {
        $this->device('h1-aaaaaaaa');
        $this->device('r1-old-legacy', active: false);

        // خطای گزارش‌شده: بعد از ذخیره‌ی کاربر، جدول با «Cannot use ::class on null» می‌شکست.
        Livewire::test(EditUser::class, ['record' => $this->user->id])
            ->fillForm(['first_name' => 'X', 'max_devices' => 2])->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, $this->user->fresh()->max_devices);

        $this->table()->assertSuccessful()
            ->assertSee('Pixel')->assertSee('فعال')->assertSee('ثبت‌نشده (نسخه‌ی قدیمی)')
            ->call('$refresh')->assertSuccessful();
    }

    public function test_admin_releases_a_device(): void
    {
        $d = $this->device('h1-aaaaaaaa');
        $this->table()->callTableAction('release', $d)->assertHasNoTableActionErrors();

        $d->refresh();
        $this->assertFalse($d->isActive());
        $this->assertSame('admin', $d->revoked_by);
    }
}
