<?php

namespace Tests\Feature;

use App\Filament\Resources\AppReleaseResource\Pages\CreateAppRelease;
use App\Models\AppRelease;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 🌟 توزیعِ اپ از سایت: API آخرین نسخه، اجباری‌بودن، صفحه‌ی دریافت و خودِ فایل.
 */
class AppReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function release(int $build, string $version, array $extra = []): AppRelease
    {
        $path = "app-releases/app-{$build}.apk";
        Storage::disk('local')->put($path, str_repeat('x', 2048));

        return AppRelease::create(array_merge([
            'platform' => 'android',
            'version_name' => $version,
            'build_number' => $build,
            'file_path' => $path,
            'changelog' => "مورد ۱\nمورد ۲",
        ], $extra));
    }

    public function test_latest_returns_highest_published_build(): void
    {
        $this->release(3, '1.0.2');
        $this->release(5, '1.1.0');
        $this->release(9, '2.0.0', ['is_published' => false]);

        $this->getJson('/api/app/latest?platform=android')
            ->assertOk()
            ->assertJsonPath('data.build_number', 5)
            ->assertJsonPath('data.version_name', '1.1.0')
            ->assertJsonPath('data.file_size', 2048)
            ->assertJsonPath('data.page_url', route('app.page'))
            ->assertJsonPath('data.min_supported_build', 0);
    }

    public function test_min_supported_build_is_the_latest_mandatory_release(): void
    {
        $this->release(3, '1.0.2', ['is_mandatory' => true]);
        $this->release(4, '1.0.3');

        $this->getJson('/api/app/latest?platform=android')
            ->assertJsonPath('data.build_number', 4)
            ->assertJsonPath('data.is_mandatory', false)
            ->assertJsonPath('data.min_supported_build', 3);
    }

    public function test_latest_is_null_without_releases_and_rejects_unknown_platform(): void
    {
        $this->getJson('/api/app/latest?platform=android')->assertOk()->assertJsonPath('data', null);
        $this->getJson('/api/app/latest?platform=ios')->assertStatus(422);
    }

    public function test_download_serves_latest_apk_with_versioned_name(): void
    {
        $this->release(5, '1.1.0');

        $response = $this->get('/app/download/android')->assertOk();
        $this->assertSame('application/vnd.android.package-archive', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ielts-assistant-1.1.0.apk', $response->headers->get('Content-Disposition'));
    }

    public function test_download_404_without_release(): void
    {
        $this->get('/app/download/android')->assertNotFound();
    }

    public function test_page_shows_version_and_changelog(): void
    {
        $this->release(5, '1.1.0');

        $this->get('/app')->assertOk()->assertSee('1.1.0')->assertSee('مورد ۲')->assertSee(route('app.download', 'android'));
    }

    public function test_replacing_or_deleting_release_removes_old_file(): void
    {
        $release = $this->release(5, '1.1.0');
        Storage::disk('local')->put('app-releases/new.apk', 'yy');

        $release->update(['file_path' => 'app-releases/new.apk']);
        Storage::disk('local')->assertMissing('app-releases/app-5.apk');
        $this->assertSame(2, $release->fresh()->file_size);

        $release->delete();
        Storage::disk('local')->assertMissing('app-releases/new.apk');
    }

    public function test_admin_cannot_publish_same_build_twice_for_a_platform(): void
    {
        $this->release(5, '1.1.0');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateAppRelease::class)
            ->fillForm([
                'platform' => 'android',
                'version_name' => '1.1.1',
                'build_number' => 5,
                'file_path' => [UploadedFile::fake()->create('app.apk', 10, 'application/vnd.android.package-archive')],
            ])
            ->call('create')
            ->assertHasFormErrors(['build_number' => 'unique']);
    }
}
