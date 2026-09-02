<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\SocialUrl;
use App\Support\Social\YouTubeUrl;

/**
 * The platform's own short domain, where one can be derived — and a straight answer
 * where one cannot.
 *
 * A first-party short link is a different thing from a bit.ly: it is served by the
 * platform, it never expires, it needs no account, and it cannot be taken down by a
 * shortening service changing its pricing or shutting off. Where a platform has one
 * that can be *derived from the URL*, this builds it. That is the important
 * qualifier, and it is why this tool exists rather than a page per platform.
 *
 * Investigating the field turned up a clean split:
 *
 * | Derivable from the URL | Issued by the platform only |
 * | --- | --- |
 * | YouTube `youtu.be`, Instagram `instagr.am`, Reddit `redd.it`, Dailymotion `dai.ly`, Flickr `flic.kr`, Telegram `t.me`, WhatsApp `wa.me` | X `t.co`, LinkedIn `lnkd.in`, Pinterest `pin.it`, Facebook `fb.me`/`fb.watch`, TikTok `vm.tiktok.com` |
 *
 * The right-hand column is not a gap to be filled later: those domains are minted
 * server-side when the platform's own share sheet is used, and there is no
 * documented way to construct one. Every "TikTok link shortener" that claims
 * otherwise is a third-party redirector wearing the platform's name. Saying so
 * plainly is more use to somebody than an invented link that 404s.
 *
 * Tracking parameters are stripped on the way through. `igshid`, `si` and
 * `share_id` are per-share identifiers: forwarding a link you were sent, with them
 * intact, tells the platform who forwarded it to you.
 */
final class SocialLinkShortenerRunner implements Cacheable, ToolRunner
{
    /** Flickr's own base58 alphabet — no 0/O/I/l, which is the point of it. */
    private const BASE58 = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Platforms whose short domain only ever comes from their own share sheet.
     *
     * @var array<string, array{domain: string, how: string}>
     */
    private const ISSUED_ONLY = [
        'x' => ['domain' => 't.co', 'how' => 'X wraps every link in t.co automatically when the post is '
            .'published. There is no way to mint one for a link that has not been posted.'],
        'linkedin' => ['domain' => 'lnkd.in', 'how' => 'LinkedIn issues lnkd.in links through its own share '
            .'button and the mobile app. A post URL is already the canonical link — use it as-is.'],
        'pinterest' => ['domain' => 'pin.it', 'how' => 'pin.it links come from the Pinterest app\'s share '
            .'sheet. Open the Pin, tap Share, then Copy Link.'],
        'facebook' => ['domain' => 'fb.me / fb.watch', 'how' => 'Facebook generates these itself when you '
            .'share from the app. A /share/ URL from the share dialog is the closest thing you can build.'],
        'tiktok' => ['domain' => 'vm.tiktok.com', 'how' => 'TikTok mints vm.tiktok.com links in the app\'s '
            .'share sheet. Tap Share, then Copy Link — anything else offering you one is a third-party '
            .'redirector, not TikTok.'],
        'threads' => ['domain' => '—', 'how' => 'Threads has no short domain. The post URL is already short.'],
        'twitch' => ['domain' => '—', 'how' => 'Twitch has no short domain. Clip URLs (clips.twitch.tv) are '
            .'the shortest form it publishes.'],
        'bluesky' => ['domain' => '—', 'how' => 'Bluesky has no short domain; the AT-protocol post URL is '
            .'the canonical link.'],
    ];

    public static function key(): string
    {
        return 'utility.social-link-shortener';
    }

