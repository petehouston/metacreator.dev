<?php

declare(strict_types=1);

namespace App\Support\Social;

/**
 * How a URL reads once a platform has finished with it.
 *
 * Feeds do not show the URL you pasted. They show the host, stripped of `www.`,
 * often upper-cased, with the path and every tracking parameter thrown away — which
 * is why a carefully tagged link looks like plain "example.com" in the card. Preview
 * tools all need the same reduction, so it lives here once.
 */
final class LinkDisplay
{
    /** The host as a feed shows it, or the input unchanged when it is not a URL. */
    public static function domain(string $url, string $fallback = 'example.com'): string
    {
        $url = trim($url);

        if ($url === '') {
            return $fallback;
        }

        $host = parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return $url;
        }

        return (string) preg_replace('/^www\./i', '', mb_strtolower($host));
    }
}
