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
 * Official embed code for a post from any platform, built from its URL.
 *
 * Every platform has an embed. Almost none of them put it somewhere you can find:
 * X hides it behind a menu that a logged-out visitor never sees, Instagram removed
 * it from the web UI entirely, TikTok's is three clicks into the share sheet, and
 * LinkedIn's requires you to know that a post URL contains an activity URN and that
 * the embed path wants the URN rather than the URL.
 *
 * Two shapes come back for most platforms, because they fail in different places:
 *
 * - The **blockquote + script** form is what each platform documents. It renders
 *   the real post — avatar, media, live like counts — and it loads third-party
 *   JavaScript to do it, which is a consent problem in the EU and a performance
 *   problem everywhere.
 * - The **iframe** form, where the platform publishes one, renders in a sandbox
 *   with no script on your page. It is the one to reach for in a CMS that strips
 *   `<script>`, in AMP, and anywhere a privacy review has to sign the page off.
 *
 * The fallback for both is a plain link, and that is not a consolation prize: an
 * embed that never loads because the visitor blocked the script leaves a hole where
 * the quote was, and a linked quotation does not.
 *
 * YouTube has its own tool with the full parameter set (autoplay, start and end
 * points, the privacy-enhanced domain). A YouTube URL pasted here gets a working
 * embed and a pointer to it.
 */
final class SocialEmbedCodeGeneratorRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'utility.embed-code-generator';
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
                    'title' => 'Post URL',
                    'description' => 'A post, video, Reel, Pin or thread from X, Instagram, TikTok, '
                        .'Facebook, LinkedIn, Pinterest, Reddit, Threads, YouTube, Vimeo, Dailymotion or Twitch.',
                    'minLength' => 6,
                    'maxLength' => 900,
                    'examples' => ['https://x.com/NASA/status/1234567890123456789'],
                ],
                'width' => [
                    'type' => 'integer',
                    'title' => 'Width (px)',
                    'description' => 'Used by the fixed-size embeds. The responsive ones ignore it.',
                    'minimum' => 200,
                    'maximum' => 1600,
                    'default' => 550,
                ],
                'theme' => [
                    'type' => 'string',
                    'title' => 'Theme',
                    'description' => 'Honoured by X and Reddit; the others follow the post itself.',
                    'enum' => ['light', 'dark'],
                    'default' => 'light',
                ],
                'parent_domain' => [
                    'type' => 'string',
                    'title' => 'Your domain',
                    'description' => 'Only needed for Twitch, which refuses to play unless the embed names '
                        .'the domain it is on. Leave blank for every other platform.',
                    'maxLength' => 120,
                    'default' => '',
                    'examples' => ['example.com'],
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
                ['url' => 'Expected a link such as https://x.com/NASA/status/1234567890123456789'],
            );
        }

        // Tracking parameters inside an embed are worse than useless: they are
        // baked into every page view of the embedding site forever.
        $url = SocialUrl::stripTracking($raw, keep: ['v', 't', 'list'])['url'];
        $platform = $identity['platform'];

        if ($platform === null) {
            throw ToolExecutionException::invalidInput(
                'That link is not from a platform we can build an embed for.',
                ['url' => 'Unrecognised platform.'],
            );
        }

        $width = max(200, min(1600, $input->int('width', 550)));
        $blocks = $this->blocks($platform, $url, $identity['path'], $width, $input);

        if ($blocks === []) {
            throw ToolExecutionException::invalidInput(
                SocialUrl::label($platform).' publishes an embed, but not for that kind of link. '
                .'Use the URL of a single post or video rather than a profile or feed.',
                ['url' => 'Expected a post or video URL.'],
            );
        }

        $blocks[] = [
            'label' => 'Plain link — the fallback worth keeping when the embed cannot load',
            'text' => '<a href="'.$this->escape($url).'" rel="noopener nofollow">View this post on '
                .$this->escape(SocialUrl::label($platform)).'</a>',
        ];

        return ToolResult::textBlocks($blocks, summary: sprintf(
            // Leading with the platform rather than an article: "a X (Twitter)
            // post" is what the obvious phrasing produces, and it reads as a typo.
            '%s %s — embed code in %d format%s.',
            SocialUrl::label($platform),
            $identity['kind'] ?? 'post',
            count($blocks),
            count($blocks) === 1 ? '' : 's',
        ))->withMeta([
            'platform' => $platform,
            'kind' => $identity['kind'],
            'clean_url' => $url,
        ])->withWarnings($this->warnings($platform, $input));
    }

    /** @return list<array{label: string, text: string}> */
    private function blocks(string $platform, string $url, string $path, int $width, ToolInput $input): array
    {
        $dark = $input->string('theme', 'light') === 'dark';

        return match ($platform) {
            'x' => $this->x($url, $width, $dark),
            'instagram' => $this->instagram($url, $path, $width),
            'tiktok' => $this->tiktok($url, $path, $width),
            'facebook' => $this->facebook($url, $width),
            'linkedin' => $this->linkedin($path, $width),
            'pinterest' => $this->pinterest($url),
            'reddit' => $this->reddit($url, $dark),
            'threads' => $this->threads($url),
            'youtube' => $this->youtube($url, $width),
            'vimeo' => $this->frame($this->vimeoSrc($path), $width, 'Vimeo'),
            'dailymotion' => $this->frame($this->dailymotionSrc($path), $width, 'Dailymotion'),
            'twitch' => $this->twitch($path, $width, trim($input->string('parent_domain'))),
            default => [],
        };
    }

    /** @return list<array{label: string, text: string}> */
    private function x(string $url, int $width, bool $dark): array
    {
        if (preg_match('#/status(?:es)?/(\d+)#', $url) !== 1) {
            return [];
        }

        // X's widget reads the *anchor's* href, not the blockquote's, and it needs
        // the anchor to be the last child. Getting either wrong renders the raw
        // quote with no styling, which is the single most common broken X embed.
        $theme = $dark ? ' data-theme="dark"' : '';

        return [
            [
                'label' => 'Official embed — renders the live post',
                'text' => '<blockquote class="twitter-tweet"'.$theme.' data-width="'.$width.'">'."\n"
                    .'  <a href="'.$this->escape($url).'"></a>'."\n"
                    .'</blockquote>'."\n"
                    .'<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>',
            ],
            [
                'label' => 'One script for the whole page — if you embed more than one post',
                'text' => '<blockquote class="twitter-tweet"'.$theme.'>'."\n"
                    .'  <a href="'.$this->escape($url).'"></a>'."\n"
                    .'</blockquote>'."\n\n"
                    .'<!-- Include widgets.js once, near </body>, not per embed. -->',
            ],
        ];
    }

    /** @return list<array{label: string, text: string}> */
    private function instagram(string $url, string $path, int $width): array
    {
        if (preg_match('#/(p|reels?|tv)/([\w-]+)#', $path, $match) !== 1) {
            return [];
        }

        $shortcode = $match[2];
        $kind = $match[1] === 'reels' ? 'reel' : $match[1];
        $permalink = "https://www.instagram.com/{$kind}/{$shortcode}/";

        return [
            [
                'label' => 'Official embed — post, caption and comments',
                'text' => '<blockquote class="instagram-media" data-instgrm-permalink="'
                    .$this->escape($permalink).'" data-instgrm-version="14" '
                    .'style="max-width:'.$width.'px;width:100%;"></blockquote>'."\n"
                    .'<script async src="https://www.instagram.com/embed.js"></script>',
            ],
            [
                'label' => 'Media only — no caption, no comment thread',
                'text' => '<blockquote class="instagram-media" data-instgrm-permalink="'
                    .$this->escape($permalink).'" data-instgrm-version="14" data-instgrm-captioned="false" '
                    .'style="max-width:'.$width.'px;width:100%;"></blockquote>'."\n"
                    .'<script async src="https://www.instagram.com/embed.js"></script>',
            ],
            [
                'label' => 'Iframe — no script on your page, for a CMS that strips them',
                'text' => '<iframe src="'.$this->escape($permalink).'embed/" width="'.$width.'" height="'
                    .(int) round($width * 1.4).'" frameborder="0" scrolling="no" allowtransparency="true" '
                    .'loading="lazy" title="Instagram post"></iframe>',
            ],
        ];
    }

    /** @return list<array{label: string, text: string}> */
    private function tiktok(string $url, string $path, int $width): array
    {
        if (preg_match('#/(?:video|photo)/(\d+)#', $path, $match) !== 1) {
            return [];
        }

        $id = $match[1];

        return [
            [
                'label' => 'Official embed — plays in place',
                'text' => '<blockquote class="tiktok-embed" cite="'.$this->escape($url).'" data-video-id="'
                    .$id.'" style="max-width:'.$width.'px;min-width:325px;">'."\n"
                    .'  <section></section>'."\n"
                    .'</blockquote>'."\n"
                    .'<script async src="https://www.tiktok.com/embed.js"></script>',
            ],
            [
                'label' => 'Iframe — no script on your page',
                'text' => '<iframe src="https://www.tiktok.com/embed/v2/'.$id.'" width="'.$width.'" height="'
                    .(int) round($width * 1.77).'" frameborder="0" allow="encrypted-media;" '
                    .'loading="lazy" title="TikTok video"></iframe>',
            ],
        ];
    }

    /** @return list<array{label: string, text: string}> */
    private function facebook(string $url, int $width): array
    {
        $encoded = rawurlencode($url);
        $isVideo = str_contains($url, '/videos/') || str_contains($url, '/watch') || str_contains($url, '/reel/');
        $plugin = $isVideo ? 'video' : 'post';

        return [
            [
                'label' => 'Iframe plugin — the form that needs no SDK',
                'text' => '<iframe src="https://www.facebook.com/plugins/'.$plugin.'.php?href='.$encoded
                    .'&amp;width='.$width.'&amp;show_text=true" width="'.$width.'" height="'
                    .(int) round($width * 1.3).'" style="border:none;overflow:hidden" scrolling="no" '
                    .'frameborder="0" allowfullscreen="true" loading="lazy" '
                    .'allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" '
                    .'title="Facebook '.$plugin.'"></iframe>',
            ],
            [
                'label' => 'SDK embed — needs the Facebook JavaScript SDK on the page',
                'text' => '<div class="fb-'.$plugin.'" data-href="'.$this->escape($url).'" data-width="'
                    .$width.'" data-show-text="true"></div>',
            ],
        ];
    }

    /**
     * LinkedIn's embed path wants the activity URN, which is buried in the post URL.
     *
     * @return list<array{label: string, text: string}>
     */
    private function linkedin(string $path, int $width): array
    {
        if (preg_match('#(?:activity|ugcPost|share)[:-](\d{15,25})#', $path, $match) !== 1) {
            return [];
        }

        $urn = 'urn:li:activity:'.$match[1];
        $src = 'https://www.linkedin.com/embed/feed/update/'.$urn;

        return [
            [
                'label' => 'Iframe — LinkedIn publishes no script-based embed',
                'text' => '<iframe src="'.$this->escape($src).'" width="'.$width.'" height="'
                    .(int) round($width * 1.15).'" frameborder="0" allowfullscreen loading="lazy" '
                    .'title="LinkedIn post"></iframe>',
            ],
            ['label' => 'Activity URN — for tooling that wants the identifier rather than the URL',
                'text' => $urn],
        ];
    }

    /** @return list<array{label: string, text: string}> */
    private function pinterest(string $url): array
    {
        if (! str_contains($url, '/pin/')) {
            return [];
        }

        return [
            [
                'label' => 'Official Pin embed — large',
                'text' => '<a data-pin-do="embedPin" data-pin-width="large" href="'.$this->escape($url).'"></a>'."\n"
                    .'<script async defer src="https://assets.pinterest.com/js/pinit.js"></script>',
            ],
            [
                'label' => 'Small — for a sidebar or an inline mention',
                'text' => '<a data-pin-do="embedPin" data-pin-width="small" href="'.$this->escape($url).'"></a>'."\n"
                    .'<script async defer src="https://assets.pinterest.com/js/pinit.js"></script>',
            ],
        ];
    }

    /** @return list<array{label: string, text: string}> */
    private function reddit(string $url, bool $dark): array
    {
        if (! str_contains($url, '/comments/')) {
            return [];
        }

        return [
            [
                'label' => 'Official embed — post and top comments',
                'text' => '<blockquote class="reddit-embed-bq" data-embed-theme="'.($dark ? 'dark' : 'light')
                    .'">'."\n"
                    .'  <a href="'.$this->escape($url).'">View the post on Reddit</a>'."\n"
                    .'</blockquote>'."\n"
                    .'<script async src="https://embed.reddit.com/widgets.js" charset="UTF-8"></script>',
            ],
        ];
    }

    /** @return list<array{label: string, text: string}> */
    private function threads(string $url): array
    {
        if (! str_contains($url, '/post/') && ! str_contains($url, '/t/')) {
            return [];
        }

        return [
            [
                'label' => 'Official embed',
                'text' => '<blockquote class="text-post-media" data-text-post-permalink="'
                    .$this->escape($url).'" data-text-post-version="0">'."\n"
                    .'  <a href="'.$this->escape($url).'"></a>'."\n"
                    .'</blockquote>'."\n"
                    .'<script async src="https://www.threads.com/embed.js"></script>',
            ],
        ];
    }

    /** @return list<array{label: string, text: string}> */
    private function youtube(string $url, int $width): array
    {
        $videoId = YouTubeUrl::videoId($url);

        if ($videoId === null) {
            return [];
        }

        $src = "https://www.youtube-nocookie.com/embed/{$videoId}";

        return [
            [
                'label' => 'Responsive iframe — privacy-enhanced domain',
                'text' => '<div style="position:relative;width:100%;aspect-ratio:16/9;">'."\n"
                    .'  <iframe style="position:absolute;inset:0;width:100%;height:100%;" src="'
                    .$this->escape($src).'" title="YouTube video player" frameborder="0" loading="lazy" '
                    .'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; '
                    .'picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" '
                    .'allowfullscreen></iframe>'."\n"
                    .'</div>',
            ],
            ...$this->frame($src, $width, 'YouTube'),
        ];
    }

    /** @return list<array{label: string, text: string}> */
    private function twitch(string $path, int $width, string $parent): array
    {
        $parent = $parent === '' ? 'example.com' : (SocialUrl::host($parent) ?? $parent);

        if (preg_match('#^/videos/(\d+)#', $path, $match) === 1) {
            $src = 'https://player.twitch.tv/?video='.$match[1].'&parent='.rawurlencode($parent);
        } elseif (preg_match('#^/([^/]+)/clip/([\w-]+)#', $path, $match) === 1) {
            $src = 'https://clips.twitch.tv/embed?clip='.rawurlencode($match[2]).'&parent='.rawurlencode($parent);
        } elseif (preg_match('#^/([\w]{2,30})/?$#', $path, $match) === 1) {
            $src = 'https://player.twitch.tv/?channel='.rawurlencode($match[1]).'&parent='.rawurlencode($parent);
        } else {
            return [];
        }

        return $this->frame($src, $width, 'Twitch');
    }

    private function vimeoSrc(string $path): string
    {
        preg_match('#/(\d+)#', $path, $match);

        return 'https://player.vimeo.com/video/'.($match[1] ?? '');
    }

    private function dailymotionSrc(string $path): string
    {
        preg_match('#/(?:video/)?([a-z0-9]+)#i', $path, $match);

        return 'https://www.dailymotion.com/embed/video/'.($match[1] ?? '');
    }

    /** @return list<array{label: string, text: string}> */
    private function frame(string $src, int $width, string $label): array
    {
        if (str_ends_with($src, '/')) {
            return [];
        }

        return [[
            'label' => 'Fixed-size iframe — '.$width.' × '.(int) round($width * 9 / 16),
            'text' => '<iframe src="'.$this->escape($src).'" width="'.$width.'" height="'
                .(int) round($width * 9 / 16).'" frameborder="0" loading="lazy" '
                .'allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="'
                .$this->escape($label).' player"></iframe>',
        ]];
    }

    /** @return list<string> */
    private function warnings(string $platform, ToolInput $input): array
    {
        $warnings = [
            'Every embed here loads content from '.SocialUrl::label($platform).', which sets cookies and '
            .'sees your visitor\'s IP. Under GDPR that usually needs consent before the embed renders — '
            .'load it behind a click-to-load placeholder if your site has a consent banner.',
        ];

        if ($platform === 'twitch' && trim($input->string('parent_domain')) === '') {
            $warnings[] = 'Twitch refuses to play unless the embed URL names the domain it is on. '
                .'`parent=example.com` above is a placeholder — replace it with your own domain, and add '
                .'one `parent=` for every domain the page is served from, localhost included.';
        }

        if (in_array($platform, ['instagram', 'facebook', 'threads'], true)) {
            $warnings[] = 'Meta\'s embeds only render public posts. If the account is private, or the post '
                .'is later deleted or set to private, the embed becomes an empty box on your page.';
        }

        if ($platform === 'youtube') {
            $warnings[] = 'The YouTube Embed Code Generator has the full parameter set — start and end '
                .'points, autoplay, loop, controls — if you need more than the default player.';
        }

        if ($platform === 'x') {
            $warnings[] = 'X\'s widget reads the URL from the anchor inside the blockquote, not from the '
                .'blockquote itself. Editors that "clean up" empty anchors will silently break it.';
        }

        return $warnings;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
