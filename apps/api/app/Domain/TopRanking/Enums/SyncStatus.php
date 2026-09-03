<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Enums;

/**
 * How the last refresh of a page went.
 *
 * `Partial` exists because the failure that actually happens is not "Wikipedia was
 * down" — it is "the article now has one more column, so half the rows parsed".
 * A run that imported 12 of 50 rows must not report success, and must not report
 * failure either: the 12 are real, and an editor needs to be told the number.
 */
enum SyncStatus: string
{
    case Never = 'never';
    case Ok = 'ok';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never synced',
            self::Ok => 'Synced',
            self::Partial => 'Partial',
            self::Failed => 'Failed',
        };
    }

    public function isProblem(): bool
    {
        return $this === self::Partial || $this === self::Failed;
    }
}
