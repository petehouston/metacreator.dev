<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A publishing schedule, spaced rather than clustered.
 *
 * The scheduling advice creators are usually given — "post on Saturday at 5pm" —
 * is folklore built on aggregate data that says nothing about any one audience.
 * What does hold is spacing: a channel that publishes on a rhythm gets browsed and
 * suggested more than one that drops three videos on a Sunday and nothing for a
 * fortnight. So the calendar spreads slots evenly through each week, assigns your
 * own content pillars round-robin so no theme is starved, and keeps long-form and
 * Shorts on separate days where the cadence allows it.
 */
final class YouTubeContentCalendarRunner implements Cacheable, ToolRunner
{
    private const MAX_SLOTS = 400;

    public static function key(): string
    {
        return 'youtube.content-calendar';
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
            'required' => ['start_date'],
            'additionalProperties' => false,
            'properties' => [
                'start_date' => [
                    'type' => 'string',
                    'title' => 'Start date',
                    'format' => 'date',
                    'description' => 'The calendar begins on the Monday of this date’s week.',
                    'minLength' => 8,
                    'maxLength' => 10,
                    'examples' => ['2026-09-07'],
                ],
                'weeks' => [
                    'type' => 'integer',
                    'title' => 'How many weeks to plan',
                    'minimum' => 1,
                    'maximum' => 52,
                    'default' => 8,
                ],
                'long_form_per_week' => [
                    'type' => 'integer',
                    'title' => 'Long-form videos per week',
                    'minimum' => 0,
                    'maximum' => 7,
                    'default' => 1,
                ],
                'shorts_per_week' => [
                    'type' => 'integer',
                    'title' => 'Shorts per week',
                    'minimum' => 0,
                    'maximum' => 14,
                    'default' => 3,
                ],
                'publish_time' => [
                    'type' => 'string',
                    'title' => 'Publish time',
                    'description' => 'In your channel’s local time, 24-hour clock.',
                    'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$',
                    'default' => '16:00',
                    'examples' => ['16:00'],
                ],
                'pillars' => [
                    'type' => 'string',
                    'title' => 'Content pillars (one per line)',
                    'description' => 'The three to five recurring themes your channel covers. '
                        .'Each slot is assigned one in turn, so none gets neglected.',
                    'maxLength' => 600,
                    'default' => "Tutorial\nReview\nBehind the scenes",
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $start = $this->weekStart($input->string('start_date'));
        $weeks = max(1, min(52, $input->int('weeks', 8)));
        $longForm = max(0, min(7, $input->int('long_form_per_week', 1)));
        $shorts = max(0, min(14, $input->int('shorts_per_week', 3)));
        $time = $this->time($input->string('publish_time', '16:00'));
        $pillars = $this->pillars($input->string('pillars'));

        if ($longForm + $shorts === 0) {
            throw ToolExecutionException::invalidInput(
                'A calendar needs at least one upload a week.',
                ['long_form_per_week' => 'Set long-form videos, Shorts, or both above zero.'],
            );
        }

        $rows = [];
        $pillarIndex = 0;

        for ($week = 0; $week < $weeks; $week++) {
            foreach ($this->weekSlots($longForm, $shorts) as $slot) {
                if (count($rows) >= self::MAX_SLOTS) {
                    break 2;
                }

                $date = $start->modify('+'.($week * 7 + $slot['day']).' days')
                    ->setTime($time[0], $time[1]);

                $rows[] = [
                    'date' => $date->format('D j M Y'),
                    'time' => $date->format('H:i'),
                    'format' => $slot['format'],
                    'pillar' => $pillars[$pillarIndex++ % count($pillars)],
                    'week' => 'Week '.($week + 1),
                    'iso' => $date->format('Y-m-d\TH:i:s'),
                ];
            }
        }

        $result = ToolResult::table(
            columns: [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'time', 'label' => 'Time', 'align' => 'right'],
                ['key' => 'format', 'label' => 'Format'],
                ['key' => 'pillar', 'label' => 'Pillar'],
                ['key' => 'week', 'label' => 'Week'],
            ],
            rows: $rows,
            summary: count($rows).' uploads across '.$weeks.' week'.($weeks === 1 ? '' : 's')
                .' — '.$longForm.' long-form and '.$shorts.' Shorts a week, spaced evenly.',
        )->withMeta([
            'slots' => count($rows),
            'first_slot' => $rows[0]['iso'] ?? null,
            'last_slot' => $rows === [] ? null : $rows[count($rows) - 1]['iso'],
            'weekly_cadence' => $longForm + $shorts,
        ]);

        return $result->withWarnings($this->warnings($longForm, $shorts, count($rows)));
    }

    /**
     * Slots for one week, ordered by day.
     *
     * Long-form goes down first, spread across the whole week; each Short then takes
     * the emptiest day at or after its ideal slot. That ordering is what keeps a
     * 1× long-form, 3× Shorts week from stacking everything on consecutive days.
     *
     * @return list<array{day: int, format: string}>
     */
    private function weekSlots(int $longForm, int $shorts): array
    {
        $slots = [];
        $load = array_fill(0, 7, 0);

        // Long-form anchors the week starting Thursday, which is where a weekly
        // upload has the whole weekend to accumulate its first-48-hours signal.
        foreach ($this->spread($longForm, 3) as $day) {
            $slots[] = ['day' => $day, 'format' => 'Long-form'];
            $load[$day]++;
        }

        foreach ($this->spread($shorts, 0) as $ideal) {
            $day = $this->emptiestDayFrom($load, $ideal);
            $slots[] = ['day' => $day, 'format' => 'Short'];
            $load[$day]++;
        }

        usort($slots, fn (array $a, array $b) => [$a['day'], $a['format']] <=> [$b['day'], $b['format']]);

        return $slots;
    }

    /**
     * The least-loaded day at or after `$ideal`, wrapping round the week.
     *
     * Scanning forward from the ideal day rather than over the whole week keeps the
     * result close to the even spread we asked for, and ties break towards the
     * earlier day so the schedule is stable run to run.
     *
     * @param  array<int, int>  $load
     */
    private function emptiestDayFrom(array $load, int $ideal): int
    {
        $best = $ideal;

        for ($offset = 1; $offset < 7; $offset++) {
            $day = ($ideal + $offset) % 7;

            if ($load[$day] < $load[$best]) {
                $best = $day;
            }
        }

        return $best;
    }

    /**
     * `$count` day indices spread as evenly as seven days allow, starting at `$from`.
     *
     * @return list<int>
     */
    private function spread(int $count, int $from): array
    {
        if ($count <= 0) {
            return [];
        }

        $step = 7 / min($count, 7);

        return array_map(
            fn (int $i) => (int) (($from + round($i * $step)) % 7),
            range(0, $count - 1),
        );
    }

    /** Snap to the Monday of the given date's week, so every row lines up. */
    private function weekStart(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($date), new DateTimeZone('UTC'));

        if ($parsed === false) {
            throw ToolExecutionException::invalidInput(
                'That start date could not be read. Use the format YYYY-MM-DD.',
                ['start_date' => 'Expected a date like 2026-09-07.'],
            );
        }

        return $parsed->modify('monday this week');
    }

    /** @return array{int, int} */
    private function time(string $value): array
    {
        return preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', trim($value), $match) === 1
            ? [(int) $match[1], (int) $match[2]]
            : [16, 0];
    }

    /** @return list<string> */
    private function pillars(string $value): array
    {
        $pillars = array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $value) ?: []),
            fn (string $pillar) => $pillar !== '',
        ));

        return $pillars === [] ? ['Upload'] : array_slice($pillars, 0, 12);
    }

    /** @return list<string> */
    private function warnings(int $longForm, int $shorts, int $slots): array
    {
        $warnings = [];

        if ($longForm >= 4) {
            $warnings[] = 'Four or more long-form videos a week is a full-time production schedule. '
                .'A cadence you abandon in month two is worse for the channel than a slower one you keep.';
        }

        if ($shorts >= 10) {
            $warnings[] = 'Ten or more Shorts a week only works with a batching workflow — film a week’s '
                .'worth in one session, or the schedule will slip.';
        }

        if ($slots >= self::MAX_SLOTS) {
            $warnings[] = 'The calendar was capped at '.self::MAX_SLOTS.' slots. Plan a shorter run of weeks '
                .'to see the whole thing.';
        }

        return $warnings;
    }
}
