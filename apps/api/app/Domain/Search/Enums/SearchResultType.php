<?php

declare(strict_types=1);

namespace App\Domain\Search\Enums;

/**
 * What kind of thing a search result is.
 *
 * The frontend renders one icon and one badge per case, so the set is deliberately
 * small and closed: four kinds of content the site publishes, not one case per
 * table. A ranking page and a blog post are different *reading experiences*, which
 * is the only distinction a reader scanning a result list can act on.
 */
enum SearchResultType: string
{
    case Page = 'page';
    case Tool = 'tool';
    case Post = 'post';
    case TopRanking = 'top_ranking';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'Page',
            self::Tool => 'Tool',
            self::Post => 'Post',
            self::TopRanking => 'Top Ranking',
        };
    }

    /**
     * The tie-breaker between two results that scored identically on text.
     *
     * A tool is what the site is for, so it wins a tie; a static page is a
     * navigational answer and comes next; posts and rankings are long-form and are
     * usually reached deliberately rather than by a one-word query.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Tool => 4,
            self::Page => 3,
            self::Post => 2,
            self::TopRanking => 1,
        };
    }
}
