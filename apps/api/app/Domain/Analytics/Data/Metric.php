<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Data;

/**
 * A headline number with everything needed to render it honestly: the comparison
 * value, the direction, and the daily series behind it.
 *
 * `direction` is separate from the sign of the delta because "down" is not always
 * bad — a falling failure rate is an improvement. The service that builds the
 * metric knows which way is up; the UI must not have to guess.
 */
final readonly class Metric
{
    private function __construct(
        public string $key,
        public string $label,
        public float $value,
        public ?float $previous,
        public string $format,
        public bool $higherIsBetter,
        /** @var list<array{date: string, value: float}> */
        public array $series,
        public ?string $hint,
    ) {}

    /**
     * @param  list<array{date: string, value: float}>  $series
     */
    public static function make(
        string $key,
        string $label,
        float $value,
        ?float $previous = null,
        string $format = 'number',
        bool $higherIsBetter = true,
        array $series = [],
        ?string $hint = null,
    ): self {
        return new self($key, $label, $value, $previous, $format, $higherIsBetter, $series, $hint);
    }

    /**
     * Percentage change against the previous window.
     *
     * Null rather than 0 when the previous window was empty: "up 100%" from a base of
     * nothing is a number that reads as insight and carries none.
     */
    public function changePercent(): ?float
    {
        if ($this->previous === null || $this->previous == 0.0) {
            return null;
        }

        return round((($this->value - $this->previous) / abs($this->previous)) * 100, 1);
    }

    /** @return 'up'|'down'|'flat' */
    public function trend(): string
    {
        $change = $this->changePercent();

        return match (true) {
            $change === null || abs($change) < 0.5 => 'flat',
            $change > 0 => 'up',
            default => 'down',
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'previous' => $this->previous,
            'change_percent' => $this->changePercent(),
            'trend' => $this->trend(),
            'higher_is_better' => $this->higherIsBetter,
            'format' => $this->format,
            'hint' => $this->hint,
            'series' => $this->series,
        ];
    }
}
