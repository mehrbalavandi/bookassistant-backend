<?php

namespace Tests\Feature;

use App\Filament\Resources\SharedStudyItemResource\Pages\CreateSharedStudyItem;
use App\Filament\Resources\SharedStudyItemResource\Pages\ListSharedStudyItems;
use App\Http\Controllers\Api\SharedStudyController;
use App\Models\SharedStudyItem;
use App\Models\User;
use App\Support\StudyItemFormat;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** 🌟 ST-7: «مرورِ آماده» — قالبِ یکسان، واردکردن، خوراکِ اپ، پنل. */
class SharedStudyItemsTest extends TestCase
{
    use RefreshDatabase;

    private const AI_OUTPUT = <<<'TXT'
Here is your JSON:
```json
{"format":"ielts-study-items","version":1,"items":[
 {"type":"word","text":"  Mitigate ","pos":"verb","ipa":"/ˈmɪt.ɪ.ɡeɪt/","cefr":"c1",
  "meaningFa":"کاهش دادن","pronounceFa":"میتیگِیت","explanationFa":"",
  "examples":[{"en":"Trees mitigate pollution.","fa":"درختان آلودگی را کاهش می‌دهند."},"Plain example."],
  "tags":["Writing","Environment"],"importance":3},
 {"type":"idiom","text":"a blessing in disguise","fa":"خیر در آن بود"},
 {"type":"grammar","text":"used to vs. be used to","explanationFa":"توضیح…"},
 {"type":"word","text":"mitigate","meaningFa":"تکراری"},
 {"type":"poem","text":"x"},
 {"type":"word","text":""}
]}
```
TXT;

    public function test_parse_cleans_ai_output(): void
    {
        $r = StudyItemFormat::parse(self::AI_OUTPUT);
        $this->assertCount(3, $r['items']);
        $this->assertCount(2, $r['errors']);

        [$w, $idiom, $tip] = $r['items'];
        $this->assertSame('word', $w['type']);
        $this->assertSame('Mitigate', $w['text']);
        $this->assertSame('C1', $w['cefr']);
        $this->assertArrayNotHasKey('explanationFa', $w, 'رشته‌ی خالی نمی‌ماند');
        $this->assertSame(['en' => 'Plain example.'], $w['examples'][1]);
        $this->assertSame(3, $w['importance']);
        $this->assertSame('word', $idiom['type']);
        $this->assertSame('خیر در آن بود', $idiom['meaningFa'], 'نامِ دیگرِ fa پذیرفته می‌شود');
        $this->assertSame('tip', $tip['type']);
        $this->assertSame(2, $tip['importance']);
    }

    public function test_import_adds_updates_and_restores(): void
    {
        $r = SharedStudyItem::import(self::AI_OUTPUT);
        $this->assertSame(['added' => 3, 'updated' => 0], array_slice($r, 0, 2));

        $item = SharedStudyItem::where('key', 'word|mitigate')->firstOrFail();
        $item->delete();
        $r = SharedStudyItem::import('[{"type":"word","text":"mitigate","meaningFa":"تخفیف دادن"}]');
        $this->assertSame(1, $r['updated']);
        $item->refresh();
        $this->assertFalse($item->trashed());
        $this->assertSame('تخفیف دادن', $item->payload['meaningFa']);
        $this->assertSame(3, SharedStudyItem::count());

        $this->assertSame(['added' => 0, 'updated' => 0], array_slice(SharedStudyItem::import('not json'), 0, 2));
    }

    public function test_feed_initial_and_incremental(): void
    {
        SharedStudyItem::import(self::AI_OUTPUT);
        $hidden = SharedStudyItem::create(['type' => 'word', 'text' => 'draft', 'payload' => [], 'published' => false]);

        $first = $this->getJson('/api/study/shared')->assertOk();
        $this->assertCount(3, $first->json('items'), 'بارِ اول فقط منتشرشده‌ها');
        $this->assertFalse($first->json('more'));
        $cursor = $first->json('cursor');
        $this->assertGreaterThanOrEqual($hidden->changed_ms, $cursor);

        $this->getJson("/api/study/shared?since={$cursor}")->assertOk()->assertJsonCount(0, 'items');

        $tip = SharedStudyItem::where('type', 'tip')->first();
        $tip->delete();
        $hidden->update(['published' => true]);
        $next = $this->getJson("/api/study/shared?since={$cursor}")->assertOk();
        $byId = collect($next->json('items'))->keyBy('sid');
        $this->assertTrue($byId[$tip->id]['deleted']);
        $this->assertNull($byId[$tip->id]['item']);
        $this->assertFalse($byId[$hidden->id]['deleted']);
        $this->assertSame('draft', $byId[$hidden->id]['item']['text']);
    }

    public function test_feed_pages_with_cursor(): void
    {
        SharedStudyController::$limit = 2;
        try {
            SharedStudyItem::import(self::AI_OUTPUT);
            $p1 = $this->getJson('/api/study/shared')->assertOk();
            $this->assertCount(2, $p1->json('items'));
            $this->assertTrue($p1->json('more'));
            $p2 = $this->getJson('/api/study/shared?since='.$p1->json('cursor'))->assertOk();
            $this->assertCount(1, $p2->json('items'));
            $this->assertFalse($p2->json('more'));
        } finally {
            SharedStudyController::$limit = 500;
        }
    }

    public function test_prompt_matches_the_language(): void
    {
        $fa = StudyItemFormat::prompt('fa');
        $this->assertStringContainsString('"meaningFa"', $fa);
        $this->assertStringNotContainsString('"meaningAr"', $fa);
        $ar = StudyItemFormat::prompt('ar');
        $this->assertStringContainsString('"meaningAr"', $ar);
        $this->assertStringNotContainsString('"meaningFa"', $ar);
        $both = StudyItemFormat::prompt();
        $this->assertStringContainsString('"pronounceAr"', $both);
        $this->assertStringContainsString('"pronounceFa"', $both);
        $this->assertStringContainsString('ielts-study-items', $both);
    }

    public function test_admin_panel_import_and_create(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListSharedStudyItems::class)->assertSuccessful()
            ->callAction('prompt', ['langs' => 'fa'])
            ->callAction('import', ['json' => self::AI_OUTPUT, 'publish' => true])
            ->assertHasNoActionErrors();
        $this->assertSame(3, SharedStudyItem::count());

        Livewire::test(CreateSharedStudyItem::class)
            ->fillForm([
                'type' => 'tip', 'text' => 'Avoid "very" in Writing',
                'payload' => ['explanationFa' => 'به‌جای very از قیدهای دقیق‌تر…', 'importance' => 3,
                    'examples' => [['en' => 'extremely important', 'fa' => 'بسیار مهم']]],
                'published' => true,
            ])
            ->call('create')->assertHasNoFormErrors();
        $tip = SharedStudyItem::where('key', 'tip|avoid "very" in writing')->firstOrFail();
        $this->assertSame(3, $tip->payload['importance']);
        $this->assertSame('extremely important', $tip->payload['examples'][0]['en']);
    }

    public function test_backup_sync_accepts_tips(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/backup/study/sync', ['items' => [[
            'id' => 't|abc', 'kind' => 'tip', 'text' => 'used to', 'updated' => 5,
            'x' => ['src' => 'user', 'ex' => [['en' => 'I used to swim.']]],
        ]]])->assertOk()->assertJsonPath('items.0.x.ex.0.en', 'I used to swim.');
    }
}
