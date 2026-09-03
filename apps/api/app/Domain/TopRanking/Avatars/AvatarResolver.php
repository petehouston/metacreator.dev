<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Avatars;

use App\Domain\TopRanking\Enums\RankingPlatform;
use App\Domain\TopRanking\Models\TopRankingEntry;
use App\Support\Http\SafeHttpClient;
use App\Support\Social\CdnImage;
use App\Support\Social\YouTubePage;
use Carbon\CarbonImmutable;

/**
 * Finds the picture an account already publishes about itself.
 *
 * **What this stores, and what it does not.** The result is a *link* to the
 * platform's own CDN, never a copy on our disk. That is the deliberate trade: no
 * storage cost, no rehosting of someone else's likeness, and a picture that
 * updates when the account updates it. What it buys in exchange is a dependency on
 * a URL we do not control, and two of these platforms sign their URLs with an
 * expiry — which is why {@see ResolvedAvatar} carries one and why the weekly job
 * exists. A reader never sees a broken image: the entry falls back to a monogram
 * the moment the link is past its date.
 *
 * **Every platform is read from a public, keyless endpoint.** Six of the seven are
 * the `og:image` these sites put on a profile page for the benefit of any link
 * preview — the same tag Slack and iMessage read. Bluesky is better still: it
 * publishes an unauthenticated XRPC API. No credentials are configured anywhere in
 * this feature, and none are needed.
 *
 * **The host allowlist is not decoration.** Instagram, X and Facebook answer some
 * anonymous requests with a login wall, and a login wall carries an `og:image` of
 * its own — the platform's logo. Storing that would put the identical picture on
 * all fifty rows and look, from the outside, exactly like a working feature. So a
 * result is only accepted when it comes from the CDN that platform serves *user*
 * pictures from, and the known placeholder paths are rejected by name.
 */
final class AvatarResolver
{
    /**
     * The hosts each platform serves real profile pictures from.
     *
     * Matched as a domain suffix, so `scontent-dfw6-2.cdninstagram.com` and
     * `scontent-lhr8-1.cdninstagram.com` — Meta answers from whichever edge is
     * nearest, and the name changes between requests — both pass on one entry.
     *
     * @var array<string, list<string>>
     */
    private const CDN_HOSTS = [
        'youtube' => ['yt3.googleusercontent.com', 'yt3.ggpht.com', 'ggpht.com', 'i.ytimg.com'],
        'instagram' => ['cdninstagram.com', 'fbcdn.net'],
        'tiktok' => ['tiktokcdn.com', 'tiktokcdn-us.com', 'ibyteimg.com', 'muscdn.com'],
        'x' => ['pbs.twimg.com'],
        'facebook' => ['fbcdn.net'],
        'twitch' => ['static-cdn.jtvnw.net', 'jtvnw.net'],
        'bluesky' => ['cdn.bsky.app'],
    ];

    /**
     * Path fragments that mean "this is the platform, not the person".
     *
     * `rsrc.php` is where Meta serves its own product artwork; `default_profile`
     * is X's egg. Both live on hosts that are otherwise legitimate, so the host
     * check alone would let them through.
     */
    private const PLACEHOLDERS = ['/rsrc.php/', 'default_profile', '/emoji/', 'sticky/default'];

    public function resolve(TopRankingEntry $entry, RankingPlatform $platform): ResolvedAvatar
    {
        $result = match ($platform) {
            // The one platform with a real API for this. Answered without a key,
            // without a rate limit worth the name, and in JSON — so it is used in
            // preference to scraping a page that would also have worked.
            RankingPlatform::Bluesky => $this->fromBluesky($entry),

            // TikTok returns a 200 with an empty shell to an unrecognised agent,
            // and puts the picture in an embedded JSON blob rather than a meta tag.
            RankingPlatform::TikTok => $this->fromTikTokPage($entry),

            default => $this->fromOpenGraph($entry, $platform),
        };

        return $result;
    }

    /**
     * The `og:image` on the account's own profile page.
     *
     * This is the same tag every chat app reads to draw a link preview, which is
     * what makes it a reasonable thing to ask for: it is published *in order to be*
     * fetched by third parties.
     */
    private function fromOpenGraph(TopRankingEntry $entry, RankingPlatform $platform): ResolvedAvatar
    {
        $url = $entry->profile_url;

        if ($url === null) {
            return ResolvedAvatar::unavailable();
        }

        $response = SafeHttpClient::attempt($url, timeout: 12.0);

        if ($response === null || $response->failed()) {
            return ResolvedAvatar::unavailable();
        }

        $head = SafeHttpClient::body($response, $this->readCap($platform));

        if (preg_match('/<meta[^>]+property="og:image"[^>]+content="([^"]+)"/i', $head, $match) !== 1
            && preg_match('/<meta[^>]+content="([^"]+)"[^>]+property="og:image"/i', $head, $match) !== 1) {
            return ResolvedAvatar::unavailable();
        }

        $image = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->accept($image, $platform, 'og:image');
    }

