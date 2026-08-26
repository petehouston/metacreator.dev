<?php

declare(strict_types=1);

namespace App\Domain\Support\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';      // waiting on the customer — SLA clock paused
    case OnHold = 'on_hold';       // blocked on us or a third party
    case Solved = 'solved';        // reopenable for 14 days
    case Closed = 'closed';        // final

    public function isResolved(): bool
    {
        return $this === self::Solved || $this === self::Closed;
    }

    /** Does the SLA clock run in this state? */
    public function countsTowardsSla(): bool
    {
        return $this === self::Open;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Waiting on customer',
            self::OnHold => 'On hold',
            self::Solved => 'Solved',
            self::Closed => 'Closed',
        };
    }
}
