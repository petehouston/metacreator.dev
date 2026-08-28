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
 * When you hit the next milestone at your current pace.
 *
 * Straight-line projection, deliberately: growth-curve models built on two numbers
 * are false precision. The value is the date, and the honest "if nothing changes"
 * caveat attached to it.
 */
final class FollowerMilestoneCountdownRunner implements Cacheable, ToolRunner
{
    /** The milestones people actually celebrate. */
    private const MILESTONES = [1000, 5000, 10000, 25000, 50000, 100000, 250000, 500000, 1000000, 5000000, 10000000];

    public static function key(): string
    {
        return 'utility.milestone-countdown';
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
            'required' => ['current', 'weekly_growth'],
            'additionalProperties' => false,
            'properties' => [
                'current' => [
                    'type' => 'integer',
                    'title' => 'Followers now',
                    'minimum' => 0,
                    'maximum' => 1_000_000_000,
                    'examples' => [8400],
                ],
                'weekly_growth' => [
                    'type' => 'integer',
                    'title' => 'New followers per week',
                    'description' => 'Average over the last month, not your best week.',
                    'minimum' => 1,
                    'maximum' => 10_000_000,
                    'examples' => [180],
                ],
                'target' => [
                    'type' => 'integer',
                    'title' => 'Custom target (optional)',
                    'minimum' => 0,
                    'maximum' => 1_000_000_000,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $current = $input->int('current');
        $weekly = $input->int('weekly_growth');
        $target = $input->int('target');

        if ($weekly < 1) {
            throw ToolExecutionException::invalidInput(
                'Weekly growth must be at least 1 — a flat account never reaches the next milestone.',
                ['weekly_growth' => 'Enter your average weekly gain.'],
            );
        }

        $targets = array_values(array_filter(self::MILESTONES, fn (int $m) => $m > $current));

        if ($target > $current) {
            $targets[] = $target;
            sort($targets);
        }

        $rows = [];

        foreach (array_slice(array_unique($targets), 0, 6) as $milestone) {
            $weeks = ($milestone - $current) / $weekly;
            $days = (int) ceil($weeks * 7);

            $rows[] = [
                'milestone' => number_format($milestone),
                'needed' => number_format($milestone - $current),
                'eta' => now()->addDays($days)->format('j M Y'),
                'away' => $this->human($days),
            ];
        }

        $first = $rows[0] ?? null;

        return ToolResult::table(
            columns: [
                ['key' => 'milestone', 'label' => 'Milestone'],
                ['key' => 'needed', 'label' => 'Still needed', 'align' => 'right'],
                ['key' => 'away', 'label' => 'Time away', 'align' => 'right'],
                ['key' => 'eta', 'label' => 'Expected date'],
            ],
            rows: $rows,
            summary: $first === null
                ? 'You are already past every milestone on the list.'
                : "At {$weekly} a week you reach {$first['milestone']} in {$first['away']} — around {$first['eta']}.",
        )->withMeta(['monthly' => $weekly * 4.345, 'yearly' => $weekly * 52])
            ->withWarnings([
                'Straight-line projection at your current pace. Growth is rarely linear — one video can '
                .'compress a year of this into a fortnight, and a quiet month stretches it.',
            ]);
    }

    private function human(int $days): string
    {
        return match (true) {
            $days <= 14 => "{$days} days",
            $days <= 90 => round($days / 7).' weeks',
            $days <= 730 => round($days / 30.4).' months',
            default => round($days / 365, 1).' years',
        };
    }
}
