<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Http\SafeHttpClient;
use App\Support\Social\LinkDisplay;
use App\Support\Social\PreviewFrame;

/**
 * The link card debugger — what every platform will actually show for a URL.
 *
 * Each platform ships its own debugger, each one requires you to be logged in to
 * that platform, and each shows a different subset. Checking a link properly means
 * four logins and four tabs. This fetches the page once and draws the card each
 * platform builds from the tags it finds — including the fallbacks they apply when
 * a tag is missing, and the point at which each one cuts the title.
 *
 * **Desktop and mobile are drawn separately**, because that is where the platforms
 * differ most and the desktop crop is the forgiving one: Facebook gives a title 88
 * characters in the web feed and about 65 on a phone, LinkedIn 100 and about 60. A
 * debugger that draws only the desktop card is checking the case that was never in
 * doubt, on the surface where the minority of the taps happen.
 */
final class MetadataPreviewRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'utility.metadata-preview';
    }

    public function cacheTtl(): int
    {
        return 900;
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
                    'title' => 'URL to check',
                    'description' => 'Any public page — your own site, a landing page, a blog post.',
                    'minLength' => 4,
                    'maxLength' => 500,
                    'examples' => ['https://example.com'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $url = trim($input->string('url'));
        $url = str_contains($url, '://') ? $url : "https://{$url}";

        $html = SafeHttpClient::body(SafeHttpClient::get($url));

        $title = $this->tag($html, 'property', 'og:title')
            ?? $this->tag($html, 'name', 'twitter:title')
            ?? $this->title($html);

        $description = $this->tag($html, 'property', 'og:description')
            ?? $this->tag($html, 'name', 'twitter:description')
            ?? $this->tag($html, 'name', 'description');

        $image = $this->tag($html, 'property', 'og:image') ?? $this->tag($html, 'name', 'twitter:image');
        $card = $this->tag($html, 'name', 'twitter:card');
        $siteName = $this->tag($html, 'property', 'og:site_name');

        // Pinterest only lifts your own title and description onto a Pin when
        // og:type names a content kind it understands; everything else is a plain
        // Pin carrying whatever the pinner happened to type.
        $type = $this->tag($html, 'property', 'og:type');
        $richPin = in_array($type, ['article', 'product', 'product.item', 'book', 'recipe'], true);

        $rows = [
            ['tag' => 'og:title', 'value' => $title ?? '— missing —', 'used_by' => 'Facebook, LinkedIn, X, Slack'],
            ['tag' => 'og:description', 'value' => $description ?? '— missing —', 'used_by' => 'Facebook, Slack'],
            ['tag' => 'og:image', 'value' => $image ?? '— missing —', 'used_by' => 'Every platform'],
            ['tag' => 'twitter:card', 'value' => $card ?? 'summary (default)', 'used_by' => 'X only'],
            ['tag' => 'og:site_name', 'value' => $siteName ?? '— missing —', 'used_by' => 'Facebook, LinkedIn'],
            ['tag' => 'og:type', 'value' => $type ?? '— missing —', 'used_by' => 'Pinterest Rich Pins'],
            ['tag' => 'canonical', 'value' => $this->canonical($html) ?? '— missing —', 'used_by' => 'Search engines'],
        ];

        $domain = LinkDisplay::domain($url);
        $large = $card === 'summary_large_image' || ($card === null && $image !== null);

        // Two rows per platform, because the crop is where the platforms differ
        // most and the desktop crop is the forgiving one. A title that survives a
        // 1280px feed is regularly cut in half on a 390px phone, and the phone is
        // where the majority of the taps come from — so a debugger that only draws
        // the desktop card is checking the case that was never in doubt.
        $frames = [
            // X drops the description entirely on the large card and shows the domain
            // over the image, which is why a good title matters more there than anywhere.
            PreviewFrame::make('x', 'X — Desktop', 'link-card')
                ->link(
                    domain: $domain,
                    title: $this->clip($title, 70),
                    description: $large ? null : $this->clip($description, 120),
                    style: $large ? 'large' : 'small',
                    image: $image,
                )
                ->status(
                    $image === null ? 'danger' : 'ok',
                    $image === null
                        ? 'No image — X posts a bare link'
                        : ($large ? 'Large image card' : 'Small summary card'),
                )
                ->note($card === null
                    ? 'No twitter:card tag, so X falls back to og:image and the summary card.'
                    : 'twitter:card is “'.$card.'”.'),

            PreviewFrame::make('x', 'X — Mobile', 'link-card')
                ->link(
                    domain: $domain,
                    title: $this->clip($title, 50),
                    description: $large ? null : $this->clip($description, 80),
                    style: $large ? 'large' : 'small',
                    image: $image,
                )
                ->status($image === null ? 'danger' : 'ok', $large ? 'Full-width image' : 'Thumbnail beside the text')
                ->note('The phone app gives the title roughly 50 characters over two lines.'),

            PreviewFrame::make('facebook', 'Facebook — Desktop feed', 'link-card')
                ->link(
                    domain: mb_strtoupper($domain),
                    title: $this->clip($title, 88),
                    description: $this->clip($description, 200),
                    style: 'large',
                    image: $image,
                )
                ->status($image === null ? 'danger' : 'ok', $image === null
                    ? 'No image — Facebook renders a plain text link'
                    : 'Image card, 1.91:1')
                ->note('Facebook shows roughly 88 characters of the title and 200 of the description.'),

            PreviewFrame::make('facebook', 'Facebook — Mobile feed', 'link-card')
                ->link(
                    domain: mb_strtoupper($domain),
                    title: $this->clip($title, 65),
                    // The phone app drops to a single description line, and on a
                    // narrow screen that line is short.
                    description: $this->clip($description, 80),
                    style: 'large',
                    image: $image,
                )
                ->status($image === null ? 'danger' : 'ok', 'Image card, 1.91:1')
                ->note('One line of description on a phone, against three on the desktop feed — write the '
                    .'first clause so it can stand alone.'),

            PreviewFrame::make('linkedin', 'LinkedIn — Desktop', 'link-card')
                ->link(
                    domain: $domain,
                    title: $this->clip($title, 100),
                    style: 'large',
                    image: $image,
                )
                ->status($image === null ? 'warn' : 'ok', $image === null
                    ? 'No image — small text-only card'
                    : 'Image card, 1.2:1')
                ->note('LinkedIn ignores og:description entirely: the title carries the whole card.'),

            PreviewFrame::make('linkedin', 'LinkedIn — Mobile', 'link-card')
                ->link(
                    domain: $domain,
                    title: $this->clip($title, 60),
                    style: 'large',
                    image: $image,
                )
                ->status($image === null ? 'warn' : 'ok', 'Image card, 1.2:1')
                ->note('Still no description, and now about 60 characters of title. LinkedIn is the '
                    .'platform where a vague title costs the most.'),

            PreviewFrame::make('pinterest', 'Pinterest — Pin closeup', 'link-card')
                ->link(
                    domain: $domain,
                    title: $this->clip($title, 40),
                    description: $this->clip($description, 500),
                    style: 'large',
                    image: $image,
                )
                ->status($richPin ? 'ok' : 'warn', $richPin
                    ? 'Rich Pin metadata found'
                    : 'No article/product metadata — a plain Pin')
                ->note($richPin
                    ? 'og:type is “'.$type.'”, so Pinterest pulls the title and description onto the Pin itself.'
                    : 'Without og:type set to article or product, Pinterest uses whatever the pinner typed '
                    .'rather than your title.'),

            PreviewFrame::make('generic', 'WhatsApp & Telegram — Mobile', 'link-card')
                ->link(
                    domain: $domain,
                    title: $this->clip($title, 65),
                    description: $this->clip($description, 100),
                    style: 'small',
                    image: $image,
                )
                ->status($image === null ? 'warn' : 'ok', $image === null
                    ? 'Text-only preview'
                    : 'Square thumbnail beside the text')
                ->note('Chat apps fetch the preview once, on the first send, and cache it hard — a fix '
                    .'published later will not reach a message already sent.'),

            PreviewFrame::make('generic', 'Slack & Discord — Desktop', 'link-card')
                ->link(
                    domain: $siteName ?? $domain,
                    title: $this->clip($title, 120),
                    description: $this->clip($description, 300),
                    style: 'small',
                    image: $image,
                )
                ->status('ok', 'Unfurl with a side thumbnail')
                ->note('Chat apps keep the description, so it is worth writing even where feeds drop it.'),
        ];

        $frames = array_map(fn (PreviewFrame $frame) => $frame->toArray(), $frames);

        $warnings = [];

        if ($image === null) {
            $warnings[] = 'No og:image. Every platform will render a plain text link, which measurably '
                .'reduces clicks. Add a 1200×630 image.';
        }

        if ($title === null) {
            $warnings[] = 'No og:title and no <title>. Every card falls back to the bare URL.';
        }

        if ($title !== null && mb_strlen($title) > 60) {
            $warnings[] = 'The title is '.mb_strlen($title).' characters — X and LinkedIn cut it around 60.';
        }

        if ($description !== null && mb_strlen($description) > 200) {
            $warnings[] = 'The description is '.mb_strlen($description).' characters. Facebook shows about 200.';
        }

        if ($card === null && $image !== null) {
            $warnings[] = 'No twitter:card tag, so X falls back to the small “summary” card. '
                .'Set summary_large_image to use the full-width one.';
        }

        return ToolResult::socialPreview(
            $frames,
            summary: $title !== null
                ? "Cards for this URL will read “{$title}”."
                : 'This page has no title tags at all — every card will fall back to the bare URL.',
            table: [
                'columns' => [
                    ['key' => 'tag', 'label' => 'Tag'],
                    ['key' => 'value', 'label' => 'Value'],
                    ['key' => 'used_by', 'label' => 'Used by'],
                ],
                'rows' => $rows,
            ],
        )->withWarnings($warnings)->withMeta([
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'preview_url' => $image,
        ]);
    }

    /** Cut a value where the platform cuts it, so the card is honest about the crop. */
    private function clip(?string $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }

    private function tag(string $html, string $attribute, string $name): ?string
    {
        // Attribute order varies between frameworks, so both orders are matched.
        $patterns = [
            '/<meta[^>]+'.$attribute.'=["\']'.preg_quote($name, '/').'["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*'.$attribute.'=["\']'.preg_quote($name, '/').'["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match) === 1 && trim($match[1]) !== '') {
                return html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    private function title(string $html): ?string
    {
        return preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match) === 1
            ? html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5)
            : null;
    }

    private function canonical(string $html): ?string
    {
        return preg_match('/<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']*)["\']/i', $html, $match) === 1
            ? $match[1]
            : null;
    }
}
