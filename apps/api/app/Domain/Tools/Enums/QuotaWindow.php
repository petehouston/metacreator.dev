<?php

declare(strict_types=1);

namespace App\Domain\Tools\Enums;

use Carbon\CarbonImmutable;

/**
 * A period a run budget is counted over.
 *
 * Three windows exist rather than one because they answer different questions.
 * A daily cap is about a runaway afternoon; a monthly cap is about what a plan
 * costs us over a billing period. An operator who only has "runs per day" has to
 * pick a number that is simultaneously a burst guard and a cost ceiling, and it
 * is never both.
 *
 * Every window is enforced independently and all of them apply at once: the first
 * one to run out is the one that walls. A window left unset (`-1`) is simply not
 * counted, which is what makes the set of active windows configuration rather
 * than a code change.
 *
 * The case order is the enforcement order and the order the admin screen renders
 * in — shortest first, because that is the one people hit.
 */
enum QuotaWindow: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /** Every window, shortest first. Iterate this rather than hard-coding three. */
    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /** "today" / "this week" / "this month" — the phrase a quota wall uses. */
    public function period(): string
    {
        return match ($this) {
            self::Daily => 'today',
            self::Weekly => 'this week',
            self::Monthly => 'this month',
        };
    }

    /** The settings key holding this window's budget for one access tier. */
    public function settingKey(ToolTier $tier): string
    {
        return "tools.limits.{$tier->value}.{$this->value}";
    }

    /**
     * The identifier of the period `$now` falls in.
     *
     * Part of the Redis counter key, so a new period is a new key rather than a
     * scheduled reset — nothing has to run at midnight for the budget to roll over.
     * Weeks are ISO weeks (Monday-based), which is what `oW` gives us: the ISO
     * year, not the calendar year, so the days either side of New Year land in one
     * bucket instead of two.
     */
    public function periodKey(CarbonImmutable $now): string
    {
        return match ($this) {
            self::Daily => $now->format('Y-m-d'),
            self::Weekly => $now->format('o-\WW'),
            self::Monthly => $now->format('Y-m'),
        };
    }

    /** The instant this period rolls over — what an exhausted actor waits for. */
    public function endsAt(CarbonImmutable $now): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $now->addDay()->startOfDay(),
            self::Weekly => $now->addWeek()->startOfWeek(),
            self::Monthly => $now->addMonth()->startOfMonth(),
        };
    }

    /**
     * How long the counter should live.
     *
     * Expiring exactly at the rollover (plus a minute of slack for clock skew)
     * rather than after a fixed span means a counter opened on the last day of the
     * month does not keep that month's total alive halfway into the next one.
     */
    public function ttlSeconds(CarbonImmutable $now): int
    {
        return max(60, $this->endsAt($now)->getTimestamp() - $now->getTimestamp() + 60);
    }
}
