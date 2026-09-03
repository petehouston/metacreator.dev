<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Enums;

use App\Domain\Tools\Enums\Platform;

/**
 * The networks a ranking page can be about.
 *
 * A closed enum rather than a free string on the page row, because three separate
 * things key off it — the brand colour on the public table, the shape of a profile
 * URL, and which avatar strategy is even worth attempting — and a typo'd
 * "instgram" would fail all three silently.
 *
 * Deliberately narrower than {@see Platform} and the
 * frontend's `platforms` list: this enum only holds networks Wikipedia actually
 * maintains a followers list for. Pinterest and Threads have no such article, so
 * offering them here would be a page that can never sync.
 */
enum RankingPlatform: string
{
    case YouTube = 'youtube';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case X = 'x';
    case Facebook = 'facebook';
    case Twitch = 'twitch';
    case Bluesky = 'bluesky';

    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::X => 'X',
            self::Facebook => 'Facebook',
            self::Twitch => 'Twitch',
            self::Bluesky => 'Bluesky',
        };
    }

    /** What one row on this platform is called, for headings and empty states. */
    public function noun(): string
    {
        return match ($this) {
            self::YouTube, self::Twitch => 'channel',
            self::Facebook => 'page',
            default => 'account',
        };
    }

    /**
     * The profile address for a handle.
     *
     * Only used when the source did not publish a link of its own — most of these
     * articles do, and the published one is always preferred: it is what a human
     * checked, and it survives a platform changing its URL shape.
     */
    public function profileUrl(string $handle): ?string
    {
        $handle = ltrim(trim($handle), '@');

        // A display name is not a handle. Several of these articles identify a row
        // by its human name only — Facebook publishes "Cristiano Ronaldo", not a
        // vanity path — and stitching that into a URL produces a link with a space
        // in it that 404s for every reader who clicks it. No link is better than a
        // broken one, so anything that is not handle-shaped gets none.
        if ($handle === '' || preg_match('/^[A-Za-z0-9._-]{1,120}$/', $handle) !== 1) {
            return null;
        }

        return match ($this) {
            self::YouTube => 'https://www.youtube.com/@'.$handle,
            self::Instagram => 'https://www.instagram.com/'.$handle,
            self::TikTok => 'https://www.tiktok.com/@'.$handle,
            self::X => 'https://x.com/'.$handle,
            self::Facebook => 'https://www.facebook.com/'.$handle,
            self::Twitch => 'https://www.twitch.tv/'.$handle,
            self::Bluesky => 'https://bsky.app/profile/'.$handle,
        };
    }

    /**
     * The brand hue, as an oklch triple the frontend drops straight into a colour
     * function.
     *
     * Held here rather than in the stylesheet so that adding a platform is one
     * change: the API is already telling the page which network a row belongs to,
     * and a second hand-maintained map keyed by the same string is a map that
     * drifts.
     */
    public function accent(): string
    {
        return match ($this) {
            self::YouTube => '0.58 0.235 25',
            self::Instagram => '0.62 0.215 350',
            self::TikTok => '0.72 0.165 190',
            // X's brand is black, which is not a colour a dot can be drawn in:
            // invisible on the dark theme, and indistinguishable from body text on
            // the light one. A mid-tone neutral is the honest compromise — it still
            // reads as "the greyscale one" beside six saturated hues.
            self::X => '0.56 0.014 260',
            self::Facebook => '0.55 0.190 260',
            self::Twitch => '0.55 0.220 300',
            self::Bluesky => '0.66 0.165 245',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function catalog(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
