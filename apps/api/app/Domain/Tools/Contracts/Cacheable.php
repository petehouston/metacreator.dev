<?php

declare(strict_types=1);

namespace App\Domain\Tools\Contracts;

/**
 * Opt a runner into result caching.
 *
 * The cache key includes the tool's `version` column, so bumping that version
 * invalidates every cached result for the tool without touching Redis.
 *
 * Only implement this when the result genuinely depends on nothing but the input.
 * A tool that reads "trending right now" data is not cacheable for long, and should
 * return a short TTL rather than skipping the interface.
 */
interface Cacheable
{
    /** Time-to-live in seconds. */
    public function cacheTtl(): int;
}
