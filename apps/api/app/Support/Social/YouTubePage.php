<?php

declare(strict_types=1);

namespace App\Support\Social;

use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\SafeHttpClient;
use Throwable;

/**
 * Reads the public metadata YouTube publishes on a watch or channel page.
 *
 * Several tools need the same facts — is this video public, when was it uploaded,
 * what is the channel id — and every one of them was otherwise going to grow its
 * own slightly different regex. The page is public information about public
 * content, and no API quota is spent (see docs/08 on compliance).
 *
 * Everything here is deliberately regex-based rather than JSON-decoded: the body is
 * truncated at 2 MB by {@see SafeHttpClient}, which frequently lands mid-object, so
 * a decode of the embedded player response would fail on exactly the long pages we
 * most want to read.
 */
final class YouTubePage
{
    private const CHANNEL_MAX_BYTES = 6_000_000;

    public static function watch(string $videoId): string
    {
        return SafeHttpClient::body(SafeHttpClient::get(YouTubeUrl::watchUrl($videoId)));
    }

    /**
     * Channel pages run to about 2.5 MB and put the banner in the last few
     * kilobytes, so the default 2 MB read cap cuts off the one thing several tools
     * come here for. The cap is raised for this page only.
     */
    public static function channel(string $url): string
    {
        $response = SafeHttpClient::attempt($url);

        if ($response === null) {
            throw ToolExecutionException::upstreamFailed('youtube');
        }

        // A 404 here means the handle is free, not that the site is broken — and
        // "we couldn't find anything at that URL" is a confusing way to say so.
        if ($response->failed()) {
            throw ToolExecutionException::notFound('a channel with that handle or URL');
        }

        return SafeHttpClient::body($response, self::CHANNEL_MAX_BYTES);
    }

