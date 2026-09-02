<?php

declare(strict_types=1);

namespace App\Support\Social;

/**
 * What a pasted social link actually is.
 *
 * A dozen of the tools in this catalog begin with the same sentence: "the visitor
 * pasted a URL; which platform is it, and which object on that platform does it
 * point at?" Answering it separately in each one produced twelve slightly different
 * answers, so it is answered once here.
 *
 * The identification is deliberately structural — host plus path shape — rather
 * than a fetch. Knowing that `instagram.com/p/Cxyz/` is an Instagram post is a fact
 * about the URL, and paying a network round trip to learn it would make every tool
 * that only needs the shape as slow as the one that needs the page.
 */
final class SocialUrl
{
    /**
     * Hosts we recognise, mapped to the platform token the rest of the app uses.
     *
     * Alias domains are listed alongside the canonical one because people paste
     * them constantly: `instagr.am` still redirects, `fb.watch` is what the
     * Facebook share sheet hands out, and `vm.tiktok.com` is every TikTok share.
     *
     * @var array<string, string>
     */
    private const HOSTS = [
        'youtube.com' => 'youtube',
        'youtu.be' => 'youtube',
        'youtube-nocookie.com' => 'youtube',
        'instagram.com' => 'instagram',
        'instagr.am' => 'instagram',
        'ig.me' => 'instagram',
        'tiktok.com' => 'tiktok',
        'vm.tiktok.com' => 'tiktok',
        'vt.tiktok.com' => 'tiktok',
        'twitter.com' => 'x',
        'x.com' => 'x',
        't.co' => 'x',
        'facebook.com' => 'facebook',
        'fb.com' => 'facebook',
        'fb.watch' => 'facebook',
        'fb.me' => 'facebook',
        'linkedin.com' => 'linkedin',
        'lnkd.in' => 'linkedin',
        'pinterest.com' => 'pinterest',
        'pin.it' => 'pinterest',
        'threads.com' => 'threads',
        'threads.net' => 'threads',
        'reddit.com' => 'reddit',
        'redd.it' => 'reddit',
        'tumblr.com' => 'tumblr',
        'twitch.tv' => 'twitch',
        'vimeo.com' => 'vimeo',
        'dailymotion.com' => 'dailymotion',
        'dai.ly' => 'dailymotion',
        'soundcloud.com' => 'soundcloud',
        'mastodon.social' => 'mastodon',
        'bsky.app' => 'bluesky',
        't.me' => 'telegram',
        'telegram.me' => 'telegram',
        'wa.me' => 'whatsapp',
        'flickr.com' => 'flickr',
        'flic.kr' => 'flickr',
    ];

    /** Human labels, for copy that has to name the platform. */
    private const LABELS = [
        'youtube' => 'YouTube',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'x' => 'X (Twitter)',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'pinterest' => 'Pinterest',
        'threads' => 'Threads',
        'reddit' => 'Reddit',
        'tumblr' => 'Tumblr',
        'twitch' => 'Twitch',
        'vimeo' => 'Vimeo',
        'dailymotion' => 'Dailymotion',
        'soundcloud' => 'SoundCloud',
        'mastodon' => 'Mastodon',
        'bluesky' => 'Bluesky',
        'telegram' => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'flickr' => 'Flickr',
    ];

    /**
     * Path shapes that identify the object a platform URL points at.
     *
     * Ordered per platform, first match wins, so the more specific shape is listed
     * before the one that would also swallow it — `/@user/video/123` before the
     * bare `/@user` that identifies a profile.
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private const SHAPES = [
        'youtube' => [
            ['#^/(?:embed|shorts|live|v)/[A-Za-z0-9_-]{11}#', 'video'],
            ['#^/watch#', 'video'],
            ['#^/playlist#', 'playlist'],
            ['#^/(?:@[\w.\-]+|channel/UC[\w-]{22}|c/[^/]+|user/[^/]+)#', 'channel'],
            ['#^/[A-Za-z0-9_-]{11}$#', 'video'],
        ],
        'instagram' => [
            ['#^/(?:[^/]+/)?p/[\w-]+#', 'post'],
            ['#^/(?:[^/]+/)?reels?/[\w-]+#', 'reel'],
            ['#^/(?:[^/]+/)?tv/[\w-]+#', 'post'],
            ['#^/stories/#', 'story'],
            ['#^/[\w.]+/?$#', 'profile'],
        ],
        'tiktok' => [
            ['#^/@[\w.]+/(?:video|photo)/\d+#', 'video'],
            ['#^/v/\d+#', 'video'],
            ['#^/@[\w.]+/?$#', 'profile'],
            ['#^/[A-Za-z0-9]+/?$#', 'short-link'],
        ],
        'x' => [
            ['#^/[\w]+/status(?:es)?/\d+#', 'post'],
            ['#^/i/status/\d+#', 'post'],
            ['#^/[\w]{1,15}/?$#', 'profile'],
        ],
        'facebook' => [
            ['#^/(?:watch|reel)#', 'video'],
            ['#/(?:posts|videos|photos|permalink)/#', 'post'],
            ['#^/share/#', 'post'],
            ['#^/(?:groups)/#', 'group'],
            ['#^/[^/]+/?$#', 'profile'],
        ],
        'linkedin' => [
            ['#^/(?:posts|feed/update|embed/feed/update)/#', 'post'],
            ['#^/(?:in|company|school)/#', 'profile'],
        ],
        'pinterest' => [
            ['#^/pin/#', 'pin'],
            ['#^/[^/]+/[^/]+/?$#', 'board'],
            ['#^/[^/]+/?$#', 'profile'],
        ],
        'threads' => [
            ['#^/@[\w.]+/post/#', 'post'],
            ['#^/(?:t|post)/#', 'post'],
            ['#^/@[\w.]+/?$#', 'profile'],
        ],
        'reddit' => [
            ['#^/r/[^/]+/comments/#', 'post'],
            ['#^/r/[^/]+#', 'community'],
            ['#^/(?:u|user)/#', 'profile'],
            ['#^/[a-z0-9]{5,9}/?$#', 'post'],
        ],
        'vimeo' => [
            ['#^/\d+#', 'video'],
            ['#^/[^/]+/?$#', 'profile'],
        ],
        'twitch' => [
            ['#^/videos/\d+#', 'video'],
            ['#^/[^/]+/clip/#', 'clip'],
            ['#^/[^/]+/?$#', 'channel'],
        ],
        'telegram' => [
            ['#^/[\w]+/\d+#', 'post'],
            ['#^/[\w]+/?$#', 'channel'],
        ],
        'bluesky' => [
            ['#^/profile/[^/]+/post/#', 'post'],
            ['#^/profile/#', 'profile'],
        ],
    ];

    /**
     * Identify a pasted link.
     *
     * @return array{platform: ?string, label: ?string, kind: ?string, host: ?string, path: string, url: string}
     */
    public static function identify(string $input): array
    {
        $url = self::normalise($input);
        $host = self::host($url);

        if ($host === null) {
            return ['platform' => null, 'label' => null, 'kind' => null, 'host' => null, 'path' => '', 'url' => $url];
        }

        $platform = self::platformForHost($host);
        $path = rtrim((string) (parse_url($url, PHP_URL_PATH) ?: '/'), '') ?: '/';

        return [
            'platform' => $platform,
            'label' => $platform === null ? null : (self::LABELS[$platform] ?? ucfirst($platform)),
            'kind' => $platform === null ? null : self::kind($platform, $path),
            'host' => $host,
            'path' => $path,
            'url' => $url,
        ];
    }

