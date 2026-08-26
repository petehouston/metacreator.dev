<?php

declare(strict_types=1);

namespace App\Domain\Blog\Enums;

/**
 * The post lifecycle from docs/09-blog-cms.md.
 *
 *   draft ──▶ scheduled ──(cron)──▶ published ──▶ unpublished ──▶ archived
 *      └──────────────────────────────▲   │
 *                                     └───┴──▶ deleted (soft, 30-day recovery)
 *
 * `deleted` is not a case here: soft deletion is expressed by `deleted_at`, so a
 * row can be restored to whatever status it held before rather than losing it.
 */
enum PostStatus: string
{
    /** Being written. Visible to staff and through a signed preview link. */
    case Draft = 'draft';

    /** Has a `scheduled_for` in the future; the scheduler publishes it. */
    case Scheduled = 'scheduled';

    /** Live. */
    case Published = 'published';

    /** Was live and was withdrawn. The URL returns 410 unless a redirect exists. */
    case Unpublished = 'unpublished';

    /** Kept for reference and excluded from every list. */
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Unpublished => 'Unpublished',
            self::Archived => 'Archived',
        };
    }

    /** Should this post appear on the public site and in the sitemap? */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /**
     * A URL that used to work and now does not should say so with 410 rather than
     * 404, so search engines drop it promptly instead of re-crawling for months.
     */
    public function isGone(): bool
    {
        return $this === self::Unpublished || $this === self::Archived;
    }

    /** Statuses this one may move to directly. */
    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return in_array($next, $this->allowedTransitions(), true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Published, self::Archived],
            self::Scheduled => [self::Draft, self::Published, self::Archived],
            self::Published => [self::Unpublished, self::Archived],
            self::Unpublished => [self::Draft, self::Published, self::Archived],
            self::Archived => [self::Draft],
        };
    }
}
