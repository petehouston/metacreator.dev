<?php

declare(strict_types=1);

namespace App\Domain\Blog\Blocks;

/**
 * Flattens a block document to plain text.
 *
 * Feeds three things that must agree with each other: the `content_text` column
 * behind fulltext search, the displayed word count, and the reading-time estimate.
 * Computing them from one traversal is what keeps them consistent.
 */
final class BlockTextExtractor
{
    /** Words per minute. 220 is the usual figure for adult silent reading of prose. */
    private const READING_WPM = 220;

    /**
     * @param  array{version?: int, blocks?: list<array<string, mixed>>}  $document
     */
    public function text(array $document): string
    {
        $parts = [];

        foreach ($document['blocks'] ?? [] as $block) {
            $type = BlockType::tryFrom((string) ($block['type'] ?? ''));

            // Only prose contributes. A code block or an embed URL would inflate the
            // word count and pollute search results with tokens nobody searches for.
            if ($type === null || ! $type->isProse()) {
                continue;
            }

            $parts[] = $this->fromBlock($type, is_array($block['data'] ?? null) ? $block['data'] : []);
        }

        $text = trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts))) ?? '');

        return $text;
    }

    public function wordCount(string $text): int
    {
        return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    }

    public function readingMinutes(int $wordCount): int
    {
        return max(1, (int) ceil($wordCount / self::READING_WPM));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fromBlock(BlockType $type, array $data): string
    {
        return match ($type) {
            BlockType::Paragraph => $this->strip($data['html'] ?? ''),
            BlockType::Heading => $this->strip($data['text'] ?? ''),
            BlockType::Quote => $this->strip($data['text'] ?? '').' '.$this->strip($data['cite'] ?? ''),
            BlockType::Callout => $this->strip($data['title'] ?? '').' '.$this->strip($data['html'] ?? ''),
            BlockType::ListBlock => $this->fromList($data['items'] ?? []),
            BlockType::Faq => $this->fromFaq($data['items'] ?? []),
            default => '',
        };
    }

    private function fromList(mixed $items): string
    {
        if (! is_array($items)) {
            return '';
        }

        return implode(' ', array_map(
            fn ($item): string => $this->strip(is_array($item) ? ($item['html'] ?? '') : $item),
            $items,
        ));
    }

    private function fromFaq(mixed $items): string
    {
        if (! is_array($items)) {
            return '';
        }

        $parts = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parts[] = $this->strip($item['question'] ?? '').' '.$this->strip($item['answer'] ?? '');
        }

        return implode(' ', $parts);
    }

    private function strip(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        // Decode first: `&amp;` should count as one word, not leak an entity into
        // the search index.
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
