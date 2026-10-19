<?php

namespace Tests\Feature;

use App\Filament\Resources\FeedbackResource\Pages\EditFeedback;
use App\Filament\Resources\FeedbackResource\Pages\ListFeedback;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Feedback;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** 🌟 FB-1 در پنل؛ 🐞 فیلدِ رمزِ ویرایشِ کاربر را مرورگر پر نکند. */
class AdminFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_opens_and_replies(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['name' => 'Sara']);
        $f = Feedback::create(['user_id' => $user->id, 'kind' => 'bug', 'subject' => 'صوت', 'message' => 'قطع می‌شود']);

        Livewire::test(ListFeedback::class)->assertCanSeeTableRecords([$f]);
        Livewire::test(EditFeedback::class, ['record' => $f->id])
            ->assertSee('قطع می‌شود')
            ->fillForm(['status' => 'resolved', 'admin_reply' => 'در نسخه‌ی بعد درست شد'])
            ->call('save')->assertHasNoFormErrors();

        $f->refresh();
        $this->assertSame('resolved', $f->status);
        $this->assertSame($admin->id, $f->replied_by);
        $this->assertTrue($f->hasUnseenReply());
    }

    public function test_user_edit_password_field_is_not_autofilled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->assertSeeHtml('autocomplete="new-password"')
            ->assertFormSet(['password' => null]);
    }
}
