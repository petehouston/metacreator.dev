<?php

declare(strict_types=1);

namespace App\Domain\Seo\Services;

use App\Domain\Tools\Models\Tool;
use App\Support\Media\SocialCardCanvas;

/**
 * The open-graph card for a tool page — the "Centred browser" design.
 *
 * The card *is* a browser: chrome bar edge to edge with the page's real URL in it,
 * the tool centred underneath on the app's own dark ground, and three stickers
 * pasted across the bottom. The reasoning behind the design, and the ninety-odd
 * alternatives it was chosen from, is in `marketing/tools/seo/tool-images`.
 *
 * Three rules the drawing obeys, all of them about the card being read at 360px
 * wide in somebody's timeline rather than at full size in a design tool:
 *
 * - **One line each.** The tool name and the tagline shrink to fit rather than
 *   wrap, so the logo sits at the same height on all ninety cards and the set
 *   reads as one publisher.
 * - **The domain travels.** metacreator.dev appears twice — in the URL bar and on
 *   a sticker — because most impressions never turn into a click, and the name is
 *   the only thing they leave behind.
 * - **One foreign colour, once.** The platform's own primary appears on exactly
 *   one sticker and in the wash behind the frame. Everything else is ours.
 */
final class ToolSocialCard
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** The app's dark ground: ink-1000 → ink-900, the same ramp as globals.css. */
    private const GROUND = ['#0f131c', '#161b27', '#1b2130'];

    private const FOREGROUND = '#f4f6fb';

    private const MUTED = '#98a2b8';

    private const EDGE = '#3a4152';

    /**
     * Each network's own primary, and the ink that stays legible on it.
     *
     * X and Threads are black-on-white brands; on our dark ground their sticker
     * takes the brand's off-white with dark text, which is the same recognition
     * and the only version that is legible on the ground we actually ship.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PLATFORMS = [
        'youtube' => ['#ff0033', '#ffffff'],
        'instagram' => ['#e1306c', '#ffffff'],
        'tiktok' => ['#fe2c55', '#ffffff'],
        'x' => ['#e7e9ea', '#0f1419'],
        'twitter' => ['#e7e9ea', '#0f1419'],
        'facebook' => ['#1877f2', '#ffffff'],
        'linkedin' => ['#0a66c2', '#ffffff'],
        'threads' => ['#f5f5f5', '#101010'],
        'pinterest' => ['#e60023', '#ffffff'],
        'bluesky' => ['#0085ff', '#ffffff'],
        'twitch' => ['#9146ff', '#ffffff'],
        'spotify' => ['#1db954', '#08210f'],
        'apple-podcasts' => ['#9933cc', '#ffffff'],
        'snapchat' => ['#fffc00', '#101010'],
    ];

    /** Tools that serve every network get the brand's own cobalt instead. */
    private const HOUSE = ['#3d80f7', '#0f131c'];

    private string $fontDir;

    private string $siteUrl;

    /**
     * Both dependencies have a sensible default so the container can build this
     * without a binding; tests pass their own font directory.
     */
    public function __construct(?string $fontDir = null, ?string $siteUrl = null)
    {
        $this->fontDir = $fontDir ?? resource_path('fonts');
        $this->siteUrl = $siteUrl ?? (string) config('app.frontend_url');
    }

    /**
     * Draw the card and return the encoded bytes.
     *
     * @return array{bytes: string, extension: string, mime: string, width: int, height: int, alt: string}
     */
    public function render(Tool $tool): array
    {
        [$accent, $onAccent] = $this->palette($tool);

        $card = new SocialCardCanvas(self::WIDTH, self::HEIGHT, $this->fontDir);

        $card->gradient(self::GROUND, 135);
        $card->glow(self::WIDTH / 2, 0, 560, 380, $accent, 0.16);

        $this->chrome($card, $tool);
        $this->body($card, $tool, $accent);
        $this->stickers($card, $accent, $onAccent);

        return $this->encode($card, $tool);
    }

    /** The URL bar. It is also the only place the full page address appears. */
    private function chrome(SocialCardCanvas $card, Tool $tool): void
    {
        $card->rect(0, 0, self::WIDTH, 86, '#ffffff', 0.04);
        $card->rect(0, 84, self::WIDTH, 2, self::EDGE);

        foreach ([44, 70, 96] as $x) {
            $card->circle($x, 43, 14, self::MUTED, 0.5);
        }

        $url = $this->displayUrl($tool);
        $font = 'JetBrainsMono-Regular';
        $size = $card->fitSize($url, 620, 19, 14, $font);
        $width = $card->textWidth($url, $size, $font) + 52;

        $card->roundedRect(self::WIDTH / 2 - $width / 2, 21, $width, 44, 22, '#ffffff', 0.07);
        $card->text($url, self::WIDTH / 2, 33, $size, $font, self::MUTED, align: 'center');
    }

    /** Mark, wordmark, name, tagline, and a mock of the tool's own answer. */
    private function body(SocialCardCanvas $card, Tool $tool, string $accent): void
    {
        $safe = self::WIDTH - 96;

        // The brand lockup: mark and wordmark measured together, then centred as
        // one unit, so the pair reads as a logo rather than two centred things.
        $wordmark = 'MetaCreator.dev';
        $wordWidth = $card->textWidth($wordmark, 24, 'DMSans-Bold');
        $lockup = 44 + 13 + $wordWidth;
        $lockupX = (self::WIDTH - $lockup) / 2;

        $card->mark($lockupX, 168, 44);
        $card->text($wordmark, $lockupX + 57, 179, 24, 'DMSans-Bold', self::FOREGROUND);

        $name = $tool->name;
        $nameSize = $card->fitSize($name, $safe, 54, 34, 'DMSans-Bold');
        $card->text($name, self::WIDTH / 2, 238, $nameSize, 'DMSans-Bold', self::FOREGROUND, align: 'center');

        $tagline = $this->tagline($tool);
        $tagSize = $card->fitSize($tagline, $safe, 25, 17, 'DMSans-Regular');
        $card->text($tagline, self::WIDTH / 2, 312, $tagSize, 'DMSans-Regular', self::MUTED, align: 'center');

        $this->mock($card, $tool, $accent);
    }

    /**
     * A tinted mock of the result the tool returns.
     *
     * Not a screenshot: a picture of the *shape* of the answer — a thumbnail set,
     * an avatar and bio lines, two money figures. Showing the answer outperforms
     * describing it on a utility page, and a shape survives being scaled to a
     * Slack unfurl in a way a screenshot of real UI does not.
     */
    private function mock(SocialCardCanvas $card, Tool $tool, string $accent): void
    {
        $top = 372;
        $centre = self::WIDTH / 2;

        $tile = function (float $x, float $y, float $w, float $h, float $r = 10) use ($card, $accent): void {
            $card->roundedOutline($x, $y, $w, $h, $r, $accent, 2, $accent, 0.20, 0.5);
        };

        switch ($this->shape($tool)) {
            case 'square':
                $tile($centre - 156, $top, 120, 120, 14);
                $tile($centre - 24, $top + 12, 96, 96, 12);
                $tile($centre + 84, $top + 24, 72, 72, 10);
                break;

            case 'profile':
                $card->circle($centre - 150, $top + 48, 96, $accent, 0.18);
                foreach ([[0, 280], [1, 220], [2, 160]] as [$i, $w]) {
                    $card->roundedRect($centre - 84, $top + 18 + $i * 30, $w, 12, 6, $accent, 0.4);
                }
                break;

            case 'lines':
                foreach ([[0, 420], [1, 340]] as [$i, $w]) {
                    $card->roundedRect($centre - $w / 2, $top + 18 + $i * 30, $w, 12, 6, $accent, 0.4);
                }
                $card->roundedRect($centre - 150, $top + 78, 300, 12, 6, $accent, 0.9);
                break;

            case 'pins':
                $tile($centre - 162, $top, 100, 150, 14);
                $tile($centre - 50, $top + 15, 100, 120, 14);
                $tile($centre + 62, $top + 27, 100, 96, 14);
                break;

            case 'figures':
                $card->text('$412', $centre - 150, $top + 4, 44, 'JetBrainsMono-Bold', $accent, align: 'center');
                $card->text('REWARDS', $centre - 150, $top + 62, 16, 'JetBrainsMono-Regular', self::MUTED, align: 'center', tracking: 2);
                $card->text('$3,100', $centre + 90, $top + 4, 44, 'JetBrainsMono-Bold', self::FOREGROUND, align: 'center');
                $card->text('BRAND DEAL', $centre + 90, $top + 62, 16, 'JetBrainsMono-Regular', self::MUTED, align: 'center', tracking: 2);
                break;

            case 'video':
                $tile($centre - 216, $top, 200, 113);
                $tile($centre, $top + 22, 120, 68);
                $tile($centre + 136, $top + 34, 80, 45);

                // The play badge is what makes three rectangles read as video
                // rather than as loading skeletons — so only video tools get it.
                $card->roundedRect($centre - 153, $top + 39, 74, 52, 14, $accent, 0.9);
                $card->triangle($centre - 122, $top + 52, 24, 26, self::FOREGROUND);
                break;

            default: // gallery and lines both fall back to a plain set of frames
                $tile($centre - 216, $top, 200, 113);
                $tile($centre, $top + 22, 120, 68);
                $tile($centre + 136, $top + 34, 80, 45);
        }
    }

    /**
     * Three stickers pasted across the bottom of the page.
     *
     * "free" answers the only objection a searcher has on a utility page, and the
     * tilt is what stops the row from reading as another toolbar.
     */
    private function stickers(SocialCardCanvas $card, string $accent, string $onAccent): void
    {
        $y = 556;
        $size = 26;
        $padX = 26;
        $gap = 16;

        // Measured first and laid out from the middle: the labels are different
        // lengths on every card, and a row placed by eye would overlap on some
        // and drift off-centre on others.
        $stickers = [
            ['free', 'DMSans-Bold', -4, $accent, $onAccent, null],
            ['no sign-up', 'DMSans-Regular', -2, '#141924', self::FOREGROUND, self::EDGE],
            ['metacreator.dev', 'DMSans-Regular', 5, '#141924', self::FOREGROUND, self::EDGE],
        ];

        $widths = array_map(
            fn (array $sticker): float => $card->stickerWidth($sticker[0], $size, $sticker[1], $padX),
            $stickers,
        );

        $total = array_sum($widths) + $gap * (count($stickers) - 1);
        $cursor = (self::WIDTH - $total) / 2;

        foreach ($stickers as $i => [$label, $font, $angle, $fill, $ink, $stroke]) {
            $card->sticker(
                $label, $cursor + $widths[$i] / 2, $y, $angle,
                $font, $size, $padX, 16, 12,
                $fill, $ink, $stroke, $stroke === null ? 0.0 : 3.0,
            );

            $cursor += $widths[$i] + $gap;
        }
    }

    /**
     * PNG unless PNG is heavy, then JPEG.
     *
     * A share is fetched by a crawler on a budget: Facebook and Slack both give up
     * on a slow image, and a first fetch that times out is cached as "no image" for
     * days. PNG keeps the type crisp and usually lands well under the ceiling; the
     * few cards with a large tinted mock are the ones JPEG wins, and at q88 the
     * difference is invisible at feed size.
     *
     * @return array{bytes: string, extension: string, mime: string, width: int, height: int, alt: string}
     */
    private function encode(SocialCardCanvas $card, Tool $tool): array
    {
        $png = $card->encode('png');
        $bytes = $png;
        $extension = 'png';
        $mime = 'image/png';

        if (strlen($png) > 300 * 1024) {
            $jpeg = $card->encode('jpeg', 88);

            if (strlen($jpeg) < strlen($png)) {
                $bytes = $jpeg;
                $extension = 'jpg';
                $mime = 'image/jpeg';
            }
        }

        return [
            'bytes' => $bytes,
            'extension' => $extension,
            'mime' => $mime,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'alt' => $this->altText($tool),
        ];
    }

    /**
     * What a screen reader and Google get instead of the pixels.
     *
     * The card's own words plus what the tool does: crawlers read `og:image:alt`
     * and the markup around it, never the image, so the description has to live
     * outside the file.
     */
    public function altText(Tool $tool): string
    {
        return mb_substr(
            "{$tool->name} on metacreator.dev — {$this->tagline($tool)}",
            0, 255,
        );
    }

    /** @return array{0: string, 1: string} */
    private function palette(Tool $tool): array
    {
        foreach ($this->platforms($tool) as $platform) {
            if (isset(self::PLATFORMS[$platform])) {
                return self::PLATFORMS[$platform];
            }
        }

        return self::HOUSE;
    }

    /**
     * Which mock shape fits this tool's answer.
     *
     * Keyed off the tool's own slug rather than a column, because the shape is a
     * property of what the tool returns and nothing in the schema records that. The
     * order matters: the first match wins, so the specific words ("money", "bio")
     * are tested before the broad ones ("image", "preview").
     */
    private function shape(Tool $tool): string
    {
        $slug = $tool->slug;

        $matches = fn (string ...$needles): bool => array_reduce(
            $needles,
            fn (bool $carry, string $needle): bool => $carry || str_contains($slug, $needle),
            false,
        );

        return match (true) {
            $matches('money', 'calculator', 'engagement-rate', 'earnings', 'cpm', 'roi', 'revenue') => 'figures',
            $matches('bio', 'username', 'handle', 'profile') => 'profile',
            $matches('pin-', 'pinterest') => 'pins',
            $matches('cover', 'artwork', 'avatar', 'logo', 'icon', 'qr-') => 'square',
            // Only these get the play badge: it says "video" loudly, and it says it
            // wrongly on a thread splitter.
            $matches('thumbnail', 'video', 'clip', 'vod', 'reel', 'short', 'subtitle', 'timestamp', 'chapter') => 'video',
            $matches('image', 'photo', 'banner', 'carousel', 'story', 'resizer', 'converter', 'compressor', 'grid') => 'gallery',
            default => 'lines',
        };
    }

    /** @return list<string> */
    private function platforms(Tool $tool): array
    {
        $platforms = $tool->platforms;

        return is_array($platforms) ? array_values(array_map('strval', $platforms)) : [];
    }

    private function tagline(Tool $tool): string
    {
        $tagline = trim((string) $tool->tagline);

        return $tagline !== '' ? $tagline : 'A free tool from MetaCreator.dev — no account needed.';
    }

    /** The address as a person reads it: no scheme, no trailing slash. */
    private function displayUrl(Tool $tool): string
    {
        $host = (string) preg_replace('#^https?://#', '', rtrim($this->siteUrl, '/'));

        return "{$host}/tools/{$tool->slug}";
    }
}
