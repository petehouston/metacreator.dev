<?php

declare(strict_types=1);

namespace App\Domain\Changelog\Enums;

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Changelog\Models\ChangelogRelease;

/**
 * A release note's lifecycle.
 *
 *   draft ──▶ scheduled ──(released_at passes)──▶ published
 *      ▲                                              │
 *      └──────────────────────────────────────────────┘
 *
 * Shorter than {@see PostStatus} on purpose. A changelog is
 * an append-only public record: an entry that shipped is not un-shipped because
 * marketing changed its mind, so there is no `unpublished` and no `archived`.
 * Getting something wrong is corrected by editing the entry, which is honest,
 * rather than by hiding it, which is not.
 *
 * There is no `scheduled` cron either: `published` plus a future `released_at` is
 * already invisible to {@see ChangelogRelease::scopePublic()},
 * so an embargo needs no job to lift it.
 */
enum ReleaseStatus: string
{
    /** Being written. Staff-only. */
    case Draft = 'draft';

    /**
     * Finished, and dated in the future. Identical to `published` in every respect
     * except what the admin list calls it — which is the whole point: an editor
     * needs to see at a glance which entries are waiting on a date.
     */
    case Scheduled = 'scheduled';

    /** Live, if `released_at` has passed. */
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
        };
    }

    /** Does this status let a release show publicly at all? */
    public function isPublishable(): bool
    {
        return $this !== self::Draft;
    }
}
