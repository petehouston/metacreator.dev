<?php

declare(strict_types=1);

namespace App\Support\Social;

/**
 * Parses the many shapes a "YouTube link" arrives in.
 *
 * Users paste watch URLs, share links, embeds, Shorts, live URLs, links with
 * playlist and timestamp parameters, mobile URLs, and sometimes just the bare id.
 * Handling all of them is the difference between a tool that works and one that
 * makes people feel stupid.
 */
final class YouTubeUrl
{
    /** A video id is exactly 11 characters of the URL-safe base64 alphabet. */
    private const ID_PATTERN = '[A-Za-z0-9_-]{11}';

    private const HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtu.be', 'www.youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com',
    ];

    public static function videoId(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        // A bare id, pasted straight from the address bar.
        if (preg_match('/^'.self::ID_PATTERN.'$/', $input) === 1) {
            return $input;
        }

        $url = str_contains($input, '://') ? $input : "https://{$input}";
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['host']), self::HOSTS, true)) {
            return null;
        }

        // youtu.be/<id>
        $path = $parts['path'] ?? '';
        if (str_ends_with(strtolower($parts['host']), 'youtu.be')) {
            return self::matchId(ltrim($path, '/'));
        }

        // youtube.com/watch?v=<id>
        parse_str($parts['query'] ?? '', $query);
        if (isset($query['v']) && is_string($query['v'])) {
            return self::matchId($query['v']);
        }

        // /embed/<id>, /shorts/<id>, /live/<id>, /v/<id>
        if (preg_match('#^/(embed|shorts|live|v)/('.self::ID_PATTERN.')#', $path, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }

    /** Resolve a channel handle (`@name`) or id from a channel URL. */
    public static function channelHandle(string $input): ?string
    {
        $input = trim($input);

        if (str_starts_with($input, '@')) {
            return $input;
        }

        $path = parse_url(str_contains($input, '://') ? $input : "https://{$input}", PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        if (preg_match('#^/(@[A-Za-z0-9._-]{3,30})#', $path, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('#^/channel/(UC[A-Za-z0-9_-]{22})#', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public static function watchUrl(string $videoId, ?int $atSeconds = null): string
    {
        $url = "https://www.youtube.com/watch?v={$videoId}";

        return $atSeconds !== null && $atSeconds > 0 ? "{$url}&t={$atSeconds}s" : $url;
    }

    private static function matchId(string $candidate): ?string
    {
        return preg_match('/^('.self::ID_PATTERN.')/', $candidate, $matches) === 1 ? $matches[1] : null;
    }
}
