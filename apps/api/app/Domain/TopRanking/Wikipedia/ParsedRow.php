<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Wikipedia;

/**
 * One row of a ranking table, after the article's markup has been reduced to facts.
 *
 * A readonly object rather than an array because it crosses three boundaries — the
 * parser, the reconciliation in the sync action, and the tests — and an array would
 * make a renamed key a runtime surprise in the third of those.
 */
final readonly class ParsedRow
{
    public function __construct(
        public string $name,
        public ?string $handle,
        public ?string $owner,
        public ?string $profileUrl,
        public ?float $metric,
        public ?float $secondaryMetric,
        public ?string $country,
        public ?string $category,
        public ?string $language,
        public ?string $description,
    ) {}
}
