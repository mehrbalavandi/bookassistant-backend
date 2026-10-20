<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** 🌟 SITE-10: بعد از تمام شدنِ نشست ← ورود؛ همان کاربر ← همان صفحه، کاربرِ دیگر ← داشبورد. */
class SessionResumeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, bool $admin = false): User
    {
        return User::factory()->create(['email' => $email, 'password' => Hash::make('pass'), 'is_admin' => $admin]);
    }

    private function login(string $email)
    {
        return $this->post('/login', ['email' => $email, 'password' => 'pass']);
    }

    public function test_login_sets_and_logout_clears_the_previous_user_cookie(): void
    {
        $a = $this->user('a@example.com');
        $this->login('a@example.com')->assertCookie(LoginRedirect::COOKIE, (string) $a->id);
        $this->post('/logout')->assertCookieExpired(LoginRedirect::COOKIE);
    }

    public function test_same_user_continues_on_the_page_where_the_session_ended(): void
    {
        $a = $this->user('a@example.com');
        // نشست تمام شده (کوکیِ ورودِ قبلی هست، نشست نیست) و کاربر روی «سفارش‌های من» بود
        $this->withCookie(LoginRedirect::COOKIE, (string) $a->id);
        $this->get('/orders')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('نشستِ شما تمام شده است');

        $this->login('a@example.com')->assertRedirect('/orders');
    }

    public function test_another_user_goes_to_the_dashboard_instead(): void
    {
        $a = $this->user('a@example.com');
        $this->user('b@example.com');
        $this->withCookie(LoginRedirect::COOKIE, (string) $a->id);
        $this->get('/feedback')->assertRedirect('/login');

        $this->login('b@example.com')->assertRedirect(route('home'));
        $this->assertNull(session('url.intended'));
    }

    public function test_without_a_previous_session_the_intended_page_is_kept(): void
    {
        $this->user('b@example.com');
        // مثلاً «خرید» را قبل از ورود زده، یا قبلی عمداً خارج شده بود
        $this->get('/orders')->assertRedirect('/login');
        $this->get('/login')->assertDontSee('نشستِ شما تمام شده است');

        $this->login('b@example.com')->assertRedirect('/orders');
    }

    public function test_admin_page_is_never_the_target_of_a_non_admin(): void
    {
        $u = $this->user('u@example.com');
        $this->withCookie(LoginRedirect::COOKIE, (string) $u->id);
        $this->withSession(['url.intended' => url('/admin/users')]);

        $this->login('u@example.com')->assertRedirect(route('home'));
    }

    public function test_admin_continues_in_the_panel(): void
    {
        $admin = $this->user('admin@example.com', true);
        $this->withCookie(LoginRedirect::COOKIE, (string) $admin->id);
        $this->get('/admin/users')->assertRedirect('/login');

        $this->login('admin@example.com')->assertRedirect(url('/admin/users'));
    }

    public function test_panel_pages_carry_the_field_history_script(): void
    {
        $admin = $this->user('admin@example.com', true);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('fieldHistory:v1', false);
    }
}
