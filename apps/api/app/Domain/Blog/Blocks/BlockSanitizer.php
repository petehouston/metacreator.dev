<?php

declare(strict_types=1);

namespace App\Domain\Blog\Blocks;

use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Normalises and sanitises a post's block array on save.
 *
 * Every string that will ever reach a browser as markup passes through HTMLPurifier
 * here, under the narrowest profile that still lets the block do its job. Anything
 * the sanitiser does not recognise is passed through untouched rather than dropped:
 * content written by a newer deploy must survive a rollback (docs/09).
 */
final class BlockSanitizer
{
    /** Current block-document schema version. */
    public const VERSION = 1;

    /** Embed providers we will render in an iframe. */
    private const EMBED_PROVIDERS = [
        'youtube', 'vimeo', 'twitter', 'instagram', 'tiktok', 'codepen', 'spotify', 'generic',
    ];

    /**
     * @param  array<string, mixed>  $document
     * @return array{version: int, blocks: list<array<string, mixed>>}
     */
    public function sanitize(array $document): array
    {
        $blocks = $document['blocks'] ?? [];

        if (! is_array($blocks)) {
            $blocks = [];
        }

        $clean = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! isset($block['type']) || ! is_string($block['type'])) {
                continue;
            }

            $clean[] = $this->sanitizeBlock($block);
        }

        return ['version' => self::VERSION, 'blocks' => $clean];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function sanitizeBlock(array $block): array
    {
        $type = BlockType::tryFrom($block['type']);
        $id = is_string($block['id'] ?? null) && $block['id'] !== ''
            ? $block['id']
            : 'b_'.strtolower((string) Str::ulid());

        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        // An unrecognised type is preserved verbatim so a newer deploy's content is
        // not destroyed by an older one saving over it. It renders as a labelled
        // placeholder on the frontend.
        if ($type === null) {
            return ['id' => $id, 'type' => $block['type'], 'data' => $data];
        }

        return [
            'id' => $id,
            'type' => $type->value,
            'data' => $this->sanitizeData($type, $data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeData(BlockType $type, array $data): array
    {
        return match ($type) {
            BlockType::Paragraph => [
                'html' => $this->richtext($data['html'] ?? ''),
            ],

            BlockType::Heading => [
                // H1 belongs to the post title; anything outside H2–H4 is clamped
                // rather than rejected, so a paste never fails the whole save.
                'level' => min(4, max(2, (int) ($data['level'] ?? 2))),
                'text' => $this->plain($data['text'] ?? ''),
            ],

            BlockType::ListBlock => [
                'style' => $this->oneOf($data['style'] ?? 'unordered', ['unordered', 'ordered', 'checklist'], 'unordered'),
                'items' => $this->listItems($data['items'] ?? []),
            ],

            BlockType::Quote => [
                'text' => $this->richtext($data['text'] ?? ''),
                'cite' => $this->plain($data['cite'] ?? ''),
                'variant' => $this->oneOf($data['variant'] ?? 'default', ['default', 'pull'], 'default'),
            ],

            BlockType::Image => [
                'url' => $this->url($data['url'] ?? ''),
                'alt' => $this->plain($data['alt'] ?? ''),
                'caption' => $this->richtext($data['caption'] ?? ''),
                'size' => $this->oneOf($data['size'] ?? 'inline', ['inline', 'wide', 'full'], 'inline'),
                'width' => $this->positiveIntOrNull($data['width'] ?? null),
                'height' => $this->positiveIntOrNull($data['height'] ?? null),
            ],

            BlockType::Embed => [
                'provider' => $this->oneOf($data['provider'] ?? 'generic', self::EMBED_PROVIDERS, 'generic'),
                'url' => $this->url($data['url'] ?? ''),
                'aspect' => $this->oneOf($data['aspect'] ?? '16:9', ['16:9', '4:3', '1:1', '9:16'], '16:9'),
                'caption' => $this->plain($data['caption'] ?? ''),
            ],

            BlockType::Code => [
                'language' => $this->slugish($data['language'] ?? 'text'),
                'filename' => $this->plain($data['filename'] ?? ''),
                // Never purified: this is displayed as text, and escaping happens at
                // render. Running it through an HTML sanitiser would corrupt the code.
                'code' => is_string($data['code'] ?? null) ? $data['code'] : '',
            ],

            BlockType::Html => [
                'html' => $this->embedHtml($data['html'] ?? ''),
                'sanitized' => true,
            ],

            BlockType::Callout => [
                'tone' => $this->oneOf($data['tone'] ?? 'info', ['info', 'tip', 'warning', 'danger'], 'info'),
                'title' => $this->plain($data['title'] ?? ''),
                'html' => $this->richtext($data['html'] ?? ''),
            ],

            BlockType::Divider => [
                'style' => $this->oneOf($data['style'] ?? 'plain', ['plain', 'dots', 'asterism'], 'plain'),
            ],

            BlockType::Table => [
                'has_header' => (bool) ($data['has_header'] ?? true),
                'rows' => $this->tableRows($data['rows'] ?? []),
            ],

            BlockType::Button => [
                'label' => $this->plain($data['label'] ?? ''),
                'href' => $this->url($data['href'] ?? ''),
                'variant' => $this->oneOf($data['variant'] ?? 'primary', ['primary', 'secondary', 'outline'], 'primary'),
            ],

            BlockType::ToolCard => [
                'toolSlug' => $this->slugish($data['toolSlug'] ?? ''),
            ],

            BlockType::Faq => [
                'items' => $this->faqItems($data['items'] ?? []),
            ],
        };
    }

    // ── Field helpers ────────────────────────────────────────────────────────

    private function richtext(mixed $value): string
    {
        return is_string($value) && $value !== ''
            ? (string) Purifier::clean($value, 'richtext')
            : '';
    }

    private function embedHtml(mixed $value): string
    {
        return is_string($value) && $value !== ''
            ? (string) Purifier::clean($value, 'embed')
            : '';
    }

    /** Strips markup entirely — for values rendered as text nodes. */
    private function plain(mixed $value): string
    {
        return is_string($value)
            ? trim(strip_tags($value))
            : '';
    }

    private function url(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        $value = trim($value);

        // Blocks a `javascript:` payload reaching an href or src attribute. Relative
        // paths are allowed so internal links keep working.
        $scheme = parse_url($value, PHP_URL_SCHEME);

        if ($scheme !== null && ! in_array(strtolower((string) $scheme), ['http', 'https', 'mailto'], true)) {
            return '';
        }

        return $value;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function slugish(mixed $value): string
    {
        return is_string($value)
            ? (string) preg_replace('/[^a-zA-Z0-9._-]/', '', $value)
            : '';
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /** @return list<array{html: string, checked: bool}> */
    private function listItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $clean = [];

        foreach ($items as $item) {
            // Accepts both a bare string and the checklist object shape.
            $html = is_array($item) ? ($item['html'] ?? '') : $item;
            $checked = is_array($item) && (bool) ($item['checked'] ?? false);

            $clean[] = ['html' => $this->richtext($html), 'checked' => $checked];
        }

        return $clean;
    }

    /** @return list<list<string>> */
    private function tableRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $clean[] = array_values(array_map(fn ($cell): string => $this->richtext($cell), $row));
        }

        return $clean;
    }

    /** @return list<array{question: string, answer: string}> */
    private function faqItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $clean = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $clean[] = [
                // The question also becomes JSON-LD, where markup is invalid.
                'question' => $this->plain($item['question'] ?? ''),
                'answer' => $this->richtext($item['answer'] ?? ''),
            ];
        }

        return $clean;
    }
}
