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
 * Scores a headline or video title and says what to change.
 *
 * A score alone is useless — "62/100" tells nobody what to do. Every section here
 * emits concrete, ordered fixes, and the score exists only to rank which fix to make
 * first.
 */
final class HeadlineAnalyzerRunner implements Cacheable, ToolRunner
{
    /** Words that reliably lift click-through when used sparingly and honestly. */
    private const POWER_WORDS = [
        'proven', 'secret', 'simple', 'ultimate', 'essential', 'complete', 'free',
        'instantly', 'effortless', 'surprising', 'stop', 'never', 'always', 'best',
        'worst', 'mistake', 'mistakes', 'why', 'how', 'now', 'fast', 'easy',
    ];

    private const EMOTIONAL_WORDS = [
        'love', 'hate', 'fear', 'shocking', 'amazing', 'incredible', 'painful',
        'brilliant', 'terrible', 'honest', 'brutal', 'obsessed', 'regret', 'proud',
    ];

    /** Overused phrasing that reads as engagement bait and now suppresses reach. */
    private const CLICKBAIT_PHRASES = [
        'you won\'t believe', 'this one weird', 'doctors hate', 'will blow your mind',
        'number 5 will', 'gone wrong', 'gone sexual', 'what happened next',
    ];

    public static function key(): string
    {
        return 'content.headline-analyzer';
    }

    public function cacheTtl(): int
    {
        return 86400;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['headline'],
            'additionalProperties' => false,
            'properties' => [
                'headline' => [
                    'type' => 'string',
                    'title' => 'Headline or video title',
                    'minLength' => 3,
                    'maxLength' => 300,
                    'examples' => ['How I Grew to 100k Subscribers in 9 Months (Without Going Viral)'],
                ],
                'context' => [
                    'type' => 'string',
                    'title' => 'Where will this be used?',
                    'enum' => ['youtube', 'blog', 'instagram', 'tiktok', 'linkedin', 'email'],
                    'default' => 'youtube',
                    'description' => 'Ideal length differs by surface — YouTube truncates around 60 characters on mobile.',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $headline = trim($input->string('headline'));
        $surface = $input->string('context', 'youtube');

        if ($headline === '') {
            throw ToolExecutionException::invalidInput(
                'Enter a headline to analyse.',
                ['headline' => 'This field cannot be empty.'],
            );
        }

        $words = preg_split('/\s+/u', $headline, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lower = mb_strtolower($headline);

        $sections = [
            $this->lengthSection($headline, $surface),
            $this->structureSection($headline, $words),
            $this->languageSection($lower, $words),
            $this->claritySection($headline, $lower, $words),
        ];

        $overall = (int) round(array_sum(array_map(
            fn (array $s) => $s['score'] * $s['weight'],
            $sections,
        )));

        $fixes = [];

        foreach ($sections as $section) {
            foreach ($section['fixes'] as $fix) {
                $fixes[] = $fix;
            }
        }

        // Show the highest-impact fix first; a list of twenty suggestions is noise.
        usort($fixes, fn (array $a, array $b) => $this->severityRank($b['severity']) <=> $this->severityRank($a['severity']));

        return ToolResult::score(
            overall: $overall,
            sections: array_map(fn (array $s) => [
                'key' => $s['key'],
                'label' => $s['label'],
                'score' => $s['score'],
                'weight' => $s['weight'],
                'notes' => $s['notes'],
            ], $sections),
            fixes: array_slice($fixes, 0, 6),
            summary: $this->summary($overall),
        )->withMeta([
            'character_count' => mb_strlen($headline),
            'word_count' => count($words),
        ]);
    }

    /** @return array<string, mixed> */
    private function lengthSection(string $headline, string $surface): array
    {
        $chars = mb_strlen($headline);

        // Truncation points differ per surface; these are where each one cuts off.
        [$ideal, $max] = match ($surface) {
            'youtube' => [60, 70],
            'blog' => [60, 65],
            'instagram', 'tiktok' => [50, 100],
            'linkedin' => [80, 150],
            'email' => [45, 60],
            default => [60, 70],
        };

        $notes = ["{$chars} characters (ideal: up to {$ideal})"];
        $fixes = [];

        $score = match (true) {
            $chars <= $ideal && $chars >= 20 => 100,
            $chars < 20 => 55,
            $chars <= $max => 80,
            default => max(20, 80 - ($chars - $max) * 2),
        };

        if ($chars > $max) {
            $fixes[] = [
                'severity' => 'high',
                'title' => 'Shorten by '.($chars - $ideal).' characters',
                'detail' => "On {$surface}, anything past ~{$max} characters is truncated — the end of your headline will not be read.",
            ];
        }

        if ($chars < 20) {
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Add specificity',
                'detail' => 'Very short headlines rarely carry enough information to earn a click. Add a number, a timeframe, or an outcome.',
            ];
        }

        return ['key' => 'length', 'label' => 'Length', 'score' => (int) $score, 'weight' => 0.25, 'notes' => $notes, 'fixes' => $fixes];
    }

    /** @return array<string, mixed> */
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function structureSection(string $headline, array $words): array
    {
        $score = 55;
        $notes = [];
        $fixes = [];

        if (preg_match('/\d/', $headline) === 1) {
            $score += 20;
            $notes[] = 'Contains a number — these consistently outperform.';
        } else {
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Add a specific number',
                'detail' => 'A concrete figure ("in 9 months", "3 mistakes", "$4,200") makes a promise measurable and raises click-through.',
            ];
        }

        if (preg_match('/\(([^)]{2,30})\)|\[[^\]]{2,30}\]/u', $headline) === 1) {
            $score += 15;
            $notes[] = 'Uses a bracketed qualifier — a reliable curiosity device.';
        }

        $wordCount = count($words);
        if ($wordCount >= 6 && $wordCount <= 12) {
            $score += 10;
            $notes[] = "{$wordCount} words — in the sweet spot.";
        } else {
            $notes[] = "{$wordCount} words.";
        }

        return ['key' => 'structure', 'label' => 'Structure', 'score' => min(100, $score), 'weight' => 0.25, 'notes' => $notes, 'fixes' => $fixes];
    }

    /** @return array<string, mixed> */
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function languageSection(string $lower, array $words): array
    {
        $power = array_values(array_intersect(self::POWER_WORDS, array_map(
            fn (string $w) => mb_strtolower(trim($w, ".,!?:;\"'()")),
            $words,
        )));

        $emotional = array_values(array_filter(
            self::EMOTIONAL_WORDS,
            fn (string $w) => str_contains($lower, $w),
        ));

        $score = 50 + min(30, count($power) * 12) + min(20, count($emotional) * 12);
        $notes = [];
        $fixes = [];

        $notes[] = $power === []
            ? 'No power words detected.'
            : 'Power words: '.implode(', ', $power).'.';

        if ($emotional !== []) {
            $notes[] = 'Emotional language: '.implode(', ', $emotional).'.';
        }

        if (count($power) > 4) {
            // Stacking them reads as spam and undercuts every individual word.
            $score -= 25;
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Reduce power-word stacking',
                'detail' => 'Four or more in one headline reads as marketing copy rather than a real promise. Keep the strongest one or two.',
            ];
        } elseif ($power === [] && $emotional === []) {
            $fixes[] = [
                'severity' => 'low',
                'title' => 'Add one emotional or power word',
                'detail' => 'A single well-placed word ("mistake", "honest", "finally") gives a factual headline a reason to be clicked now.',
            ];
        }

        return ['key' => 'language', 'label' => 'Language', 'score' => max(0, min(100, $score)), 'weight' => 0.25, 'notes' => $notes, 'fixes' => $fixes];
    }

