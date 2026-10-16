<?php

namespace App\Models;

use App\Support\StudyItemFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 🌟 ST-7: یک موردِ «مرورِ آماده» (واژه یا نکته) که ادمین برای همه‌ی کاربران گذاشته است.
 *
 * - payload = همان قالبِ یکسان (StudyItemFormat)؛ type و text ستونِ جدا هم دارند (جستجو،
 *   یکتایی). هوکِ saving این‌ها را هماهنگ و payload را تمیز می‌کند.
 * - changed_ms با هر ذخیره، حذف و بازگردانی زیاد می‌شود؛ اپ‌ها با GET /api/study/shared?since=
 *   فقط تغییرها را می‌گیرند. حذف نرم است تا اپ‌ها بفهمند موردی برداشته شده.
 */
class SharedStudyItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['type', 'text', 'payload', 'published'];

    protected $casts = [
        'payload' => 'array',
        'published' => 'boolean',
        'changed_ms' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $raw = array_merge((array) $item->payload, array_filter([
                'type' => $item->type,
                'text' => $item->text,
            ]));
            $clean = StudyItemFormat::normalize($raw, $error);
            if ($clean === null) {
                throw new \InvalidArgumentException($error ?? 'موردِ نامعتبر');
            }
            $item->type = $clean['type'];
            $item->text = $clean['text'];
            $item->key = StudyItemFormat::key($clean['type'], $clean['text']);
            $item->payload = $clean;
            $item->changed_ms = self::nextChangedMs();
        });
        $touch = fn (self $item) => $item->forceFill(['changed_ms' => self::nextChangedMs()])->saveQuietly();
        static::deleted($touch);
        static::restored($touch);
    }

    /** یکتا و صعودی، حتی وقتی ده‌ها مورد در یک میلی‌ثانیه وارد می‌شوند. */
    public static function nextChangedMs(): int
    {
        $now = (int) floor(microtime(true) * 1000);
        $max = (int) static::withTrashed()->max('changed_ms');

        return max($now, $max + 1);
    }

    /** شکلِ خوراکِ اپ: موردِ برداشته‌شده فقط شناسه و deleted دارد. */
    public function toFeedArray(): array
    {
        $gone = $this->trashed() || ! $this->published;

        return [
            'sid' => $this->id,
            'changed' => $this->changed_ms,
            'deleted' => $gone,
            'item' => $gone ? null : $this->payload,
        ];
    }

    /**
     * ورودِ گروهی از قالبِ یکسان: موردِ تازه ساخته، موردِ موجود (همان نوع و متن) به‌روز و اگر
     * حذف شده بود برگردانده می‌شود.
     *
     * @return array{added:int, updated:int, errors:list<string>}
     */
    public static function import(string $json, bool $publish = true): array
    {
        $parsed = StudyItemFormat::parse($json);
        $added = 0;
        $updated = 0;
        foreach ($parsed['items'] as $item) {
            $existing = static::withTrashed()->where('key', StudyItemFormat::key($item['type'], $item['text']))->first();
            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->fill(['payload' => $item, 'published' => $publish])->save();
                $updated++;
            } else {
                static::create([
                    'type' => $item['type'], 'text' => $item['text'], 'payload' => $item, 'published' => $publish,
                ]);
                $added++;
            }
        }

        return ['added' => $added, 'updated' => $updated, 'errors' => $parsed['errors']];
    }
}
