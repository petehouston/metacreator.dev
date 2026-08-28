<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\PostLength;

/**
 * One count, every platform's limits, with the truncation point for each.
 *
 * The competing free tools count characters with `length` and are wrong for anyone
 * writing in Japanese, using emoji, or including a link. This uses each platform's
 * real counting rule (see {@see PostLength}).
 */
final class CharacterCounterRunner implements Cacheable, ToolRunner
{
    /**
     * @var array<string, array{label: string, limit: int, truncates_at: int|null, weighted: bool}>
     */
    private const SURFACES = [
        'x_post' => ['label' => 'X post', 'limit' => 280, 'truncates_at' => null, 'weighted' => true],
        'x_premium' => ['label' => 'X post (Premium)', 'limit' => 25000, 'truncates_at' => 280, 'weighted' => true],
        'instagram_caption' => ['label' => 'Instagram caption', 'limit' => 2200, 'truncates_at' => 125, 'weighted' => false],
        'instagram_bio' => ['label' => 'Instagram bio', 'limit' => 150, 'truncates_at' => null, 'weighted' => false],
        'tiktok_caption' => ['label' => 'TikTok caption', 'limit' => 2200, 'truncates_at' => 90, 'weighted' => false],
        'youtube_title' => ['label' => 'YouTube title', 'limit' => 100, 'truncates_at' => 60, 'weighted' => false],
        'youtube_description' => ['label' => 'YouTube description', 'limit' => 5000, 'truncates_at' => 157, 'weighted' => false],
        'linkedin_post' => ['label' => 'LinkedIn post', 'limit' => 3000, 'truncates_at' => 210, 'weighted' => false],
        'facebook_post' => ['label' => 'Facebook post', 'limit' => 63206, 'truncates_at' => 477, 'weighted' => false],
        'threads_post' => ['label' => 'Threads post', 'limit' => 500, 'truncates_at' => 280, 'weighted' => false],
        'pinterest_title' => ['label' => 'Pinterest Pin title', 'limit' => 100, 'truncates_at' => 40, 'weighted' => false],
        'pinterest_description' => ['label' => 'Pinterest Pin description', 'limit' => 500, 'truncates_at' => 50, 'weighted' => false],
        'meta_description' => ['label' => 'SEO meta description', 'limit' => 160, 'truncates_at' => 155, 'weighted' => false],
    ];

    public static function key(): string
    {
        return 'content.character-counter';
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
                    'description' => 'Counts update as you type. Emoji count as one character; links count as 23 on X.',
                    'minLength' => 0,
                    'maxLength' => 70000,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $text = $input->string('text');

        $graphemes = PostLength::graphemeCount($text);
        $weighted = PostLength::weighted($text);
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $lines = $text === '' ? 0 : substr_count($text, "\n") + 1;

        $rows = [];

        foreach (self::SURFACES as $key => $surface) {
            $count = $surface['weighted'] ? $weighted : $graphemes;
            $remaining = $surface['limit'] - $count;

            $rows[] = [
                'platform' => $surface['label'],
                'used' => $count.' / '.number_format($surface['limit']),
                'remaining' => $remaining >= 0 ? number_format($remaining).' left' : number_format(abs($remaining)).' over',
                'fold' => $surface['truncates_at'] !== null
                    ? ($count > $surface['truncates_at']
                        ? 'Cut off at '.$surface['truncates_at']
                        : 'Fully visible')
                    : '—',
                'status' => $remaining >= 0 ? 'ok' : 'over',
                '_key' => $key,
            ];
        }

        $overLimit = array_values(array_filter($rows, fn (array $r) => $r['status'] === 'over'));

        return ToolResult::table(
            columns: [
                ['key' => 'platform', 'label' => 'Platform'],
                ['key' => 'used', 'label' => 'Characters'],
                ['key' => 'remaining', 'label' => 'Remaining', 'align' => 'right'],
                ['key' => 'fold', 'label' => 'Before "see more"'],
            ],
            rows: $rows,
            summary: $overLimit === []
                ? "{$graphemes} characters · {$words} words — fits everywhere."
                : "{$graphemes} characters · too long for ".count($overLimit).' platform(s).',
        )->withMeta([
            'characters' => $graphemes,
            'characters_weighted' => $weighted,
            'words' => $words,
            'lines' => $lines,
            'reading_seconds' => (int) ceil($words / 3.5),
        ]);
    }
}
