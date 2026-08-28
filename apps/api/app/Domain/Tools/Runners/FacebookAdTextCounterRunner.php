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
 * The three ad fields, checked against the length Meta actually shows.
 *
 * Ads Manager accepts far more text than any placement displays. The recommended
 * lengths below are the display limits, which is what decides whether your offer is
 * legible in the feed.
 */
final class FacebookAdTextCounterRunner implements Cacheable, ToolRunner
{
    /** @var array<string, array{label: string, recommended: int, hard: int, note: string}> */
    private const FIELDS = [
        'primary_text' => ['label' => 'Primary text', 'recommended' => 125, 'hard' => 3000,
            'note' => 'Truncated with “See more” past 125 characters on mobile.'],
        'headline' => ['label' => 'Headline', 'recommended' => 27, 'hard' => 255,
            'note' => 'Anything past ~27 characters is cut on most placements.'],
        'description' => ['label' => 'Link description', 'recommended' => 27, 'hard' => 255,
            'note' => 'Often hidden entirely on mobile placements.'],
    ];

    public static function key(): string
    {
        return 'facebook.ad-text-counter';
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
            'required' => ['primary_text'],
            'additionalProperties' => false,
            'properties' => [
                'primary_text' => [
                    'type' => 'string',
                    'title' => 'Primary text',
                    'description' => 'The body copy above the creative.',
                    'maxLength' => 3000,
                ],
                'headline' => [
                    'type' => 'string',
                    'title' => 'Headline',
                    'maxLength' => 255,
                    'default' => '',
                ],
                'description' => [
                    'type' => 'string',
                    'title' => 'Link description',
                    'maxLength' => 255,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $rows = [];
        $overRecommended = 0;

        foreach (self::FIELDS as $key => $field) {
            $value = $input->string($key);
            $count = PostLength::graphemeCount($value);

            if ($count > $field['recommended']) {
                $overRecommended++;
            }

            $rows[] = [
                'field' => $field['label'],
                'count' => $count.' / '.$field['recommended'],
                'shown' => $count > $field['recommended']
                    ? mb_substr($value, 0, $field['recommended']).'…'
                    : ($value === '' ? '—' : $value),
                'status' => match (true) {
                    $count > $field['hard'] => 'Over the hard limit',
                    $count > $field['recommended'] => 'Will be truncated',
                    default => 'Fits',
                },
                'note' => $field['note'],
            ];
        }

        return ToolResult::table(
            columns: [
                ['key' => 'field', 'label' => 'Field'],
                ['key' => 'count', 'label' => 'Characters', 'align' => 'right'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'shown', 'label' => 'What is displayed'],
            ],
            rows: $rows,
            summary: $overRecommended === 0
                ? 'All three fields display in full on every placement.'
                : "{$overRecommended} field(s) will be truncated on mobile placements.",
        )->withWarnings([
            'Recommended lengths are display limits, not what Ads Manager will accept — it accepts '
            .'much more and then cuts it in the feed.',
        ]);
    }
}