    public function cacheTtl(): int
    {
        return 86400;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Social media link',
                    'description' => 'A post, video, Pin, profile or channel link from any major platform.',
                    'minLength' => 6,
                    'maxLength' => 900,
                    'examples' => ['https://www.instagram.com/p/Cxyz1234567/?igshid=abc123'],
                ],
                'strip_tracking' => [
                    'type' => 'boolean',
                    'title' => 'Remove tracking parameters',
                    'description' => 'Drops utm_*, igshid, si, fbclid and the rest before shortening.',
                    'default' => true,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));
        $identity = SocialUrl::identify($raw);

        if ($identity['host'] === null) {
            throw ToolExecutionException::invalidInput(
                'That is not a URL we can read. Paste the whole link, including the domain.',
                ['url' => 'Expected a link such as https://www.instagram.com/p/Cxyz1234567/'],
            );
        }

        $stripped = $input->bool('strip_tracking', true)
            ? SocialUrl::stripTracking($raw, keep: ['v', 't', 'list'])
            : ['url' => SocialUrl::normalise($raw), 'removed' => []];

        $platform = $identity['platform'];
        $short = $platform === null ? null : $this->shorten($platform, $stripped['url'], $identity['path']);

        $blocks = [];

        if ($short !== null) {
            $blocks[] = ['label' => 'Short link — '.parse_url($short, PHP_URL_HOST), 'text' => $short];
        }

        $blocks[] = ['label' => 'Clean link'.($stripped['removed'] === [] ? '' : ' — tracking removed'),
            'text' => $stripped['url']];

        if ($short !== null) {
            $blocks[] = ['label' => 'Markdown', 'text' => '['.($identity['label'] ?? 'Link').']('.$short.')'];
            $blocks[] = ['label' => 'HTML link',
                'text' => '<a href="'.htmlspecialchars($short, ENT_QUOTES | ENT_HTML5).'" rel="noopener">'
                    .htmlspecialchars($identity['label'] ?? 'Link', ENT_QUOTES | ENT_HTML5).'</a>'];
        }

        $saved = mb_strlen($raw) - mb_strlen($short ?? $stripped['url']);

        return ToolResult::textBlocks($blocks, summary: $this->summary($identity, $short, $saved))
            ->withMeta([
                'platform' => $platform,
                'kind' => $identity['kind'],
                'short_url' => $short,
                'clean_url' => $stripped['url'],
                'characters_saved' => max(0, $saved),
                'removed_parameters' => $stripped['removed'],
            ])
            ->withWarnings($this->warnings($identity, $short, $stripped['removed']));
    }

    /**
     * @param  array{platform: ?string, label: ?string, kind: ?string, host: ?string, path: string, url: string}  $identity
     */
    private function summary(array $identity, ?string $short, int $saved): string
    {
        if ($short !== null) {
            return sprintf(
                '%s %s → %s%s.',
                $identity['label'],
                $identity['kind'] ?? 'link',
                $short,
                $saved > 0 ? ' ('.$saved.' characters shorter)' : '',
            );
        }

        if ($identity['platform'] === null) {
            return 'That link is not from a platform we recognise, so there is no first-party short '
                .'domain to build. The cleaned link is below.';
        }

        return $identity['label'].' has no short link you can construct from a URL — '
            .'the cleaned link below is the shortest form you can share.';
    }

    /**
     * @param  array{platform: ?string, label: ?string, kind: ?string, host: ?string, path: string, url: string}  $identity
     * @param  list<string>  $removed
     * @return list<string>
     */
    private function warnings(array $identity, ?string $short, array $removed): array
    {
        $warnings = [];

        if ($removed !== []) {
            $warnings[] = 'Removed '.implode(', ', $removed).'. Several of those are per-share tracking '
                .'ids, so a forwarded link carrying them tells the platform who forwarded it.';
        }

        $platform = $identity['platform'];

        if ($short === null && $platform !== null && isset(self::ISSUED_ONLY[$platform])) {
            $entry = self::ISSUED_ONLY[$platform];

            $warnings[] = $identity['label'].' short links ('.$entry['domain'].') cannot be built from a '
                .'URL. '.$entry['how'];
        }

        if ($platform === 'instagram' && $short !== null) {
            $warnings[] = 'instagr.am is Instagram\'s own legacy domain and still redirects, but it is not '
                .'what the app\'s share sheet hands out. Use it where character count matters; use the full '
                .'instagram.com link where the domain being recognisable matters more.';
        }

        return $warnings;
    }

    private function shorten(string $platform, string $url, string $path): ?string
    {
        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);

        return match ($platform) {
            'youtube' => $this->youtube($url),
            'instagram' => $this->instagram($path),
            'reddit' => $this->reddit($path),
            'dailymotion' => $this->dailymotion($path),
            'flickr' => $this->flickr($path),
            // Both of these already *are* the short domain, so the useful move is
            // normalising a long form onto it rather than inventing a shorter one.
            'telegram' => 'https://t.me'.rtrim($path, '/'),
            'whatsapp' => preg_match('#^/(\+?\d{6,15})#', $path, $m) === 1 ? 'https://wa.me/'.ltrim($m[1], '+') : null,
            default => null,
        };
    }

    private function youtube(string $url): ?string
    {
        $videoId = YouTubeUrl::videoId($url);

        if ($videoId === null) {
            return null;
        }

        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);
        $t = $query['t'] ?? null;
        $seconds = is_string($t) && preg_match('/^(\d+)s?$/', $t, $m) === 1 ? (int) $m[1] : null;

        return 'https://youtu.be/'.$videoId.($seconds !== null && $seconds > 0 ? '?t='.$seconds : '');
    }

    /** `instagram.com/p/ABC` → `instagr.am/p/ABC`. Same path, four characters less domain. */
    private function instagram(string $path): ?string
    {
        return preg_match('#^/(?:[^/]+/)?(p|reels?|tv)/([\w-]+)#', $path, $match) === 1
            ? 'https://instagr.am/'.($match[1] === 'reels' ? 'reel' : $match[1]).'/'.$match[2]
            : (preg_match('#^/([\w.]{1,30})/?$#', $path, $match) === 1 ? 'https://instagr.am/'.$match[1] : null);
    }

    /** `reddit.com/r/sub/comments/abc123/title/` → `redd.it/abc123`. */
    private function reddit(string $path): ?string
    {
        if (preg_match('#^/r/[^/]+/comments/([a-z0-9]{5,10})#i', $path, $match) === 1) {
            return 'https://redd.it/'.$match[1];
        }

        return preg_match('#^/([a-z0-9]{5,10})/?$#i', $path, $match) === 1 ? 'https://redd.it/'.$match[1] : null;
    }

    private function dailymotion(string $path): ?string
    {
        return preg_match('#^/video/([a-z0-9]+)#i', $path, $match) === 1 ? 'https://dai.ly/'.$match[1] : null;
    }

    /**
     * `flickr.com/photos/user/1234567` → `flic.kr/p/<base58 of 1234567>`.
     *
     * Flickr's short URL is the photo id in base58 over an alphabet with no 0, O,
     * I or l — the characters people mistype when they read a link aloud. The
     * encoding is documented and deterministic, so no request is needed.
     */
    private function flickr(string $path): ?string
    {
        if (preg_match('#^/photos/[^/]+/(\d+)#', $path, $match) !== 1) {
            return null;
        }

        $number = (int) $match[1];

        if ($number <= 0) {
            return null;
        }

        $encoded = '';
        $base = mb_strlen(self::BASE58);

        while ($number > 0) {
            $encoded = self::BASE58[$number % $base].$encoded;
            $number = intdiv($number, $base);
        }

        return 'https://flic.kr/p/'.$encoded;
    }
}