    /**
     * The oEmbed record, or null when the video is not publicly embeddable.
     *
     * oEmbed is an official, keyless endpoint that answers only for public videos,
     * which makes a null here meaningful: private, removed, or unlisted.
     *
     * @return array<string, mixed>|null
     */
    public static function oEmbed(string $videoId): ?array
    {
        try {
            $response = SafeHttpClient::get(
                'https://www.youtube.com/oembed?format=json&url='
                .rawurlencode(YouTubeUrl::watchUrl($videoId)),
            );
        } catch (Throwable) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /** The 15 most recent public uploads on a channel, newest first. */
    /** @return list<string> Video ids. */
    public static function recentUploads(string $channelId): array
    {
        try {
            $feed = SafeHttpClient::body(SafeHttpClient::get(
                "https://www.youtube.com/feeds/videos.xml?channel_id={$channelId}",
            ));
        } catch (ToolExecutionException) {
            return [];
        }

        preg_match_all('#<yt:videoId>([A-Za-z0-9_-]{11})</yt:videoId>#', $feed, $matches);

        return $matches[1];
    }

    /** An `og:` property, decoded. */
    public static function og(string $html, string $property): ?string
    {
        return self::attribute($html, 'property', "og:{$property}");
    }

    /** A `<meta name="…">` value, decoded. */
    public static function named(string $html, string $name): ?string
    {
        return self::attribute($html, 'name', $name);
    }

    /** A schema.org `<meta itemprop="…">` value, decoded. */
    public static function itemprop(string $html, string $name): ?string
    {
        return self::attribute($html, 'itemprop', $name);
    }

    /** A string field from the embedded player response, e.g. `"category":"Music"`. */
    public static function field(string $html, string $key): ?string
    {
        if (preg_match('/"'.preg_quote($key, '/').'"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $html, $match) !== 1) {
            return null;
        }

        $decoded = json_decode('"'.$match[1].'"');

        return is_string($decoded) ? $decoded : $match[1];
    }

    /** A boolean field from the embedded player response, null when absent. */
    public static function flag(string $html, string $key): ?bool
    {
        return preg_match('/"'.preg_quote($key, '/').'"\s*:\s*(true|false)/', $html, $match) === 1
            ? $match[1] === 'true'
            : null;
    }

    /**
     * The channel banner, as a CDN base URL with no sizing suffix.
     *
     * The banner is the one `yt3` image the page requests by width; the avatar is
     * always requested as a square, which is what tells the two apart.
     */
    public static function channelBanner(string $html): ?string
    {
        return preg_match('#"(https://yt3\.[a-z.]+/[A-Za-z0-9_\-]+)=w(?:2560|2120|1707|1060)#', $html, $match) === 1
            ? $match[1]
            : null;
    }

    /**
     * The channel avatar, as a CDN base URL with no sizing suffix.
     *
     * `og:image` is the avatar on a channel page, and Google's image CDN will
     * resize it to any square once the `=s…` suffix is stripped off.
     */
    public static function channelAvatar(string $html): ?string
    {
        $url = self::og($html, 'image');

        return $url !== null && str_contains($url, 'yt3.') ? explode('=', $url)[0] : null;
    }

    /**
     * The header line under a channel's name: handle, subscribers, videos.
     *
     * The page repeats this shape for every channel it links to in the sidebar, so
     * the three parts are matched as one run — a handle followed by its own counts
     * within the same metadata block — rather than separately, which would happily
     * pair this channel's handle with a recommended channel's subscriber count.
     *
     * @return array{handle: ?string, subscribers: ?string, videos: ?string}
     */
    public static function channelHeader(string $html): array
    {
        $pattern = '#"content":"(@[\w.\-]{1,60})"(?:(?!"content").){0,600}?'
            .'"content":"([\d.,]+[KMB]?) subscribers?"(?:(?!"content").){0,600}?'
            .'"content":"([\d.,]+[KMB]?) videos?"#s';

        if (preg_match($pattern, $html, $match) !== 1) {
            return ['handle' => null, 'subscribers' => null, 'videos' => null];
        }

        return ['handle' => $match[1], 'subscribers' => $match[2], 'videos' => $match[3]];
    }

    /**
     * `12K` → 12000. YouTube rounds these itself, so the result is approximate by
     * construction and only ever used for order-of-magnitude comparisons.
     */
    public static function abbreviatedCount(?string $text): ?int
    {
        if ($text === null || preg_match('/^([\d.,]+)([KMB]?)$/', trim($text), $match) !== 1) {
            return null;
        }

        $number = (float) str_replace(',', '', $match[1]);

        return (int) round($number * match ($match[2]) {
            'K' => 1_000,
            'M' => 1_000_000,
            'B' => 1_000_000_000,
            default => 1,
        });
    }

    /** The channel's own description, as YouTube publishes it to crawlers. */
    public static function channelDescription(string $html): ?string
    {
        return self::og($html, 'description');
    }

    public static function channelId(string $html): ?string
    {
        $patterns = [
            '#<link[^>]+rel=["\']canonical["\'][^>]+href=["\']https://www\.youtube\.com/channel/(UC[A-Za-z0-9_-]{22})#i',
            '#<meta[^>]+itemprop=["\']identifier["\'][^>]+content=["\'](UC[A-Za-z0-9_-]{22})#i',
            '#"externalId"\s*:\s*"(UC[A-Za-z0-9_-]{22})"#',
            '#"channelId"\s*:\s*"(UC[A-Za-z0-9_-]{22})"#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * Normalise a channel reference — handle, bare name, id or any URL shape — to a
     * page we can fetch.
     */
    public static function channelUrl(string $channel): string
    {
        $channel = trim($channel);

        if (str_starts_with($channel, '@')) {
            return 'https://www.youtube.com/'.$channel;
        }

        if (preg_match('#^UC[A-Za-z0-9_-]{22}$#', $channel) === 1) {
            return "https://www.youtube.com/channel/{$channel}";
        }

        if (str_contains($channel, 'youtube.com') || str_contains($channel, 'youtu.be')) {
            return str_contains($channel, '://') ? $channel : "https://{$channel}";
        }

        // A bare name: treat it as a handle, which is what people usually paste.
        return 'https://www.youtube.com/@'.ltrim($channel, '@');
    }

    /** `PT4M13S` → `4:13`. Returns null for anything unparseable. */
    public static function humanDuration(?string $iso8601): ?string
    {
        if ($iso8601 === null
            || preg_match('/^P(?:(\d+)D)?T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso8601, $match) !== 1) {
            return null;
        }

        $seconds = ((int) ($match[1] ?? 0)) * 86400
            + ((int) ($match[2] ?? 0)) * 3600
            + ((int) ($match[3] ?? 0)) * 60
            + (int) ($match[4] ?? 0);

        return self::clock($seconds);
    }

    public static function clock(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds % 60)
            : sprintf('%d:%02d', $minutes, $seconds % 60);
    }

    private static function attribute(string $html, string $attribute, string $value): ?string
    {
        $pattern = '/<meta[^>]+'.$attribute.'=["\']'.preg_quote($value, '/').'["\'][^>]*content=["\']([^"\']*)["\']/i';

        if (preg_match($pattern, $html, $match) !== 1) {
            // Some of YouTube's tags put `content` first; try the mirrored order.
            $pattern = '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*'.$attribute.'=["\']'.preg_quote($value, '/').'["\']/i';

            if (preg_match($pattern, $html, $match) !== 1) {
                return null;
            }
        }

        $decoded = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);

        return $decoded === '' ? null : $decoded;
    }
}
