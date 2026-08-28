<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * How long a script actually runs when you say it out loud.
 *
 * Delivery pace varies enormously — a TikTok voiceover runs at nearly double the
 * pace of a documentary narration — so a single "words per minute" figure is
 * useless. This prices the same script at every pace you might deliver it.
 */
final class ScriptTimerRunner implements Cacheable, ToolRunner
{
    /** @var array<string, array{label: string, wpm: int, note: string}> */
    private const PACES = [
        'shorts' => ['label' => 'Shorts / Reels voiceover', 'wpm' => 180, 'note' => 'Fast, energetic, minimal pauses.'],
        'youtube' => ['label' => 'YouTube talking head', 'wpm' => 150, 'note' => 'Conversational with natural beats.'],
        'podcast' => ['label' => 'Podcast / interview', 'wpm' => 135, 'note' => 'Relaxed, room for asides.'],
        'narration' => ['label' => 'Documentary narration', 'wpm' => 115, 'note' => 'Deliberate, heavy pauses.'],
        'presentation' => ['label' => 'Live presentation', 'wpm' => 105, 'note' => 'Slower than you think, once nerves land.'],
    ];

    public static function key(): string
    {
        return 'content.script-timer';
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
            'required' => ['script'],
            'additionalProperties' => false,
            'properties' => [
                'script' => [
                    'type' => 'string',
                    'title' => 'Your script',
                    'description' => 'Paste the words you will actually say. Stage directions in [brackets] are ignored.',
                    'minLength' => 1,
                    'maxLength' => 100000,
                ],
                'target_seconds' => [
                    'type' => 'integer',
                    'title' => 'Target length (seconds)',
                    'description' => 'Optional. We will tell you how many words to cut or add.',
                    'minimum' => 0,
                    'maximum' => 36000,
                    'default' => 0,
                ],
                'pace' => [
                    'type' => 'string',
                    'title' => 'Your delivery',
                    'enum' => array_keys(self::PACES),
                    'default' => 'youtube',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        // Stage directions are written but never spoken, so they must not be timed.
        $script = preg_replace('/\[[^\]]*\]/u', ' ', $input->string('script')) ?? '';
        $words = count(preg_split('/\s+/u', trim($script), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $pace = $input->string('pace', 'youtube');
        $target = $input->int('target_seconds');

        $rows = [];

        foreach (self::PACES as $key => $option) {
            $seconds = (int) round($words / $option['wpm'] * 60);

            $rows[] = [
                'delivery' => $option['label'].($key === $pace ? ' ★' : ''),
                'wpm' => $option['wpm'].' wpm',
                'runtime' => $this->clock($seconds),
                'note' => $option['note'],
            ];
        }

        $chosen = self::PACES[$pace] ?? self::PACES['youtube'];
        $chosenSeconds = (int) round($words / $chosen['wpm'] * 60);

        $summary = "{$words} spoken words — about ".$this->clock($chosenSeconds)." at {$chosen['wpm']} wpm.";
        $warnings = [];

        if ($target > 0) {
            $targetWords = (int) round($target / 60 * $chosen['wpm']);
            $delta = $words - $targetWords;

            $summary .= $delta > 0
                ? ' Cut about '.$delta.' words to hit your target.'
                : ($delta < 0 ? ' You have room for about '.abs($delta).' more words.' : ' Exactly on target.');

            $warnings[] = "A {$target}-second slot fits roughly {$targetWords} words at this pace.";
        }

        return ToolResult::table(
            columns: [
                ['key' => 'delivery', 'label' => 'Delivery'],
                ['key' => 'wpm', 'label' => 'Pace'],
                ['key' => 'runtime', 'label' => 'Runtime', 'align' => 'right'],
                ['key' => 'note', 'label' => 'Sounds like'],
            ],
            rows: $rows,
            summary: $summary,
        )->withWarnings($warnings)->withMeta(['words' => $words, 'seconds' => $chosenSeconds]);
    }

    private function clock(int $seconds): string
    {
        if ($seconds < 60) {
            return '0:'.str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);
        }

        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }
}