    public static function platform(string $input): ?string
    {
        return self::identify($input)['platform'];
    }

    public static function label(string $platform): string
    {
        return self::LABELS[$platform] ?? ucfirst($platform);
    }

    /** Add the scheme people leave off, and trim the whitespace a copy-paste adds. */
    public static function normalise(string $input): string
    {
        $input = trim($input);

        return $input === '' || str_contains($input, '://') ? $input : "https://{$input}";
    }

    /** The host, lower-cased and stripped of `www.`, or null when the input is not a URL. */
    public static function host(string $url): ?string
    {
        $host = parse_url(self::normalise($url), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return (string) preg_replace('/^www\./i', '', mb_strtolower($host));
    }

    /**
     * The tracking parameters every platform bolts onto a shared link.
     *
     * Stripping these is not cosmetic: `igshid` and `si` are per-share identifiers,
     * so pasting a link you were sent tells the platform who forwarded it to you.
     *
     * @var list<string>
     */
    public const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'dclid', 'twclid', 'ttclid',
        'igshid', 'igsh', 'si', 'feature', 'pp', 'ref', 'ref_src', 'ref_url', 's', 't',
        'mc_cid', 'mc_eid', 'yclid', 'li_fat_id', 'trk', 'trkCampaign', 'rdt_cid',
        '_branch_match_id', 'share_id', 'share_app_id', 'is_from_webapp', 'sender_device',
    ];

    /**
     * The same URL with its tracking parameters removed.
     *
     * `$kept` is returned separately rather than dropped silently, because a couple
     * of these are load-bearing on some platforms — YouTube's `t` is a timestamp,
     * not a tracker — and a tool that quietly deletes one owes the visitor the list.
     *
     * @param  list<string>  $keep  Parameters to leave alone even if they look like trackers.
     * @return array{url: string, removed: list<string>}
     */
    public static function stripTracking(string $url, array $keep = []): array
    {
        $parts = parse_url(self::normalise($url));

        if ($parts === false || ! isset($parts['host'])) {
            return ['url' => $url, 'removed' => []];
        }

        parse_str($parts['query'] ?? '', $query);

        $removed = [];

        foreach (array_keys($query) as $name) {
            $name = (string) $name;

            if (in_array($name, $keep, true)) {
                continue;
            }

            if (in_array($name, self::TRACKING_PARAMS, true)) {
                $removed[] = $name;
                unset($query[$name]);
            }
        }

        $rebuilt = ($parts['scheme'] ?? 'https').'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '')
            .($query === [] ? '' : '?'.http_build_query($query));

        return ['url' => $rebuilt, 'removed' => $removed];
    }

    private static function platformForHost(string $host): ?string
    {
        if (isset(self::HOSTS[$host])) {
            return self::HOSTS[$host];
        }

        // Subdomains: `m.youtube.com`, `music.youtube.com`, `uk.pinterest.com`,
        // `old.reddit.com`. Matching on the registrable tail is what makes a
        // country-specific Pinterest domain work without listing all forty.
        foreach (self::HOSTS as $known => $platform) {
            if (str_ends_with($host, '.'.$known)) {
                return $platform;
            }
        }

        return null;
    }

    private static function kind(string $platform, string $path): ?string
    {
        foreach (self::SHAPES[$platform] ?? [] as [$pattern, $kind]) {
            if (preg_match($pattern, $path) === 1) {
                return $kind;
            }
        }

        return null;
    }
}
