<?php

namespace App\Support;

/**
 * 🌟 ST-7: قالبِ یکسانِ «موردِ مرور» برای واژه‌ها و نکته‌های بیرون از کتاب‌ها.
 *
 * همان قالبی است که پرامپتِ هوش مصنوعی (prompt()) تولید می‌کند، پنلِ ادمین («مرورِ آماده»
 * ← واردکردن) می‌خواند، و اپ هم مستقیم می‌خواند (study_item_format.dart). اگر چیزی این‌جا
 * عوض شد، همان‌جا هم عوض شود. سند: STUDY-ITEMS.md در مخزنِ اپ.
 *
 *   { "format": "ielts-study-items", "version": 1, "items": [ {...}, ... ] }
 *
 * هر مورد:
 *   type          word | tip   (phrase/idiom/collocation ← word؛ grammar/note ← tip)
 *   text          واژه/عبارت به شکلِ پایه، یا عنوانِ کوتاهِ نکته (انگلیسی) — اجباری
 *   pos, ipa, cefr
 *   meaningFa, meaningAr, pronounceFa, pronounceAr, explanationFa, explanationAr
 *   examples      [ {en, fa, ar}, ... ]  (یا فقط رشته‌ی انگلیسی)
 *   tags          [ "Writing", ... ]
 *   importance    1 | 2 | 3
 *   source        از کجا آمده (اختیاری)
 */
class StudyItemFormat
{
    public const FORMAT = 'ielts-study-items';

    public const VERSION = 1;

    private const TEXT_FIELDS = [
        'pos' => 60, 'ipa' => 120, 'cefr' => 4,
        'meaningFa' => 500, 'meaningAr' => 500,
        'pronounceFa' => 200, 'pronounceAr' => 200,
        'explanationFa' => 4000, 'explanationAr' => 4000,
        'source' => 200,
    ];

    private const TYPE_ALIASES = [
        'word' => 'word', 'phrase' => 'word', 'idiom' => 'word', 'collocation' => 'word',
        'phrasal verb' => 'word', 'phrasal_verb' => 'word', 'vocabulary' => 'word', 'expression' => 'word',
        'tip' => 'tip', 'note' => 'tip', 'grammar' => 'tip', 'rule' => 'tip', 'strategy' => 'tip',
    ];

    /** کلیدِ یکتای مورد (برای جلوگیری از تکرار): نوع + متنِ کوچک‌شده با فاصله‌های یکسان. */
    public static function key(string $type, string $text): string
    {
        return $type.'|'.mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    }