    /**
     * How far into a profile page the meta tags might be.
     *
     * On six of these platforms the head is the head: `og:image` lands inside the
     * first few kilobytes, and reading further is bytes spent finding nothing.
     * YouTube is the exception, and not by a little — it ships roughly 750 kB of
     * inline configuration *before* its meta tags, so a sensible cap silently
     * returns "no avatar" for every channel on the site.
     * {@see YouTubePage} hit the same wall for the same
     * reason and documents it in the same terms.
     */
    private function readCap(RankingPlatform $platform): int
    {
        return $platform === RankingPlatform::YouTube ? 2_000_000 : 400_000;
    }

    /**
     * TikTok's profile picture, out of the state blob its page ships with.
     *
     * There is no `og:image` on this page for a non-browser agent and no keyless
     * API, so the remaining public source is the JSON TikTok embeds to hydrate its
     * own React app. `avatarLarger` is the 1080px original; the same blob carries
     * smaller crops, and the larger one is chosen because the table renders it at
     * 2× on a retina screen.
     */
    private function fromTikTokPage(TopRankingEntry $entry): ResolvedAvatar
    {
        $url = $entry->profile_url;

        if ($url === null) {
            return ResolvedAvatar::unavailable();
        }

        $response = SafeHttpClient::attempt($url, timeout: 15.0, userAgent: SafeHttpClient::BROWSER_USER_AGENT);

        if ($response === null || $response->failed()) {
            return ResolvedAvatar::unavailable();
        }

        $body = SafeHttpClient::body($response, 1_500_000);

        if (preg_match('/"avatarLarger"\s*:\s*"([^"]+)"/', $body, $match) !== 1) {
            return ResolvedAvatar::unavailable();
        }

        // The blob is JSON embedded in HTML, so its slashes arrive as `/`.
        $image = json_decode('"'.$match[1].'"');

        return is_string($image)
            ? $this->accept($image, RankingPlatform::TikTok, 'page-state')
            : ResolvedAvatar::unavailable();
    }

    /** Bluesky's public actor record. No key, no session, plain JSON. */
    private function fromBluesky(TopRankingEntry $entry): ResolvedAvatar
    {
        $actor = $entry->handle;

        if ($actor === null || $actor === '') {
            return ResolvedAvatar::unavailable();
        }

        $response = SafeHttpClient::attempt(
            'https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile?actor='.rawurlencode($actor),
            timeout: 10.0,
        );

        if ($response === null || $response->failed()) {
            return ResolvedAvatar::unavailable();
        }

        $avatar = $response->json('avatar');

        return is_string($avatar) && $avatar !== ''
            ? $this->accept($avatar, RankingPlatform::Bluesky, 'bsky-api')
            : ResolvedAvatar::unavailable();
    }

    /**
     * Decide whether a URL is genuinely this account's picture.
     *
     * The two checks are the whole reason this method exists rather than the
     * callers returning `ResolvedAvatar::found()` directly: without them the
     * feature fails silently and convincingly, with a page of identical logos.
     */
    private function accept(string $url, RankingPlatform $platform, string $source): ResolvedAvatar
    {
        if (! str_starts_with($url, 'https://')) {
            return ResolvedAvatar::unavailable();
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        // Keyed by every enum case, so this cannot miss — a new platform without an
        // entry is a compile-time gap in the map above, not a silent empty allowlist.
        $allowed = self::CDN_HOSTS[$platform->value];
        $onCdn = false;

        foreach ($allowed as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                $onCdn = true;

                break;
            }
        }

        if (! $onCdn) {
            return ResolvedAvatar::unavailable();
        }

        foreach (self::PLACEHOLDERS as $fragment) {
            if (str_contains($url, $fragment)) {
                return ResolvedAvatar::unavailable();
            }
        }

        return ResolvedAvatar::found(substr($url, 0, 1000), $source, $this->expiry($url));
    }

    /**
     * When a signed URL stops working, read out of the URL itself.
     *
     * Two conventions, because two companies. Meta hex-encodes an `oe` parameter,
     * which {@see CdnImage} already knows how to read — it was written for the
     * image-downloader tools, which hit exactly this wall. TikTok writes a plain
     * `x-expires` in seconds. Everything else here is unsigned and returns null,
     * which is the honest answer: those links do not expire on a schedule.
     */
    private function expiry(string $url): ?CarbonImmutable
    {
        $meta = CdnImage::expiresAt($url);

        if ($meta !== null) {
            return CarbonImmutable::createFromTimestamp($meta);
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $tiktok = $query['x-expires'] ?? null;

        if (is_string($tiktok) && ctype_digit($tiktok)) {
            $timestamp = (int) $tiktok;

            // A signature dated before now, or years out, is not an expiry we
            // understand — and acting on a misread would hide a working picture.
            if ($timestamp > time() && $timestamp < time() + (400 * 86400)) {
                return CarbonImmutable::createFromTimestamp($timestamp);
            }
        }

        return null;
    }
}
