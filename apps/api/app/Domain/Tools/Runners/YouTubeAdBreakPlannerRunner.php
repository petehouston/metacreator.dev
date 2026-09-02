<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Enums\ResultView;
use App\Domain\Tools\Exceptions\ToolExecutionException;

/**
 * Where the mid-rolls go — snapped to your chapters, so an ad never lands in the
 * middle of a sentence.
 *
 * Two facts do most of the work. A video must reach **eight minutes** before
 * YouTube will place a mid-roll at all, which is why so much of the platform is
 * eight minutes and ten seconds long. And YouTube's automatic placement optimises
 * for revenue rather than for your edit, which is how an ad ends up between a
 * question and its answer.
 *
 * The manual placement everybody skips is worth two minutes of work because the
 * cost of a bad break is not the ad, it is the viewer who does not come back after
 * it. So this takes the one thing you already have — your chapter list — and puts
 * a break at the natural boundary nearest each planned slot, refusing to place one
 * in the opening or in the last minute, where the drop-off is worst.
 *
 * Under eight minutes it says so and stops. A tool that returns a list of mid-roll
 * timestamps for a six-minute video has produced a plan that YouTube will ignore.
 */
final class YouTubeAdBreakPlannerRunner implements Cacheable, ToolRunner
{
    /** YouTube's published minimum length for mid-roll ads, in seconds. */
    private const MIDROLL_MINIMUM = 480;

    /** No break inside the opening: the viewer has not committed yet. */
    private const OPENING_GUARD = 90;

    /** No break in the closing minute: it buys almost nothing and costs the end screen. */
    private const CLOSING_GUARD = 60;

    /** How far a slot may move to reach a chapter boundary, in seconds. */
    private const SNAP_WINDOW = 45;

