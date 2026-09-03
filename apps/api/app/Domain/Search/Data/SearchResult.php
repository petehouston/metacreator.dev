<?php

declare(strict_types=1);

namespace App\Domain\Search\Data;

use App\Domain\Search\Enums\SearchResultType;

/**
 * One hit, in the shape the site renders it — not in the shape it was stored.
 *
 * Search is the one surface that has to put a tool, an article, a ranking table and
 * the privacy policy in the same list, and a reader scanning that list wants the
 * same four things from each row. Normalising here rather than in the resource
 * means the ranking code compares like with like: every candidate has a title, a
 * summary and a body, whatever table it came out of.
 */
final readonly class SearchResult
{
    public function __construct(
        public SearchResultType $type,
        public string $id,
        public string $title,
        public string $url,
        public ?string $summary,
        public ?string $image,
        public int $score,
        /** Extra context for the result card — a category name, a platform, a date. */
        public ?string $context = null,
    ) {}

    public function withScore(int $score): self
    {
        return new self(
            $this->type,
            $this->id,
            $this->title,
            $this->url,
            $this->summary,
            $this->image,
            $score,
            $this->context,
        );
    }

    /**
     * The flat form this is cached as.
     *
     * `cache.serializable_classes` is `false` in this application: nothing may be
     * unserialized from the cache as a PHP object, so that a leaked `APP_KEY` plus a
     * writable cache cannot be turned into a gadget chain. Caching the object would
     * therefore come back as `__PHP_Incomplete_Class` and fail on first property
     * access — and the right answer is not to widen that allowlist for a search
     * result. Scalars in, scalars out.
     *
     * It is the more durable shape anyway: a cached object is a snapshot of a class
     * definition, so adding a constructor argument would break every entry written
     * before the deploy, for as long as its TTL runs.
     *
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'summary' => $this->summary,
            'image' => $this->image,
            'score' => $this->score,
            'context' => $this->context,
        ];
    }

    /** @param array<string, string|int|null> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            type: SearchResultType::from((string) $row['type']),
            id: (string) $row['id'],
            title: (string) $row['title'],
            url: (string) $row['url'],
            summary: isset($row['summary']) ? (string) $row['summary'] : null,
            image: isset($row['image']) ? (string) $row['image'] : null,
            score: (int) ($row['score'] ?? 0),
            context: isset($row['context']) ? (string) $row['context'] : null,
        );
    }
}