    /** @return array<string, mixed> */
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function claritySection(string $headline, string $lower, array $words): array
    {
        $score = 100;
        $notes = [];
        $fixes = [];

        foreach (self::CLICKBAIT_PHRASES as $phrase) {
            if (str_contains($lower, $phrase)) {
                $score -= 35;
                $fixes[] = [
                    'severity' => 'high',
                    'title' => "Remove \"{$phrase}\"",
                    'detail' => 'Recognised engagement bait. Platforms actively suppress it and audiences have learned to distrust it.',
                ];
                break;
            }
        }

        $upperWords = array_filter($words, fn (string $w) => mb_strlen($w) > 2 && $w === mb_strtoupper($w));

        if (count($upperWords) > 2) {
            $score -= 20;
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Reduce ALL CAPS',
                'detail' => 'More than two shouted words reads as spam. Cap one word at most, for genuine emphasis.',
            ];
        }

        $exclamations = substr_count($headline, '!');

        if ($exclamations > 1) {
            $score -= 15;
            $fixes[] = [
                'severity' => 'low',
                'title' => 'Use at most one exclamation mark',
                'detail' => 'Multiple exclamation marks lower perceived credibility.',
            ];
        }

        if ($fixes === []) {
            $notes[] = 'No clickbait patterns, shouting or punctuation spam detected.';
        }

        return ['key' => 'clarity', 'label' => 'Clarity & trust', 'score' => max(0, $score), 'weight' => 0.25, 'notes' => $notes, 'fixes' => $fixes];
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    private function summary(int $score): string
    {
        return match (true) {
            $score >= 85 => "Strong headline ({$score}/100). Ship it.",
            $score >= 70 => "Good headline ({$score}/100) with room for one or two easy wins.",
            $score >= 50 => "Workable ({$score}/100), but the fixes below will make a real difference.",
            default => "This headline needs work ({$score}/100). Start with the first fix below.",
        };
    }
}
