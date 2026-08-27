<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The window every admin metric is computed over, plus the equal-length window
 * immediately before it.
 *
 * Deltas are the whole point of the overview screen — a number with no comparison
 * is trivia. Deriving the comparison window here (rather than at each call site)
 * means "last 30 days" is always compared against the 30 days before it, and never
 * against a calendar month of a different length.
 */
final readonly class Period
{
    /** Windows the admin UI offers, in days. */
    public const PRESETS = [7, 14, 30, 90, 365];

    private function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public int $days,
    ) {}

    public static function ofDays(int $days): self
    {
        if (! in_array($days, self::PRESETS, true)) {
            throw new InvalidArgumentException("Unsupported period: {$days} days.");
        }

        $end = CarbonImmutable::now()->endOfDay();

        // `$days - 1`: a 7-day window is today plus the six days before it, not today
        // plus seven — otherwise every window silently covers one day too many and the
        // period-over-period comparison is off by one.
        return new self($end->subDays($days - 1)->startOfDay(), $end, $days);
    }

    public static function fromRequest(mixed $value, int $default = 30): self
    {
        $days = is_numeric($value) ? (int) $value : $default;

        return self::ofDays(in_array($days, self::PRESETS, true) ? $days : $default);
    }

    /** The equal-length window ending the instant this one begins. */
    public function previous(): self
    {
        return new self(
            $this->start->subDays($this->days),
            $this->start->subSecond(),
            $this->days,
        );
    }

    /** @return list<string> Every date in the window as `Y-m-d`, gaps included. */
    public function dates(): array
    {
        $dates = [];

        for ($cursor = $this->start; $cursor->lessThanOrEqualTo($this->end); $cursor = $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
        }

        return $dates;
    }

    public function label(): string
    {
        return match ($this->days) {
            7 => 'Last 7 days',
            14 => 'Last 14 days',
            30 => 'Last 30 days',
            90 => 'Last 90 days',
            default => 'Last 12 months',
        };
    }

    /** @return array{days: int, label: string, start: string, end: string} */
    public function toArray(): array
    {
        return [
            'days' => $this->days,
            'label' => $this->label(),
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
        ];
    }
}
