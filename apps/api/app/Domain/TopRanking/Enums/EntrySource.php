<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Enums;

/**
 * Who put a row on the page — which is the same question as "what may a sync do
 * to it".
 *
 * Without this distinction an automated weekly refresh has only two options, both
 * wrong: replace every row (and lose the entry an editor added by hand on Friday)
 * or add only what is missing (and keep an account that dropped off the list a
 * year ago, forever).
 */
enum EntrySource: string
{
    /** Imported from the article. A sync owns it: it may update, move or remove it. */
    case Wikipedia = 'wikipedia';

    /** Added in admin. A sync never removes it, and never overwrites its fields. */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Wikipedia => 'Wikipedia',
            self::Manual => 'Added by hand',
        };
    }
}
