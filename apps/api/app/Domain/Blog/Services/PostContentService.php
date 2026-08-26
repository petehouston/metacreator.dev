<?php

declare(strict_types=1);

namespace App\Domain\Blog\Services;

use App\Domain\Blog\Blocks\BlockSanitizer;
use App\Domain\Blog\Blocks\BlockTextExtractor;
use App\Domain\Blog\Models\Post;

/**
 * Turns a submitted block document into the columns a post stores.
 *
 * Sanitising and deriving live here rather than in the controller so that every
 * write path — admin save, autosave, import, seeder — produces identical rows.
 */
final class PostContentService
{
    public function __construct(
        private readonly BlockSanitizer $sanitizer,
        private readonly BlockTextExtractor $extractor,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @return array{blocks: array<string, mixed>, content_text: string, word_count: int, reading_minutes: int}
     */
    public function derive(array $document): array
    {
        $blocks = $this->sanitizer->sanitize($document);
        $text = $this->extractor->text($blocks);
        $words = $this->extractor->wordCount($text);

        return [
            'blocks' => $blocks,
            'content_text' => $text,
            'word_count' => $words,
            'reading_minutes' => $this->extractor->readingMinutes($words),
        ];
    }

    /**
     * Apply a block document to a post without saving it.
     *
     * @param  array<string, mixed>  $document
     */
    public function apply(Post $post, array $document): Post
    {
        $post->forceFill($this->derive($document));

        return $post;
    }

    /**
     * Derive an excerpt from the first paragraph when the editor left it blank.
     *
     * A missing excerpt is a meta-description and a card subtitle that both fall
     * back to nothing, so it is worth generating something reasonable.
     */
    public function autoExcerpt(Post $post, int $length = 200): string
    {
        $text = trim((string) $post->content_text);

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($truncated, ' ');

        // Cut on a word boundary; a mid-word ellipsis reads like a bug.
        return rtrim($lastSpace !== false ? mb_substr($truncated, 0, $lastSpace) : $truncated, " \t\n\r\0\x0B.,;:").'…';
    }
}
