<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\DeviceService;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** 🌟 DEV-1..4: محدودیتِ دستگاه، توکنِ وابسته به دستگاه، آزادسازی و مجوزِ آفلاین. */
class DeviceBindingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sms.driver' => 'log', 'app.debug' => true, 'sms.otp.expose_in_debug' => true,
            'devices.max_per_user' => 1, 'devices.release_cooldown_days' => 30,
            'devices.require_binding' => true,
            'devices.license.signing_key' => base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES)),
        ]);
        $this->user = User::create(['name' => 'A B', 'first_name' => 'A', 'last_name' => 'B', 'mobile' => '09121234567']);
        $this->book = Book::create(['title' => 'Mindset 01', 'folder_name' => 'm1', 'price' => 1000]);
        $this->user->purchasedBooks()->attach($this->book->id);
    }

    private function login(string $deviceId, array $extra = [])
    {
        $code = $this->postJson('/api/auth/otp/request', ['mobile' => '09121234567', 'device_id' => $deviceId])
            ->assertOk()->json('debug_code');
        // هر درخواستِ کد ۱۲۰ ثانیه فاصله می‌خواهد؛ در تست کدِ قبلی را کنار می‌گذاریم.
        \App\Models\OtpCode::query()->update(['created_at' => now()->subMinutes(5)]);

        return $this->postJson('/api/auth/otp/verify', array_merge([
            'mobile' => '09121234567', 'code' => $code, 'device_id' => $deviceId,
            'device_name' => 'Pixel', 'platform' => 'android',
        ], $extra));
    }

    private function api(string $token, string $deviceId)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->withHeader(DeviceService::HEADER, $deviceId);
    }

    public function test_token_works_only_from_its_own_device(): void
    {
        $token = $this->login('h1-device-aaaa')->assertOk()->json('token');

        $this->api($token, 'h1-device-aaaa')->getJson('/api/me')->assertOk();

        // هدر جا افتاده: رد، ولی توکن سالم می‌ماند.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->withHeaders([DeviceService::HEADER => ''])->getJson('/api/me')
            ->assertStatus(401)->assertJson(['reason' => 'device_header_missing']);
        $this->assertSame(1, PersonalAccessToken::count());

        // توکنِ کپی‌شده روی دستگاهِ دیگر: رد و باطل.
        $this->api($token, 'h1-device-bbbb')->getJson('/api/me')
            ->assertStatus(401)->assertJson(['reason' => 'device_mismatch']);
        $this->assertSame(0, PersonalAccessToken::count());
        $this->api($token, 'h1-device-aaaa')->getJson('/api/me')->assertStatus(401);
    }

    public function test_second_device_is_refused_until_the_first_is_released(): void
    {
        $tokenA = $this->login('h1-device-aaaa')->assertOk()->json('token');

        $r = $this->login('h1-device-bbbb')->assertStatus(409)
            ->assertJson(['reason' => 'device_limit', 'limit' => 1, 'can_release' => true]);
        $this->assertCount(1, $r->json('devices'));
        $this->assertNotEmpty($r->json('ticket'));
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->whereNull('user_device_id')->count(), 'در ۴۰۹ هیچ توکنی ساخته نمی‌شود');

        $old = $r->json('devices.0.id');
        $tokenB = $this->postJson('/api/auth/device/replace', ['ticket' => $r->json('ticket'), 'release_device_id' => $old])
            ->assertOk()->assertJsonPath('device.current', true)->json('token');

        $this->api($tokenB, 'h1-device-bbbb')->getJson('/api/me')->assertOk();
        // دستگاهِ قبلی در اولین درخواست می‌فهمد آزاد شده.
        $this->api($tokenA, 'h1-device-aaaa')->getJson('/api/me')
            ->assertStatus(401)->assertJson(['reason' => 'device_revoked']);
        $this->api($tokenA, 'h1-device-aaaa')->getJson('/api/books')
            ->assertOk()->assertJsonPath('session.valid', false);

        // دوباره ظرفِ ۳۰ روز: نه.
        $r2 = $this->login('h1-device-aaaa')->assertStatus(409)->assertJson(['can_release' => false]);
        $this->assertNull($r2->json('ticket'));
        $this->assertNotNull($r2->json('next_release_at'));

        // بعد از ۳۰ روز: بله.
        $this->travel(31)->days();
        $this->login('h1-device-aaaa')->assertStatus(409)->assertJson(['can_release' => true]);
    }

    public function test_relogin_on_the_same_device_is_free_and_replaces_its_session(): void
    {
        $t1 = $this->login('h1-device-aaaa')->assertOk()->json('token');
        $this->api($t1, 'h1-device-aaaa')->postJson('/api/logout')->assertOk();
        $t2 = $this->login('h1-device-aaaa')->assertOk()->json('token');
        $t3 = $this->login('h1-device-aaaa')->assertOk()->json('token');

        $this->assertSame(1, UserDevice::where('user_id', $this->user->id)->active()->count());
        $this->assertSame(1, PersonalAccessToken::count(), 'هر دستگاه فقط یک نشست');
        $this->api($t2, 'h1-device-aaaa')->getJson('/api/me')->assertStatus(401);
        $this->api($t3, 'h1-device-aaaa')->getJson('/api/me')->assertOk();
    }

    public function test_admin_release_and_per_user_limit(): void
    {
        $this->login('h1-device-aaaa')->assertOk();
        $device = UserDevice::where('device_id', 'h1-device-aaaa')->first();

        app(DeviceService::class)->release($device, 'admin');
        $this->login('h1-device-bbbb')->assertOk();
        // آزادسازیِ ادمین در فاصله‌ی ۳۰روزه‌ی کاربر حساب نمی‌شود.
        $this->assertNull(app(DeviceService::class)->nextSelfReleaseAt($this->user));

        $this->user->forceFill(['max_devices' => 2])->save();
        $this->login('h1-device-cccc')->assertOk();
        $this->login('h1-device-dddd')->assertStatus(409)->assertJson(['limit' => 2]);
    }

    public function test_user_releases_a_device_from_the_app(): void
    {
        $this->user->forceFill(['max_devices' => 2])->save();
        $tA = $this->login('h1-device-aaaa')->assertOk()->json('token');
        $tB = $this->login('h1-device-bbbb')->assertOk()->json('token');

        $list = $this->api($tA, 'h1-device-aaaa')->getJson('/api/devices')->assertOk()
            ->assertJson(['limit' => 2, 'can_release' => true]);
        $this->assertCount(2, $list->json('devices'));
        $bId = collect($list->json('devices'))->firstWhere('current', false)['id'];

        $this->api($tA, 'h1-device-aaaa')->deleteJson("/api/devices/{$bId}")->assertOk()
            ->assertJson(['current_released' => false]);
        $this->api($tB, 'h1-device-bbbb')->getJson('/api/me')->assertStatus(401)
            ->assertJson(['reason' => 'device_revoked']);

        $aId = UserDevice::where('device_id', 'h1-device-aaaa')->value('id');
        $this->api($tA, 'h1-device-aaaa')->deleteJson("/api/devices/{$aId}")->assertStatus(429)
            ->assertJson(['reason' => 'release_cooldown']);
    }

    public function test_old_app_versions_are_asked_to_update(): void
    {
        $this->postJson('/api/auth/otp/request', ['mobile' => '09121234567'])
            ->assertStatus(426)->assertJson(['reason' => 'update_required']);
        $this->user->forceFill(['email' => 'a@example.com', 'password' => 'secret'])->save();
        $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'secret'])
            ->assertStatus(426);

        // توکنِ قدیمیِ بی‌دستگاه: دیگر خریدها را نمی‌بیند و دانلود نمی‌کند.
        $legacy = $this->user->createToken('old')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $books = $this->withToken($legacy)->getJson('/api/books')->assertOk();
        $this->assertFalse($books->json('data.0.is_purchased'));
        $this->assertSame('unbound', $books->json('session.reason'));
        $this->app['auth']->forgetGuards();
        $this->withToken($legacy)->getJson("/api/books/{$this->book->id}/zip-info")
            ->assertStatus(401)->assertJson(['reason' => 'unbound']);
    }

    public function test_transition_mode_keeps_old_apps_working(): void
    {
        config(['devices.require_binding' => false]);
        $this->user->forceFill(['email' => 'a@example.com', 'password' => 'secret'])->save();
        $legacy = $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'secret'])
            ->assertOk()->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($legacy)->getJson('/api/books')->assertOk()->assertJsonPath('data.0.is_purchased', true)
            // اپِ تازه با این توکن یک‌بار خارج می‌شود تا دستگاهش ثبت شود.
            ->assertJsonPath('session.bound', false)->assertJsonPath('session.reason', 'unbound');
    }

    public function test_signed_offline_license(): void
    {
        $login = $this->login('h1-device-aaaa')->assertOk();
        $payload = app(LicenseService::class)->verify($login->json('license'));
        $this->assertNotNull($payload, 'امضا درست است');
        $this->assertSame((string) $this->user->id, $payload['uid']);
        $this->assertSame('h1-device-aaaa', $payload['did']);
        $this->assertSame([(string) $this->book->id], $payload['books']);
        $this->assertEqualsWithDelta(time() + 30 * 86400, $payload['exp'], 5);

        // دست‌کاری‌شده: نامعتبر.
        $forged = $login->json('license');
        $forged['payload'] = base64_encode(str_replace('"exp":', '"exp":9', base64_decode($forged['payload'])));
        $this->assertNull(app(LicenseService::class)->verify($forged));

        // GET /api/books و POST /api/devices مجوز را تمدید می‌کنند.
        $token = $login->json('token');
        $this->assertNotNull($this->api($token, 'h1-device-aaaa')->getJson('/api/books')
            ->assertJsonPath('session.bound', true)->json('license.sig'));
        $this->assertNotNull($this->api($token, 'h1-device-aaaa')
            ->postJson('/api/devices', ['app_version' => '1.2.0'])->assertOk()->json('license.sig'));
        $this->assertSame('1.2.0', UserDevice::where('device_id', 'h1-device-aaaa')->value('app_version'));

        // بدونِ کلید: ورود کار می‌کند ولی مجوزی صادر نمی‌شود.
        config(['devices.license.signing_key' => null]);
        $this->assertNull($this->api($token, 'h1-device-aaaa')->getJson('/api/license')->assertOk()->json('license'));
    }

    public function test_keygen_prints_a_working_key_pair(): void
    {
        config(['devices.license.signing_key' => null]);
        $this->artisan('license:keygen')->assertSuccessful()
            ->expectsOutputToContain('LICENSE_SIGNING_KEY=')
            ->expectsOutputToContain('kLicensePublicKey');
    }
}
