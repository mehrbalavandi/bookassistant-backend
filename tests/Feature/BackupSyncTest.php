<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 🌟 BK-1..3: پشتیبانِ «مرورِ من»، تنظیمات و دستگاه‌ها. */
class BackupSyncTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $id, int $updated, array $extra = []): array
    {
        return array_merge([
            'id' => $id, 'kind' => 'word', 'text' => 'cohesive', 'imp' => 2,
            'status' => 'fresh', 'bookId' => '7', 'updated' => $updated,
        ], $extra);
    }

    public function test_first_sync_uploads_and_second_device_downloads(): void
    {
        $user = User::factory()->create();
        $a = $this->actingAs($user)->postJson('/api/backup/study/sync', [
            'since_ms' => null,
            'items' => [$this->item('w|cohesive', 1000), $this->item('p|7|12|3|abc', 1000, ['kind' => 'passage', 'text' => 'Read the text', 'page' => 12])],
        ])->assertOk();
        $this->assertCount(2, $a->json('items'));

        // دستگاهِ دوم بدونِ داده
        $b = $this->actingAs($user)->postJson('/api/backup/study/sync', ['since_ms' => null, 'items' => []])->assertOk();
        $ids = collect($b->json('items'))->pluck('id')->sort()->values()->all();
        $this->assertSame(['p|7|12|3|abc', 'w|cohesive'], $ids);
        $this->assertSame(12, collect($b->json('items'))->firstWhere('id', 'p|7|12|3|abc')['page']);
    }

    public function test_last_write_wins_and_deletes_propagate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/backup/study/sync', ['items' => [$this->item('w|x', 2000, ['imp' => 3])]]);

        // نسخه‌ی کهنه‌تر از دستگاهِ دیگر نادیده گرفته می‌شود
        $this->actingAs($user)->postJson('/api/backup/study/sync', ['items' => [$this->item('w|x', 1500, ['imp' => 1])]]);
        $this->assertDatabaseHas('study_items', ['item_id' => 'w|x', 'importance' => 3]);

        // حذفِ تازه‌تر
        $r = $this->actingAs($user)->postJson('/api/backup/study/sync', [
            'items' => [['id' => 'w|x', 'kind' => 'word', 'updated' => 3000, 'deleted' => true]],
        ])->assertOk();
        $this->assertTrue(collect($r->json('items'))->firstWhere('id', 'w|x')['deleted']);
    }

    public function test_incremental_since_and_isolation_between_users(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $first = $this->actingAs($u1)->postJson('/api/backup/study/sync', ['items' => [$this->item('w|a', 1)]]);
        $this->actingAs($u2)->postJson('/api/backup/study/sync', ['items' => [$this->item('w|b', 1)]]);

        $this->travel(1)->minutes();
        $r = $this->actingAs($u1)->postJson('/api/backup/study/sync', [
            'since_ms' => $first->json('server_ms') + 20_000, 'items' => [],
        ])->assertOk();
        $this->assertCount(0, $r->json('items'));
        $all = $this->actingAs($u1)->postJson('/api/backup/study/sync', ['since_ms' => null, 'items' => []]);
        $this->assertSame(['w|a'], collect($all->json('items'))->pluck('id')->all());
    }

    public function test_settings_backup_and_devices(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson('/api/backup/settings', ['data' => ['audio_start_mode' => 'fromStart'], 'updated' => 10])->assertOk();
        $this->actingAs($user)->putJson('/api/backup/settings', ['data' => ['audio_start_mode' => 'resume'], 'updated' => 5])->assertOk();
        $this->actingAs($user)->getJson('/api/backup/settings')->assertJsonPath('data.audio_start_mode', 'fromStart');

        $this->actingAs($user)->postJson('/api/devices', [
            'device_id' => 'abc', 'platform' => 'android', 'app_version' => '1.2.0', 'build_number' => 7,
        ])->assertOk();
        $this->assertDatabaseHas('user_devices', ['user_id' => $user->id, 'device_id' => 'abc', 'build_number' => 7]);
        $this->assertNotNull($user->fresh()->last_seen_at);
    }

    public function test_requires_auth(): void
    {
        $this->postJson('/api/backup/study/sync', ['items' => []])->assertUnauthorized();
    }
}
