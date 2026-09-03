<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Enums;

/**
 * Whether a row has a picture, and if not, why not.
 *
 * Four states rather than a nullable URL, because "we have not looked yet" and "we
 * looked and this platform will not tell us" are different facts and lead to
 * different actions. The first is a job to run; the second is a URL for an editor
 * to paste, and re-running the job against it every week is wasted work.
 */
enum AvatarStatus: string
{
    /** Never attempted. The state every imported row starts in. */
    case Pending = 'pending';

    /** Resolved, and the link is believed good. */
    case Ok = 'ok';

    /**
     * Attempted and refused. Instagram, X and Facebook return a login wall to an
     * anonymous request, so this is the honest resting state for most of their
     * rows — not an error to retry into.
     */
    case Unavailable = 'unavailable';

    /**
     * We had a link and its signature has run out. Meta and TikTok sign CDN URLs
     * with an expiry, so a link that worked on Monday answers 403 by Friday.
     */
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not checked',
            self::Ok => 'Resolved',
            self::Unavailable => 'Unavailable',
            self::Expired => 'Expired',
        };
    }

    /** Should the public page try to render this link at all? */
    public function isUsable(): bool
    {
        return $this === self::Ok;
    }
}
