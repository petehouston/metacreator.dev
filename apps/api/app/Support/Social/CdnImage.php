<?php

declare(strict_types=1);

namespace App\Support\Social;

/**
 * What a platform's image CDN will tell you about a file, from the URL alone.
 *
 * Three of the image downloaders in this catalog hand back links that are not
 * permanent. Meta signs every `fbcdn.net` and `cdninstagram.com` URL with a hash
 * and an expiry, and a signed link that has passed its expiry answers 403 — so a
 * visitor who bookmarks one instead of saving the file gets a broken link later
 * and no explanation of why. The expiry is in the URL, in the open, so the honest
 * thing is to read it and say when the link stops working.
 *
 * Nothing here fetches anything. Every method is a statement about the URL string,
 * which is what lets the downloaders call them per row without paying a round trip
 * per row.
 */
final class CdnImage
{
    /** Hosts whose URLs Meta signs, and whose `oe` parameter is therefore an expiry. */
    private const META_HOSTS = ['fbcdn.net', 'cdninstagram.com'];

    /**
     * Whether this URL is signed and will stop working.
     *
     * Signed is not the same as "from Meta": Meta serves some unsigned assets too,
     * and the presence of the signature parameters is the thing that matters.
     */
    public static function isSigned(string $url): bool
    {
        return self::isMetaHost($url) && self::query($url)['oh'] !== null;
    }

    /**
     * When a Meta-signed URL stops working, as a unix timestamp.
     *
     * `oe` is the expiry, hex-encoded. It is parsed defensively rather than
     * trusted: a value that decodes to a time in the distant past or a decade out
     * is not an expiry we understand, and quoting it would be worse than saying
     * nothing. Returns null for any URL this does not apply to.
     */
    public static function expiresAt(string $url): ?int
    {
        if (! self::isMetaHost($url)) {
            return null;
        }

        $oe = self::query($url)['oe'];

        if ($oe === null || preg_match('/^[0-9a-f]{6,12}$/i', $oe) !== 1) {
            return null;
        }

        $timestamp = (int) hexdec($oe);
        $now = time();

        // A signed link's life is measured in hours or days. Anything outside a
        // window of roughly a year either way is not the number we think it is.
        return $timestamp > $now - 31_536_000 && $timestamp < $now + 31_536_000
            ? $timestamp
            : null;
    }

    /**
     * How long a signed URL has left, in words, or null when it is not signed.
     *
     * Deliberately relative — "about 3 hours" rather than a clock time — because
     * the visitor's question is "do I need to save this now?", and a timestamp in
     * a timezone they have to work out does not answer it. Relative phrasing also
     * keeps the copy free of the dates this project's writing standard bans.
     */
    public static function lifetime(string $url): ?string
    {
        $expiry = self::expiresAt($url);

        if ($expiry === null) {
            return null;
        }

        $seconds = $expiry - time();

        if ($seconds <= 0) {
            return 'already expired';
        }

        if ($seconds < 3600) {
            return 'under an hour';
        }

        $hours = (int) round($seconds / 3600);

        return $hours < 48
            ? 'about '.$hours.' '.($hours === 1 ? 'hour' : 'hours')
            : 'about '.(int) round($hours / 24).' days';
    }

    private static function isMetaHost(string $url): bool
    {
        $host = SocialUrl::host($url);

        if ($host === null) {
            return false;
        }

        foreach (self::META_HOSTS as $known) {
            if ($host === $known || str_ends_with($host, '.'.$known)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The URL's query string as an array, with the two signature keys always present.
     *
     * @return array{oh: ?string, oe: ?string}
     */
    private static function query(string $url): array
    {
        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);

        return [
            'oh' => isset($query['oh']) && is_string($query['oh']) ? $query['oh'] : null,
            'oe' => isset($query['oe']) && is_string($query['oe']) ? $query['oe'] : null,
        ];
    }
}
