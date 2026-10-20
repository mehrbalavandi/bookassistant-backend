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
        ])->assertRedirect(route('home'))->assertSessionHas('flash');
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
            ->assertSessionHas('flash');
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

    public function test_dashboard_links_to_change_password_for_email_users(): void
    {
        $u = User::factory()->create(['email' => 'a@example.com', 'is_admin' => false]);
        $this->actingAs($u)->get('/')->assertOk()->assertSee(route('account.password'), false);
        $m = User::factory()->create(['email' => null, 'mobile' => '09120000002', 'is_admin' => false]);
        $this->actingAs($m)->get('/')->assertOk()->assertDontSee(route('account.password'), false);
    }

    /** 🌟 SITE-14 */
    public function test_panel_login_page_sends_to_the_site_login_form(): void
    {
        $this->get('/admin/login')->assertRedirect(route('login', ['tab' => 'email']));
        $this->get('/admin')->assertRedirect(route('login', ['tab' => 'email']));

        config(['site.auth.email_enabled' => false]);
        $this->get('/admin/login')->assertRedirect(route('login', ['for' => 'admin']));
        $this->get('/login?for=admin')->assertOk()->assertSee('ورودِ مدیران')
            ->assertSee('autocomplete="current-password"', false)->assertSee('name="for" value="admin"', false);

        $admin = User::factory()->create(['email' => 'admin@example.com', 'password' => Hash::make('pass'), 'is_admin' => true]);
        // ‎/admin‎ پیش‌تر خواسته شده بود ← بعد از ورود همان‌جا
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'pass', 'for' => 'admin'])
            ->assertRedirect(url('/admin'));
        $this->assertAuthenticatedAs($admin);
    }

    /** 🌟 SITE-15 */
    public function test_after_saving_the_user_returns_to_the_page_they_came_from(): void
    {
        $u = User::factory()->create(['email' => 'a@example.com', 'password' => Hash::make('oldpass11')]);
        $this->actingAs($u)->get('/account/password', ['referer' => url('/orders')])->assertOk();
        // یک بار اشتباه (فرم دوباره نشان داده می‌شود) — مقصد نباید گم شود
        $this->actingAs($u)->post('/account/password', [
            'current_password' => 'wrong', 'password' => 'newpass22', 'password_confirmation' => 'newpass22',
        ]);
        $this->actingAs($u)->get('/account/password', ['referer' => url('/account/password')])->assertOk();

        $this->actingAs($u)->post('/account/password', [
            'current_password' => 'oldpass11', 'password' => 'newpass22', 'password_confirmation' => 'newpass22',
        ])->assertRedirect(url('/orders'));
        $this->actingAs($u)->get('/orders')->assertSee('رمزِ عبور عوض شد.');
    }
}
