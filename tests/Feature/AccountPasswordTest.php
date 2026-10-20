<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** 🌟 SITE-13: تغییرِ رمز در سایت + فرم‌هایی که مدیرِ رمزِ مرورگر می‌شناسد */
class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.auth.password_mode' => 'medium']);
    }

    public function test_user_changes_password_with_the_current_one(): void
    {
        $u = User::factory()->create(['email' => 'a@example.com', 'password' => Hash::make('oldpass11')]);
        $this->actingAs($u)->get('/account/password')->assertOk()
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('autocomplete="new-password"', false);

        $this->actingAs($u)->post('/account/password', [
            'current_password' => 'wrong', 'password' => 'newpass22', 'password_confirmation' => 'newpass22',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($u)->post('/account/password', [
            'current_password' => 'oldpass11', 'password' => 'newpass22', 'password_confirmation' => 'newpass22',
        ])->assertRedirect('/account/password')->assertSessionHas('success');
        $this->assertTrue(Hash::check('newpass22', $u->fresh()->password));
        $this->assertAuthenticatedAs($u);
    }

    public function test_weak_or_unconfirmed_password_is_rejected(): void
    {
        $u = User::factory()->create(['email' => 'a@example.com', 'password' => Hash::make('oldpass11')]);
        $this->actingAs($u)->post('/account/password', [
            'current_password' => 'oldpass11', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
        $this->actingAs($u)->post('/account/password', [
            'current_password' => 'oldpass11', 'password' => 'newpass22', 'password_confirmation' => 'other',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('oldpass11', $u->fresh()->password));
    }

    public function test_user_without_a_password_can_create_one(): void
    {
        $u = User::factory()->create(['email' => 'm@example.com', 'mobile' => '09120000000', 'password' => null]);
        $this->actingAs($u)->get('/account/password')->assertOk()->assertSee('ساختنِ رمزِ عبور')
            ->assertDontSee('autocomplete="current-password"', false);
        $this->actingAs($u)->post('/account/password', ['password' => 'newpass22', 'password_confirmation' => 'newpass22'])
            ->assertSessionHas('success');
        $this->assertTrue(Hash::check('newpass22', $u->fresh()->password));
    }

    public function test_mobile_only_user_is_told_no_password_is_needed(): void
    {
        $u = User::factory()->create(['email' => null, 'mobile' => '09120000001', 'password' => null]);
        $this->actingAs($u)->get('/account/password')->assertOk()->assertSee('رمزِ عبور لازم ندارد');
        $this->actingAs($u)->post('/account/password', ['password' => 'newpass22', 'password_confirmation' => 'newpass22'])
            ->assertSessionHasErrors('password');
    }

    public function test_change_password_needs_login(): void
    {
        $this->get('/account/password')->assertRedirect('/login');
    }

    public function test_sign_in_and_sign_up_forms_name_the_username_field(): void
    {
        $this->get('/login')->assertSee('autocomplete="username"', false)->assertSee('autocomplete="current-password"', false);
        $this->get('/register')->assertSee('autocomplete="username"', false)->assertSee('autocomplete="new-password"', false);
    }
}