    /**
     * یک مورد را تمیز می‌کند. null یعنی قابلِ استفاده نیست ($error دلیل را می‌گوید).
     *
     * @return array<string,mixed>|null
     */
    public static function normalize(mixed $raw, ?string &$error = null): ?array
    {
        $error = null;
        if (! is_array($raw)) {
            $error = 'مورد باید یک شیء باشد.';

            return null;
        }
        $type = self::TYPE_ALIASES[mb_strtolower(trim((string) ($raw['type'] ?? 'word')))] ?? null;
        if ($type === null) {
            $error = 'نوعِ «'.($raw['type'] ?? '').'» شناخته نیست (word یا tip).';

            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) ($raw['text'] ?? $raw['word'] ?? $raw['title'] ?? '')));
        if ($text === '') {
            $error = 'فیلدِ text خالی است.';

            return null;
        }
        if (mb_strlen($text) > 300) {
            $error = 'text بیش از ۳۰۰ حرف است: «'.mb_substr($text, 0, 40).'…»';

            return null;
        }

        $out = ['type' => $type, 'text' => $text];
        // نام‌های دیگری که هوش مصنوعی‌ها گاهی می‌سازند.
        $aliases = ['meaningFa' => ['fa', 'translationFa'], 'meaningAr' => ['ar', 'translationAr']];
        foreach (self::TEXT_FIELDS as $field => $max) {
            $value = $raw[$field] ?? null;
            foreach ($aliases[$field] ?? [] as $alt) {
                $value ??= $raw[$alt] ?? null;
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                $out[$field] = mb_substr(trim((string) $value), 0, $max);
            }
        }
        if (isset($out['cefr'])) {
            $out['cefr'] = strtoupper($out['cefr']);
            if (! preg_match('/^(A1|A2|B1|B2|C1|C2)$/', $out['cefr'])) {
                unset($out['cefr']);
            }
        }

        $examples = [];
        foreach (array_slice((array) ($raw['examples'] ?? []), 0, 6) as $ex) {
            if (is_string($ex)) {
                $ex = ['en' => $ex];
            }
            if (! is_array($ex) || trim((string) ($ex['en'] ?? '')) === '') {
                continue;
            }
            $examples[] = array_filter([
                'en' => mb_substr(trim((string) $ex['en']), 0, 600),
                'fa' => isset($ex['fa']) ? mb_substr(trim((string) $ex['fa']), 0, 600) : null,
                'ar' => isset($ex['ar']) ? mb_substr(trim((string) $ex['ar']), 0, 600) : null,
            ], fn ($v) => $v !== null && $v !== '');
        }
        if ($examples) {
            $out['examples'] = $examples;
        }

        $tags = [];
        foreach (array_slice((array) ($raw['tags'] ?? []), 0, 8) as $tag) {
            if (is_scalar($tag) && trim((string) $tag) !== '') {
                $tags[] = mb_substr(trim((string) $tag), 0, 40);
            }
        }
        if ($tags) {
            $out['tags'] = array_values(array_unique($tags));
        }

        $imp = (int) ($raw['importance'] ?? 2);
        $out['importance'] = max(1, min(3, $imp ?: 2));

        return $out;
    }

    /**
     * متنِ کامل (خروجیِ هوش مصنوعی، با یا بدونِ ```json) ← موردهای سالم + خطاها.
     *
     * @return array{items: list<array<string,mixed>>, errors: list<string>}
     */
    public static function parse(string $raw): array
    {
        $raw = trim($raw);
        // ```json … ``` و متنِ اضافه پیش/پس از JSON
        $raw = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/m', '', $raw);
        $start = strcspn($raw, '{[');
        $raw = substr($raw, $start);
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $end = max(strrpos($raw, '}') ?: 0, strrpos($raw, ']') ?: 0);
            $data = $end ? json_decode(substr($raw, 0, $end + 1), true) : null;
        }
        if (! is_array($data)) {
            return ['items' => [], 'errors' => ['متن JSONِ معتبر نیست ('.json_last_error_msg().').']];
        }

        $list = match (true) {
            isset($data['items']) && is_array($data['items']) => $data['items'],
            array_is_list($data) => $data,
            default => [$data],
        };

        $items = [];
        $errors = [];
        $seen = [];
        foreach ($list as $i => $one) {
            $item = self::normalize($one, $error);
            if (! $item) {
                $errors[] = 'مورد '.($i + 1).': '.$error;

                continue;
            }
            $k = self::key($item['type'], $item['text']);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $items[] = $item;
        }

        return ['items' => $items, 'errors' => $errors];
    }

    /**
     * پرامپتِ هوش مصنوعی. $langs: fa | ar | both — زبانِ توضیح‌ها.
     * همان متنِ studyItemsPrompt() در اپ (study_item_format.dart)؛ هر تغییری هر دو جا.
     */
    public static function prompt(string $langs = 'both'): string
    {
        $fa = $langs !== 'ar';
        $ar = $langs !== 'fa';
        $l = fn (string $faPart, string $arPart, string $sep = ', ') => implode($sep, array_filter([
            $fa ? $faPart : null, $ar ? $arPart : null,
        ]));
        $ex = fn (string $en, string $faT, string $arT) => '{"en":"'.$en.'",'
            .$l('"fa":"'.$faT.'"', '"ar":"'.$arT.'"', ',').'}';

        $langLine = match ($langs) {
            'fa' => 'Persian (Farsi) only — do not add any Arabic field',
            'ar' => 'Arabic only — do not add any Persian field',
            default => 'BOTH Persian (Farsi) and Arabic — fill every Fa field and its Ar twin',
        };

        $word = "{\"type\":\"word\",\n"
            ." \"text\":\"mitigate\",\n"
            ." \"pos\":\"verb\",\n"
            ." \"ipa\":\"/ˈmɪt.ɪ.ɡeɪt/\",\n"
            ." \"cefr\":\"C1\",\n"
            .' '.$l('"meaningFa":"کاهش دادن، تخفیف دادن (شدت یا اثرِ چیزی)"', '"meaningAr":"يُخفِّف، يُقلِّل من حدّة"').",\n"
            .' '.$l('"pronounceFa":"میتیگِیت"', '"pronounceAr":"ميتيغيت"').",\n"
            .' '.$l('"explanationFa":"…"', '"explanationAr":"…"').",\n"
            .' "examples":['.$ex('Planting trees can mitigate the effects of air pollution.', '…', '…')."],\n"
            ." \"tags\":[\"Writing\",\"Environment\"],\n"
            ." \"importance\":2}";
        $tip = "{\"type\":\"tip\",\n"
            ." \"text\":\"used to + verb vs. be used to + verb-ing\",\n"
            .' '.$l('"explanationFa":"…"', '"explanationAr":"…"').",\n"
            .' "examples":['.$ex('I used to live in Shiraz.', '…', '…').','.$ex('I am used to getting up early.', '…', '…')."],\n"
            ." \"tags\":[\"Grammar\"],\n"
            ." \"importance\":3}";
        $pron = $l('"pronounceFa": how the English word sounds, written in Persian letters', '"pronounceAr": how the English word sounds, written in Arabic letters', '; ');
        $exLangs = $l('Persian', 'Arabic', ' and ');

        return <<<PROMPT
You are an expert IELTS teacher. Turn the material at the end of this message (notes, a language channel post, another AI's answer, a word list, or just a question) into review items for a study app.

Output ONLY valid JSON — no explanation before or after, no Markdown code fence, no comments — in exactly this shape:

{"format":"ielts-study-items","version":1,"items":[ ... ]}

Each item is one of two types.

1) A word, phrase, phrasal verb, idiom or collocation:
{$word}

2) A tip: a grammar point, a writing/speaking technique, a common mistake, a pronunciation point or an exam strategy:
{$tip}

Rules:
- Explanation language: {$langLine}.
- "text": English. For words use the base/dictionary form (verbs without "to"); for tips a short English title or pattern.
- "pos": noun, verb, adjective, adverb, phrasal verb, idiom, collocation, phrase, linking phrase …
- "ipa": British pronunciation between slashes. "cefr": A1, A2, B1, B2, C1 or C2.
- {$pron}.
- "meaning…": the meaning(s) used in the material, short. "explanation…": short and practical — usage, register (formal/informal), difference from similar words, common mistakes, why it matters for IELTS. Tips may be several lines.
- "examples": 1–3 natural English sentences, preferably in IELTS contexts, each with its {$exLangs} translation.
- "tags": 1–3 of: Reading, Listening, Writing, Speaking, Grammar, Vocabulary, Pronunciation, plus a topic if useful (Environment, Education, Technology, Health …).
- "importance": 1 = useful, 2 = important, 3 = essential for IELTS.
- One item per word or tip; no duplicates. Correct any spelling or factual mistakes in the material instead of copying them.
- If the material is only a list of words or a question, complete every field yourself, accurately.
- Leave out a field you are not sure about instead of guessing; never output empty strings.

Material:
<<<
(paste your text here)
>>>
PROMPT;
    }
}
