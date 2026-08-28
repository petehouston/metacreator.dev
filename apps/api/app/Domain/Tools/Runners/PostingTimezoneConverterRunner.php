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
use Throwable;

/**
 * One posting time, shown in every timezone your audience lives in.
 *
 * Scheduling tools take your local time and say nothing about what that means for
 * the half of your audience asleep. This lists the major markets, flags the ones
 * where it lands in the middle of the night, and handles the daylight-saving shift
 * that catches everyone out twice a year.
 */
final class PostingTimezoneConverterRunner implements Cacheable, ToolRunner
{
    /** @var array<string, string> */
    private const MARKETS = [
        'America/Los_Angeles' => 'Los Angeles',
        'America/Chicago' => 'Chicago',
        'America/New_York' => 'New York',
        'America/Sao_Paulo' => 'São Paulo',
        'Europe/London' => 'London',
        'Europe/Berlin' => 'Berlin / Paris',
        'Africa/Lagos' => 'Lagos',
        'Asia/Dubai' => 'Dubai',
        'Asia/Kolkata' => 'Mumbai',
        'Asia/Singapore' => 'Singapore',
        'Asia/Tokyo' => 'Tokyo',
        'Australia/Sydney' => 'Sydney',
    ];

    public static function key(): string
    {
        return 'utility.timezone-converter';
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
            'required' => ['datetime', 'timezone'],
            'additionalProperties' => false,
            'properties' => [
                'datetime' => [
                    'type' => 'string',
                    'title' => 'When are you posting?',
                    'description' => 'Format: YYYY-MM-DD HH:MM (24-hour).',
                    'minLength' => 10,
                    'maxLength' => 25,
                    'examples' => ['2026-09-01 18:30'],
                ],
                'timezone' => [
                    'type' => 'string',
                    'title' => 'Your timezone',
                    'enum' => array_keys(self::MARKETS),
                    'default' => 'Europe/London',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $zone = $input->string('timezone', 'Europe/London');

        try {
            $moment = new DateTimeImmutable($input->string('datetime'), new DateTimeZone($zone));
        } catch (Throwable) {
            throw ToolExecutionException::invalidInput(
                'That date and time could not be read. Use the format 2026-09-01 18:30.',
                ['datetime' => 'Expected YYYY-MM-DD HH:MM.'],
            );
        }

        $rows = [];
        $asleep = 0;

        foreach (self::MARKETS as $tz => $city) {
            $local = $moment->setTimezone(new DateTimeZone($tz));
            $hour = (int) $local->format('G');

            $verdict = match (true) {
                $hour >= 23 || $hour < 6 => 'Asleep',
                $hour < 9 => 'Commute — strong',
                $hour < 12 => 'Morning',
                $hour < 14 => 'Lunch — strong',
                $hour < 18 => 'Afternoon',
                $hour < 22 => 'Evening — strong',
                default => 'Winding down',
            };

            if ($verdict === 'Asleep') {
                $asleep++;
            }

            $rows[] = [
                'city' => $city,
                'local_time' => $local->format('D j M, H:i'),
                'offset' => $local->format('P'),
                'verdict' => $verdict,
            ];
        }

        return ToolResult::table(
            columns: [
                ['key' => 'city', 'label' => 'Market'],
                ['key' => 'local_time', 'label' => 'Local time'],
                ['key' => 'offset', 'label' => 'UTC offset'],
                ['key' => 'verdict', 'label' => 'Audience state'],
            ],
            rows: $rows,
            summary: $moment->format('D j M Y, H:i')." in {$zone} — "
                .(count(self::MARKETS) - $asleep).' of '.count(self::MARKETS)
                .' major markets are awake for it.',
        )->withMeta(['utc' => $moment->setTimezone(new DateTimeZone('UTC'))->format('c')])
            ->withWarnings([
                'Offsets shown are for that exact date, so daylight saving is already applied — '
                .'the same clock time can shift by an hour a fortnight later.',
            ]);
    }
}
