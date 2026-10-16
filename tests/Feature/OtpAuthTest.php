<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** 🌟 AUTH-1..3: ورود/ثبت‌نام با موبایل و کدِ پیامکی (درایورِ log). */
class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'log', 'app.debug' => true, 'sms.otp.expose_in_debug' => true]);
    }

    private function requestCode(string $mobile = '09121234567'): string
    {
        $r = $this->postJson('/api/auth/otp/request', ['device_id' => 'test-device-0001', 'mobile' => $mobile])->assertOk();

        return $r->json('debug_code');
    }

    public function test_new_user_signs_up_with_name(): void
    {
        $r = $this->postJson('/api/auth/otp/request', ['device_id' => 'test-device-0001', 'mobile' => '۰۹۱۲ ۱۲۳ ۴۵۶۷'])->assertOk();
        $this->assertFalse($r->json('registered'));
        $this->assertSame('09121234567', $r->json('mobile'));
        $code = $r->json('debug_code');

        // بدونِ نام ← needs_profile و کد هنوز مصرف نشده
        $this->postJson('/api/auth/otp/verify', ['device_id' => 'test-device-0001', 'mobile' => '09121234567', 'code' => $code])
            ->assertStatus(422)->assertJson(['needs_profile' => true]);

        $v = $this->postJson('/api/auth/otp/verify', ['device_id' => 'test-device-0001', 
            'mobile' => '09121234567', 'code' => $code, 'first_name' => 'مهرشاد', 'last_name' => 'بلوندی',
        ])->assertOk();
        $this->assertNotEmpty($v->json('token'));
        $this->assertSame('مهرشاد بلوندی', $v->json('user.name'));

        $this->withToken($v->json('token'))->withHeader('X-Device-Id', 'test-device-0001')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.mobile', '09121234567');
    }

    public function test_existing_user_logs_in_and_code_is_single_use(): void
    {
        User::create(['name' => 'A B', 'first_name' => 'A', 'last_name' => 'B', 'mobile' => '09121234567']);
        $code = $this->requestCode();
        $this->postJson('/api/auth/otp/verify', ['device_id' => 'test-device-0001', 'mobile' => '09121234567', 'code' => $code])->assertOk();
        $this->postJson('/api/auth/otp/verify', ['device_id' => 'test-device-0001', 'mobile' => '09121234567', 'code' => $code])
            ->assertStatus(422)->assertJson(['reason' => 'expired']);
    }

    public function test_wrong_codes_lock_after_max_attempts(): void
    {
        User::create(['name' => 'A B', 'mobile' => '09121234567']);
        $code = $this->requestCode();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/otp/verify', ['device_id' => 'test-device-0001', 'mobile' => '09121234567', 'code' => '00000'])
                ->assertStatus(422);
        }
        $this->postJson('/api/auth/otp/verify', ['device_id' => 'test-device-0001', 'mobile' => '09121234567', 'code' => '00000'])
            ->assertJson(['reason' => 'too_many']);
        // حتی کدِ درست هم دیگر پذیرفته نمی‌شود
        $this->postJson('/api/auth/otp/verify', ['device_id' => 'test-device-0001', 'mobile' => '09121234567', 'code' => $code])->assertStatus(422);
    }

    public function test_resend_is_throttled_and_invalid_mobile_rejected(): void
    {
        $this->requestCode();
        $this->postJson('/api/auth/otp/request', ['device_id' => 'test-device-0001', 'mobile' => '09121234567'])->assertStatus(429)
            ->assertJson(['success' => false]);
        $this->postJson('/api/auth/otp/request', ['device_id' => 'test-device-0001', 'mobile' => '12345'])->assertStatus(422);
        $this->assertSame(1, OtpCode::count());
    }

    public function test_code_is_not_exposed_with_real_driver_or_without_debug(): void
    {
        config(['app.debug' => false]);
        $r = $this->postJson('/api/auth/otp/request', ['device_id' => 'test-device-0001', 'mobile' => '09121234567'])->assertOk();
        $this->assertNull($r->json('debug_code'));
    }

    public function test_old_email_account_links_mobile(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode('09351112233');
        $this->actingAs($user)->postJson('/api/me/mobile', ['mobile' => '09351112233', 'code' => $code])
            ->assertOk()->assertJsonPath('user.mobile', '09351112233');
        // از این به بعد همان حساب با موبایل وارد می‌شود
        RateLimiter::clear('m:09351112233');
        $this->travel(3)->minutes();
        $code2 = $this->requestCode('09351112233');
        $this->postJson('/api/auth/otp/verify', ['device_id' => 'test-device-0001', 'mobile' => '09351112233', 'code' => $code2])
            ->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_web_login_with_otp(): void
    {
        $this->post('/login/otp/request', ['mobile' => '09121234567'])->assertRedirect('/login/otp');
        $code = session('otp_debug_code');
        $this->post('/login/otp/verify', ['code' => $code, 'first_name' => 'الف', 'last_name' => 'ب'])
            ->assertRedirect();
        $this->assertAuthenticated();
        $this->assertSame('09121234567', auth()->user()->mobile);
    }
}
