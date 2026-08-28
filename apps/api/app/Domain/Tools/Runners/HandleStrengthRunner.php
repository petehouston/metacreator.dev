<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * Whether a handle will survive being said out loud, typed, and searched for.
 *
 * A handle is a brand asset that has to work spoken on a podcast, typed from
 * memory, and matched in search. Numbers, underscores and doubled letters break at
 * least one of those, which is what this scores.
 */
final class HandleStrengthRunner implements Cacheable, ToolRunner
{
    /** Per-platform maximum handle length. */
    private const LIMITS = [
        'Instagram' => 30,
        'TikTok' => 24,
        'X' => 15,
        'YouTube' => 30,
        'LinkedIn' => 100,
    ];

    public static function key(): string
    {
        return 'utility.handle-strength';
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
            'required' => ['handle'],
            'additionalProperties' => false,
            'properties' => [
                'handle' => [
                    'type' => 'string',
                    'title' => 'The handle you are considering',
                    'minLength' => 1,
                    'maxLength' => 100,
                    'examples' => ['thebreadlab'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $handle = ltrim(trim($input->string('handle')), '@');
        $length = mb_strlen($handle);
        $lower = mb_strtolower($handle);

        $sections = [];
        $fixes = [];

        // Length: X's 15-character cap is the binding constraint everywhere.
        $lengthScore = match (true) {
            $length === 0 => 0,
            $length <= 15 => 100,
            $length <= 24 => 70,
            $length <= 30 => 45,
            default => 20,
        };

        $tooLongFor = array_keys(array_filter(self::LIMITS, fn (int $limit) => $length > $limit));

        $sections[] = ['key' => 'length', 'label' => 'Length', 'score' => $lengthScore, 'weight' => 0.3,
            'notes' => ["{$length} characters", $tooLongFor === []
                ? 'Fits on every major platform'
                : 'Too long for: '.implode(', ', $tooLongFor)]];

        if ($tooLongFor !== []) {
            $fixes[] = ['severity' => 'high', 'title' => 'Shorten to 15 characters',
                'detail' => 'X caps handles at 15, so anything longer forces a different handle there — '
                    .'and a handle that differs per platform is much harder to promote.'];
        }

        // Typability: the things that make a handle hard to dictate.
        $hasDigits = preg_match('/\d/', $handle) === 1;
        $hasUnderscore = str_contains($handle, '_');
        $hasDots = str_contains($handle, '.');
        $hasDoubles = preg_match('/(.)\1/u', $lower) === 1;
        $mixedCase = $handle !== $lower && $handle !== mb_strtoupper($handle);

        $typeScore = 100 - ($hasDigits ? 25 : 0) - ($hasUnderscore ? 20 : 0)
            - ($hasDots ? 10 : 0) - ($hasDoubles ? 10 : 0);

        $typeNotes = [];
        $hasDigits && $typeNotes[] = 'Contains digits — “is that the number or the word?”';
        $hasUnderscore && $typeNotes[] = 'Contains an underscore — invisible when underlined as a link';
        $hasDots && $typeNotes[] = 'Contains a dot';
        $hasDoubles && $typeNotes[] = 'Has a doubled letter, a common typo source';
        $typeNotes === [] && $typeNotes[] = 'Clean — letters only';

        $sections[] = ['key' => 'typability', 'label' => 'Say it out loud', 'score' => max(0, $typeScore),
            'weight' => 0.4, 'notes' => $typeNotes];

        if ($hasDigits || $hasUnderscore) {
            $fixes[] = ['severity' => 'medium', 'title' => 'Drop the digits and underscores',
                'detail' => 'You will read this handle aloud hundreds of times. Every separator is a '
                    .'chance for someone to land on the wrong account.'];
        }

        // Memorability: word count and whether it reads as something.
        $wordish = preg_match_all('/[bcdfghjklmnpqrstvwxyz]{4,}/i', $lower);
        $memoryScore = 100 - ($wordish * 25) - ($length > 20 ? 20 : 0) - ($mixedCase ? 10 : 0);

        $sections[] = ['key' => 'memorability', 'label' => 'Memorability', 'score' => max(0, min(100, $memoryScore)),
            'weight' => 0.3, 'notes' => [
                $wordish > 0 ? 'Contains hard-to-pronounce consonant runs' : 'Pronounceable',
                $mixedCase ? 'Mixed case is lost in most places handles are displayed' : 'Case-safe',
            ]];

        $overall = (int) round(array_sum(array_map(fn (array $s) => $s['score'] * $s['weight'], $sections)));

        return ToolResult::score(
            overall: $overall,
            sections: $sections,
            fixes: $fixes,
            summary: match (true) {
                $overall >= 85 => "“@{$handle}” is a strong handle — short, clean and easy to dictate.",
                $overall >= 65 => "“@{$handle}” works, with one or two things worth fixing before you commit.",
                default => "“@{$handle}” will cause friction every time you say it out loud.",
            },
        )->withWarnings([
            'This scores the handle itself, not whether it is available. Check each platform before you commit.',
        ]);
    }
}
