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
 * The link card debugger — what X, Facebook and LinkedIn will show for a URL.
 *
 * Every platform's own debugger requires you to be logged in and each one shows a
 * different subset. This reads the tags once and draws the card each platform will
 * actually build from them — including the fallbacks they apply when a tag is
 * missing, and the point at which each one cuts the title.
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

        $rows = [
            ['tag' => 'og:title', 'value' => $title ?? '— missing —', 'used_by' => 'Facebook, LinkedIn, X, Slack'],
            ['tag' => 'og:description', 'value' => $description ?? '— missing —', 'used_by' => 'Facebook, Slack'],
            ['tag' => 'og:image', 'value' => $image ?? '— missing —', 'used_by' => 'Every platform'],
            ['tag' => 'twitter:card', 'value' => $card ?? 'summary (default)', 'used_by' => 'X only'],
            ['tag' => 'og:site_name', 'value' => $siteName ?? '— missing —', 'used_by' => 'Facebook, LinkedIn'],
            ['tag' => 'canonical', 'value' => $this->canonical($html) ?? '— missing —', 'used_by' => 'Search engines'],
        ];

        $domain = LinkDisplay::domain($url);
        $large = $card === 'summary_large_image' || ($card === null && $image !== null);

        $frames = [
            // X drops the description entirely on the large card and shows the domain
            // over the image, which is why a good title matters more there than anywhere.
            PreviewFrame::make('x', 'X (Twitter)', 'link-card')
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

            PreviewFrame::make('facebook', 'Facebook', 'link-card')
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

            PreviewFrame::make('linkedin', 'LinkedIn', 'link-card')
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

            PreviewFrame::make('generic', 'Slack, Discord, iMessage', 'link-card')
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