    public static function key(): string
    {
        return 'youtube.ad-break-planner';
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
            'required' => ['duration'],
            'additionalProperties' => false,
            'properties' => [
                'duration' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Video length',
                    'description' => 'As mm:ss or h:mm:ss — the length in your editor’s timeline.',
                    'pattern' => '^\\d{1,2}(:\\d{2}){1,2}$',
                    'examples' => ['22:40'],
                ],
                'chapters' => [
                    'type' => 'string',
                    'x-control' => 'textarea',
                    'title' => 'Chapters (optional)',
                    'description' => 'One per line, exactly as they go in the description: '
                        .'`0:00 Intro`. Breaks are snapped to these.',
                    'maxLength' => 4000,
                    'default' => '',
                ],
                'spacing_minutes' => [
                    'type' => 'integer',
                    'title' => 'Minutes between breaks',
                    'minimum' => 2,
                    'maximum' => 30,
                    'default' => 6,
                ],
                'include_pre_roll' => [
                    'type' => 'boolean',
                    'title' => 'Include pre-roll and post-roll',
                    'default' => true,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $duration = (int) $this->seconds($input->string('duration'));
        $spacing = max(2, min(30, $input->int('spacing_minutes', 6))) * 60;
        $chapters = $this->chapters($input->string('chapters'), $duration);
        $withEnds = $input->bool('include_pre_roll', true);

        $rows = [];

        if ($withEnds) {
            $rows[] = [
                'position' => '0:00',
                'kind' => 'Pre-roll',
                'why' => 'Always available on a monetized video, whatever its length.',
                'seconds' => 0,
            ];
        }

        $placed = [];

        if ($duration >= self::MIDROLL_MINIMUM) {
            $placed = $this->midrolls($duration, $spacing, $chapters);

            foreach ($placed as $break) {
                $rows[] = [
                    'position' => $this->clock($break['at']),
                    'kind' => 'Mid-roll',
                    'why' => $break['why'],
                    'seconds' => $break['at'],
                ];
            }
        }

        if ($withEnds) {
            $rows[] = [
                'position' => $this->clock($duration),
                'kind' => 'Post-roll',
                'why' => 'Runs after the video ends. Costs nothing in retention and is the one break '
                    .'nobody skips out of.',
                'seconds' => $duration,
            ];
        }

        return (new ToolResult(
            view: ResultView::Table,
            data: [
                'columns' => [
                    ['key' => 'position', 'label' => 'Timestamp', 'copyable' => true,
                        'copy_all' => true, 'copy_separator' => "\n"],
                    ['key' => 'kind', 'label' => 'Break'],
                    ['key' => 'why', 'label' => 'Why here'],
                ],
                'rows' => $rows,
            ],
            summary: $this->summary($duration, count($placed), $chapters !== []),
        ))->withMeta([
            'duration_seconds' => $duration,
            'midrolls' => count($placed),
            'eligible_for_midrolls' => $duration >= self::MIDROLL_MINIMUM,
            'chapters_used' => count($chapters),
        ])->withWarnings($this->warnings($duration, $chapters, count($placed)));
    }

    /**
     * Slots at the requested spacing, each moved to the nearest chapter boundary
     * within the snap window.
     *
     * A slot that cannot reach a boundary stays where it is rather than being
     * dragged across half the video: an ad two seconds after a hard cut is better
     * than one at a chapter three minutes from where the pacing wanted it.
     *
     * @param  list<array{at: int, title: string}>  $chapters
     * @return list<array{at: int, why: string}>
     */
    private function midrolls(int $duration, int $spacing, array $chapters): array
    {
        $last = $duration - self::CLOSING_GUARD;
        $breaks = [];

        for ($at = self::OPENING_GUARD + $spacing; $at <= $last; $at += $spacing) {
            $snapped = $this->snap($at, $chapters);

            if ($snapped !== null) {
                [$position, $title] = $snapped;

                if ($position < self::OPENING_GUARD || $position > $last) {
                    continue;
                }

                $breaks[] = ['at' => $position, 'why' => "Start of “{$title}” — a cut the viewer "
                    .'was expecting anyway.'];

                continue;
            }

            $breaks[] = ['at' => $at, 'why' => $chapters === []
                ? 'Evenly spaced. Add your chapters and this moves to the nearest real cut.'
                : 'No chapter within '.self::SNAP_WINDOW.' seconds, so the slot stayed on the '
                    .'pacing rather than being dragged to one.'];
        }

        // Two breaks on the same second is what happens when several slots snap to
        // one chapter; the viewer would see one ad, and the plan should say one.
        $seen = [];

        return array_values(array_filter($breaks, function (array $break) use (&$seen): bool {
            if (in_array($break['at'], $seen, true)) {
                return false;
            }

            $seen[] = $break['at'];

            return true;
        }));
    }

    /**
     * @param  list<array{at: int, title: string}>  $chapters
     * @return array{0: int, 1: string}|null
     */
    private function snap(int $at, array $chapters): ?array
    {
        $best = null;
        $bestDistance = self::SNAP_WINDOW + 1;

        foreach ($chapters as $chapter) {
            if ($chapter['at'] === 0) {
                continue;
            }

            $distance = abs($chapter['at'] - $at);

            if ($distance <= self::SNAP_WINDOW && $distance < $bestDistance) {
                $best = [$chapter['at'], $chapter['title']];
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @return list<array{at: int, title: string}>
     */
    private function chapters(string $raw, int $duration): array
    {
        $chapters = [];

        foreach (preg_split('/\r?\n/', trim($raw)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/^(\d{1,2}(?::\d{2}){1,2})\s*[-–—]?\s*(.*)$/u', $line, $match) !== 1) {
                continue;
            }

            $at = $this->seconds($match[1], strict: false);

            if ($at === null || $at > $duration) {
                continue;
            }

            $chapters[] = ['at' => $at, 'title' => trim($match[2]) === '' ? 'that chapter' : trim($match[2])];
        }

        usort($chapters, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        return $chapters;
    }

    private function seconds(string $value, bool $strict = true): ?int
    {
        $parts = array_map('intval', explode(':', trim($value)));

        if (count($parts) < 2 || count($parts) > 3) {
            if ($strict) {
                throw ToolExecutionException::invalidInput(
                    'Give the length as mm:ss or h:mm:ss.',
                    ['duration' => 'For example 22:40 or 1:05:00.'],
                );
            }

            return null;
        }

        $seconds = count($parts) === 3
            ? $parts[0] * 3600 + $parts[1] * 60 + $parts[2]
            : $parts[0] * 60 + $parts[1];

        if ($strict && $seconds < 1) {
            throw ToolExecutionException::invalidInput(
                'That length is zero.',
                ['duration' => 'For example 22:40.'],
            );
        }

        return $seconds;
    }

    private function clock(int $seconds): string
    {
        return $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private function summary(int $duration, int $midrolls, bool $hadChapters): string
    {
        if ($duration < self::MIDROLL_MINIMUM) {
            $short = self::MIDROLL_MINIMUM - $duration;

            return 'At '.$this->clock($duration).' this video is '.$this->clock($short)
                .' short of the eight minutes YouTube requires for mid-rolls. Pre-roll and post-roll '
                .'are still available — and stretching an edit to clear eight minutes is a trade you '
                .'should make on purpose, not by accident.';
        }

        return $midrolls.' mid-roll'.($midrolls === 1 ? '' : 's').' across '.$this->clock($duration)
            .($hadChapters
                ? ', each on a chapter boundary so the ad lands on a cut you already made.'
                : ', evenly spaced. Paste your chapters in and every break moves to the nearest real '
                    .'transition.');
    }

    /**
     * @param  list<array{at: int, title: string}>  $chapters
     * @return list<string>
     */
    private function warnings(int $duration, array $chapters, int $midrolls): array
    {
        $warnings = [];

        if ($duration >= self::MIDROLL_MINIMUM && $chapters === []) {
            $warnings[] = 'No chapters were given, so these are evenly spaced slots. The whole point '
                .'of placing breaks by hand is landing them on a cut, so this list is worth '
                .'re-running once your chapters exist.';
        }

        if ($midrolls > 0 && $duration / max($midrolls, 1) < 240) {
            $warnings[] = 'That is a break every four minutes or less. It will raise the ad count and '
                .'lower the share of viewers who reach the end; on a video people watch for the '
                .'ending, that trade usually loses.';
        }

        $warnings[] = 'Eight minutes is YouTube’s published threshold for mid-rolls, and pre-roll and '
            .'post-roll do not depend on length. Everything else here — the spacing, the guards at '
            .'each end — is editorial judgement, not a platform rule.';

        return $warnings;
    }
}
