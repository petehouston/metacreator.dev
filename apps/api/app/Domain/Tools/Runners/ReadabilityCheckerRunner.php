<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;

/**
 * Flesch reading ease and friends, with the sentences that caused the score.
 *
 * A grade level on its own changes nothing. What changes the writing is being shown
 * the three specific sentences that are too long, so the fixes list names them.
 */
final class ReadabilityCheckerRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'content.readability-checker';
    }

    public function cacheTtl(): int
    {
        return 3600;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['text'],
            'additionalProperties' => false,
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'title' => 'Your text',
                    'description' => 'A caption, a script, a newsletter — anything longer than a sentence or two.',
                    'minLength' => 20,
                    'maxLength' => 50000,
                ],
                'audience' => [
                    'type' => 'string',
                    'title' => 'Written for',
                    'enum' => ['general', 'social', 'technical'],
                    'default' => 'general',
                    'description' => 'Sets the grade level we score you against.',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $text = trim($input->string('text'));
        $audience = $input->string('audience', 'general');

        $sentences = $this->sentences($text);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($sentences === [] || $words === []) {
            throw ToolExecutionException::invalidInput('Add at least one full sentence to score.');
        }

        $syllables = array_sum(array_map(fn (string $w) => $this->syllables($w), $words));
        $wordsPerSentence = count($words) / count($sentences);
        $syllablesPerWord = $syllables / count($words);

        $ease = round(206.835 - 1.015 * $wordsPerSentence - 84.6 * $syllablesPerWord, 1);
        $grade = round(0.39 * $wordsPerSentence + 11.8 * $syllablesPerWord - 15.59, 1);

        // The target grade differs by audience: a caption read on a phone should be
        // easier than documentation someone sat down to read.
        $targetGrade = match ($audience) {
            'social' => 6.0,
            'technical' => 12.0,
            default => 9.0,
        };

        $long = array_values(array_filter(
            $sentences,
            fn (string $s) => count(preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: []) > 25,
        ));

        $complex = array_values(array_unique(array_filter(
            array_map(fn (string $w) => trim($w, ".,!?;:\"'()[]"), $words),
            fn (string $w) => $this->syllables($w) >= 4 && mb_strlen($w) > 8,
        )));

        $sections = [
            [
                'key' => 'ease',
                'label' => 'Reading ease',
                'score' => (int) max(0, min(100, $ease)),
                'weight' => 0.4,
                'notes' => ["Flesch reading ease {$ease} — ".$this->easeBand((float) $ease)],
            ],
            [
                'key' => 'grade',
                'label' => 'Grade level',
                'score' => (int) max(0, min(100, round(100 - max(0, $grade - $targetGrade) * 10))),
                'weight' => 0.3,
                'notes' => ["Flesch–Kincaid grade {$grade} (target for this audience: {$targetGrade})"],
            ],
            [
                'key' => 'sentences',
                'label' => 'Sentence length',
                'score' => (int) max(0, min(100, round(100 - max(0, $wordsPerSentence - 18) * 5))),
                'weight' => 0.2,
                'notes' => [
                    round($wordsPerSentence, 1).' words per sentence on average',
                    count($long).' sentence(s) over 25 words',
                ],
            ],
            [
                'key' => 'vocabulary',
                'label' => 'Vocabulary',
                'score' => (int) max(0, min(100, round(100 - (count($complex) / max(1, count($words))) * 400))),
                'weight' => 0.1,
                'notes' => [count($complex).' long or multi-syllable words'],
            ],
        ];

        $overall = (int) round(array_sum(array_map(fn (array $s) => $s['score'] * $s['weight'], $sections)));

        $fixes = [];

        if ($long !== []) {
            $fixes[] = [
                'severity' => 'high',
                'title' => 'Split '.count($long).' long sentence(s)',
                'detail' => 'Longest offender: “'.$this->truncate($long[0]).'” — break it at the first conjunction.',
            ];
        }

        if ($grade > $targetGrade + 2) {
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Simplify the wording',
                'detail' => "This reads at grade {$grade}; your audience wants around {$targetGrade}. "
                    .'Shorter sentences move the grade faster than shorter words.',
            ];
        }

        if ($complex !== []) {
            $fixes[] = [
                'severity' => 'low',
                'title' => 'Swap a few long words',
                'detail' => 'For example: '.implode(', ', array_slice($complex, 0, 5)).'.',
            ];
        }

        return ToolResult::score(
            overall: $overall,
            sections: $sections,
            fixes: $fixes,
            summary: "Flesch reading ease {$ease} — ".$this->easeBand((float) $ease)
                .". Reads at about grade {$grade}.",
        )->withMeta([
            'flesch_reading_ease' => $ease,
            'flesch_kincaid_grade' => $grade,
            'words' => count($words),
            'sentences' => count($sentences),
        ]);
    }

    /** @return list<string> */
    private function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $s) => $s !== ''));
    }

    /**
     * Vowel-group syllable estimate.
     *
     * Not exact — no purely algorithmic counter is — but consistently within a few
     * percent across a paragraph, which is all the Flesch formulas need.
     */
    private function syllables(string $word): int
    {
        $word = mb_strtolower(preg_replace('/[^a-zA-Z]/', '', $word) ?? '');

        if ($word === '') {
            return 0;
        }

        if (mb_strlen($word) <= 3) {
            return 1;
        }

        $word = preg_replace('/(?:es|ed|[^aeiouy]e)$/', '', $word) ?? $word;
        $groups = preg_match_all('/[aeiouy]+/', $word);

        return max(1, (int) $groups);
    }

    private function easeBand(float $ease): string
    {
        return match (true) {
            $ease >= 80 => 'very easy, the level of a popular caption',
            $ease >= 60 => 'plain English, comfortable for most readers',
            $ease >= 50 => 'fairly hard, the level of a broadsheet feature',
            $ease >= 30 => 'difficult, academic register',
            default => 'very difficult — most readers will bounce',
        };
    }

    private function truncate(string $sentence): string
    {
        return mb_strlen($sentence) > 90 ? mb_substr($sentence, 0, 90).'…' : $sentence;
    }
}
