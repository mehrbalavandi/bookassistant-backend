<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettingsPage;
use App\Models\OtpCode;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\SiteSettings;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** 🌟 SITE-1..9: تنظیماتِ سایت از پنل، روش‌های ورود، ثبت‌نامِ ایمیلی، بازیابیِ رمز، تعمیر. */
class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE = ['device_id' => 'test-device-0001'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'log', 'app.debug' => true, 'sms.otp.expose_in_debug' => true]);
    }

    private function set(array $values): void
    {
        SiteSettings::save($values);
    }

    // ───────── ذخیره و خواندن ─────────

    public function test_saved_settings_override_config_and_secrets_are_encrypted(): void
    {
        $this->set(['site.auth.mobile_enabled' => false, 'sms.driver' => 'smsir', 'sms.smsir.api_key' => 'SECRET-KEY', 'shop.card.unique_max' => '50']);

        $this->assertFalse(config('site.auth.mobile_enabled'));
        $this->assertSame('smsir', config('sms.driver'));
        $this->assertSame(50, config('shop.card.unique_max'));
        $row = SiteSetting::find('sms.smsir.api_key');
        $this->assertTrue($row->encrypted);
        $this->assertStringNotContainsString('SECRET-KEY', $row->value);

        // درخواستِ بعدی (بوتِ تازه) هم همان را می‌بیند
        SiteSettings::flush();
        config(['sms.smsir.api_key' => null]);
        SiteSettings::apply();
        $this->assertSame('SECRET-KEY', config('sms.smsir.api_key'));

        // کلیدِ مخفیِ خالی = همان قبلی؛ کلیدِ ناشناخته نادیده
        $this->set(['sms.smsir.api_key' => '', 'app.key' => 'hacked']);
        $this->assertSame('SECRET-KEY', config('sms.smsir.api_key'));
        $this->assertNull(SiteSetting::find('app.key'));

        SiteSettings::forget(['sms.driver']);
        $this->assertNull(SiteSetting::find('sms.driver'));
    }

    public function test_admin_saves_settings_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->set(['payment.drivers.zarinpal.merchantId' => 'MERCHANT-1']);

        $this->get(SiteSettingsPage::getUrl())->assertOk()->assertSee('ورود و ثبت‌نام')->assertDontSee('MERCHANT-1');

        Livewire::test(SiteSettingsPage::class)
            ->set('data.site__auth__email_enabled', false)
            ->set('data.site__name', 'سایتِ آزمایشی')
            ->set('data.site__announcement__enabled', true)
            ->set('data.site__announcement__text', 'تخفیفِ ویژه')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(config('site.auth.email_enabled'));
        $this->assertSame('سایتِ آزمایشی', config('site.name'));
        $this->assertSame('MERCHANT-1', config('payment.drivers.zarinpal.merchantId'), 'secret left blank must survive');
        $this->assertSame($admin->id, SiteSetting::find('site.name')->updated_by);

        $this->get('/admin')->assertOk()->assertSee('سایتِ آزمایشی')->assertSee('روش‌های ورود');
    }

    // ───────── روش‌های ورود ─────────

    public function test_app_config_reports_methods_and_links(): void
    {
        $this->set(['site.auth.email_enabled' => false, 'site.support.phone' => '02112345678']);
        $this->getJson('/api/app/config')->assertOk()
            ->assertJsonPath('auth.mobile', true)
            ->assertJsonPath('auth.email', false)
            ->assertJsonPath('auth.messages.email', 'ورود و ثبت‌نام با ایمیل فعلاً فعال نیست.')
            ->assertJsonPath('support.phone', '02112345678')
            ->assertJsonPath('announcement', null)
            ->assertJsonStructure(['links' => ['terms', 'privacy', 'forgot_password']]);
    }

    public function test_mobile_disabled_blocks_api_and_site(): void
    {
        $this->set(['site.auth.mobile_enabled' => false]);

        $this->postJson('/api/auth/otp/request', self::DEVICE + ['mobile' => '09121234567'])
            ->assertStatus(403)->assertJson(['reason' => 'method_disabled', 'method' => 'mobile']);
        $this->postJson('/api/auth/otp/verify', self::DEVICE + ['mobile' => '09121234567', 'code' => '12345'])
            ->assertStatus(403)->assertJson(['reason' => 'method_disabled']);
        $this->assertSame(0, OtpCode::count());

        $this->get('/login')->assertOk()->assertSee('ورود و ثبت‌نام با شماره‌ی موبایل فعلاً فعال نیست.');
        $this->post('/login/otp/request', ['mobile' => '09121234567'])->assertRedirect(route('login'));
        $this->get('/login/otp')->assertRedirect(route('login'));
        $this->assertSame(0, OtpCode::count());
    }

    public function test_email_disabled_blocks_users_but_not_admins(): void
    {
        User::create(['name' => 'U', 'email' => 'u@example.com', 'password' => Hash::make('pass')]);
        User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => Hash::make('pass'), 'is_admin' => true]);
        $this->set(['site.auth.email_enabled' => false]);

        $this->postJson('/api/login', self::DEVICE + ['email' => 'u@example.com', 'password' => 'pass'])
            ->assertStatus(403)->assertJson(['reason' => 'method_disabled', 'method' => 'email']);
        $this->postJson('/api/register', self::DEVICE + ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'n@example.com', 'password' => 'Aa1!aaaa'])
            ->assertStatus(403);
        $this->postJson('/api/login', self::DEVICE + ['email' => 'a@example.com', 'password' => 'pass'])->assertOk();

        $this->get('/login?tab=email')->assertOk()->assertSee('ورود و ثبت‌نام با ایمیل فعلاً فعال نیست.')->assertSee('ورودِ مدیران');
        $this->post('/login', ['email' => 'u@example.com', 'password' => 'pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'pass']);
        $this->assertAuthenticated();
    }

    public function test_registration_closed_lets_existing_users_in_only(): void
    {
        User::create(['name' => 'A B', 'first_name' => 'A', 'last_name' => 'B', 'mobile' => '09120000000']);
        $this->set(['site.auth.registration_enabled' => false]);

        $this->postJson('/api/auth/otp/request', self::DEVICE + ['mobile' => '09121234567'])
            ->assertStatus(403)->assertJson(['reason' => 'registration_closed']);
        $this->postJson('/api/auth/otp/request', self::DEVICE + ['mobile' => '09120000000'])->assertOk();
        $this->postJson('/api/register', self::DEVICE + ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'n@example.com', 'password' => 'Aa1!aaaa'])
            ->assertStatus(403)->assertJson(['reason' => 'registration_closed']);
        $this->get('/register')->assertOk()->assertSee('ثبت‌نامِ حسابِ تازه فعلاً بسته است.');
    }

    // ───────── ثبت‌نامِ ایمیلی و بازیابیِ رمز ─────────

    public function test_email_registration_from_app_and_site(): void
    {
        $r = $this->postJson('/api/register', self::DEVICE + [
            'first_name' => 'مهرشاد', 'last_name' => 'بلوندی', 'email' => 'Me@Example.com', 'password' => 'Aa1!aaaa',
        ])->assertCreated();
        $this->assertNotEmpty($r->json('token'));
        $this->assertSame('مهرشاد بلوندی', $r->json('user.name'));
        $this->assertSame('me@example.com', $r->json('user.email'));

        // همان ایمیل دوباره ← پیامِ فارسی
        $this->postJson('/api/register', self::DEVICE + ['first_name' => 'a', 'last_name' => 'b', 'email' => 'me@example.com', 'password' => 'Aa1!aaaa'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'این ایمیل قبلاً ثبت شده است؛ وارد شوید یا رمز را بازیابی کنید.');

        // رمزِ ضعیف با قانونِ پیش‌فرض رد، با «متوسط» پذیرفته
        $weak = ['first_name' => 'a', 'last_name' => 'b', 'email' => 'w@example.com', 'password' => 'abcdefg1'];
        $err = $this->postJson('/api/register', self::DEVICE + $weak)->assertStatus(422)->json('errors.password.0');
        $this->assertStringContainsString('رمزِ عبور مناسب نیست', $err);
        $this->postJson('/api/register', self::DEVICE + ['email' => 'bad'])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'ایمیل معتبر نیست.')
            ->assertJsonPath('errors.first_name.0', 'پرکردنِ «نام» لازم است.');
        $this->set(['site.auth.password_mode' => 'medium']);
        $this->postJson('/api/register', self::DEVICE + $weak)->assertCreated();

        // سایت
        $this->post('/register', [
            'first_name' => 'سارا', 'last_name' => 'احمدی', 'email' => 's@example.com',
            'password' => 'abcdefg1', 'password_confirmation' => 'abcdefg1',
        ])->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertSame('سارا احمدی', User::where('email', 's@example.com')->first()->name);
    }

    public function test_password_reset_by_email(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'U', 'email' => 'u@example.com', 'password' => Hash::make('old')]);

        $this->postJson('/api/password/forgot', ['email' => 'u@example.com'])->assertOk()->assertJson(['success' => true]);
        // ایمیلِ ناشناخته: همان پاسخ، بی‌ایمیل
        $this->postJson('/api/password/forgot', ['email' => 'nobody@example.com'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token, $user) {
            $token = $n->token;
            $mail = $n->toMail($user);

            return str_contains($mail->subject, 'بازیابیِ رمزِ عبور');
        });
        Notification::assertCount(1);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'u@example.com']))->assertOk()->assertSee('ساختنِ رمزِ تازه');
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'u@example.com', 'password' => 'Nn1!nnnn', 'password_confirmation' => 'Nn1!nnnn',
        ])->assertRedirect(route('login', ['tab' => 'email']));
        $this->assertTrue(Hash::check('Nn1!nnnn', $user->fresh()->password));

        // توکن یک‌بارمصرف است
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'u@example.com', 'password' => 'Xx1!xxxx', 'password_confirmation' => 'Xx1!xxxx',
        ])->assertSessionHasErrors('email');
    }

    // ───────── تعمیر، اطلاعیه، صفحه‌ها ─────────

    public function test_maintenance_mode(): void
    {
        $this->set(['site.maintenance.enabled' => true, 'site.maintenance.message' => 'تا ساعتِ ۱۸ برمی‌گردیم']);

        $this->getJson('/api/books')->assertStatus(503)->assertJson(['reason' => 'maintenance', 'message' => 'تا ساعتِ ۱۸ برمی‌گردیم']);
        $this->getJson('/api/app/config')->assertOk()->assertJsonPath('maintenance.message', 'تا ساعتِ ۱۸ برمی‌گردیم');
        $this->get('/')->assertStatus(503)->assertSee('تا ساعتِ ۱۸ برمی‌گردیم');
        $this->get('/login')->assertOk();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_announcement_pages_and_security_headers(): void
    {
        $this->set([
            'site.announcement.enabled' => true, 'site.announcement.text' => 'خبرِ مهم', 'site.announcement.level' => 'warning',
            'site.pages.terms' => '<h2>بندِ یک</h2><p>متن</p><script>alert(1)</script>',
            'site.support.telegram' => '@help',
        ]);

        $this->get('/')->assertOk()->assertSee('خبرِ مهم')->assertSee('قوانین و مقررات')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/app/config')->assertJsonPath('announcement.text', 'خبرِ مهم')->assertJsonPath('announcement.level', 'warning');
        $this->get('/page/terms')->assertOk()->assertSee('<h2>بندِ یک</h2>', false)->assertDontSee('<script>', false);
        $this->get('/page/privacy')->assertOk()->assertSee('هنوز نوشته نشده');
        $this->get('/page/other')->assertNotFound();
        $this->get('/contact')->assertOk()->assertSee('https://t.me/help', false);
    }
}
