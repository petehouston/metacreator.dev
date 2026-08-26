<?php

declare(strict_types=1);

namespace App\Domain\Tools\Enums;

/**
 * What a visitor must have in order to run a tool.
 *
 * The order of the cases is meaningful: a higher-ranked actor may always run a
 * lower-ranked tool. Use {@see self::rank()} rather than comparing cases.
 */
enum ToolTier: string
{
    case Free = 'free';
    case Account = 'account';
    case Premium = 'premium';

    public function rank(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Account => 1,
            self::Premium => 2,
        };
    }

    public function satisfiedBy(self $available): bool
    {
        return $available->rank() >= $this->rank();
    }

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Account => 'Free account',
            self::Premium => 'Pro',
        };
    }

    /** Short marketing copy shown on catalog cards and tool headers. */
    public function description(): string
    {
        return match ($this) {
            self::Free => 'Use it right now — no account needed.',
            self::Account => 'Create a free account to use this tool.',
            self::Premium => 'Included with a Pro subscription.',
        };
    }
}
