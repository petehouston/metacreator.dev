<?php

declare(strict_types=1);

namespace App\Domain\Support\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /** First-response target in hours (see docs/12). */
    public function firstResponseHours(): int
    {
        return match ($this) {
            self::Urgent => 2,
            self::High => 8,
            self::Normal => 24,
            self::Low => 48,
        };
    }

    public function resolutionHours(): int
    {
        return match ($this) {
            self::Urgent => 8,
            self::High => 24,
            self::Normal => 72,
            self::Low => 168,
        };
    }
}
