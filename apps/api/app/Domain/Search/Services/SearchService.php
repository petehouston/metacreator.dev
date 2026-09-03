<?php

declare(strict_types=1);

namespace App\Domain\Search\Services;

use App\Domain\Blog\Models\Post;
use App\Domain\Search\Data\SearchResult;
use App\Domain\Search\Enums\SearchResultType;
use App\Domain\Search\SitePageCatalog;
use App\Domain\Settings\Settings;
use App\Domain\Tools\Models\Tool;
use App\Domain\TopRanking\Models\TopRankingPage;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Global search across everything the site publishes.
 *
 * ## Why this is not one SQL query
 *
 * The four sources live in three tables with nothing in common but the idea of a
 * title, and two of them are not tables at all — the static pages are a code
 * catalog. A UNION over incompatible shapes, or a denormalised index table kept in
 * step by observers, both buy true SQL-side pagination at a real cost in machinery.
 * The corpus here is a few hundred rows: the tool catalog, the blog, seven ranking
 * pages and a dozen static routes. Fetching a bounded set of candidates per source
 * and ranking them in PHP is a handful of indexed queries and a sort over an array
 * that fits in a cache line's worth of memory.
 *
 * ## Why ranking is in PHP and not in the ORDER BY
 *
 * "Prioritise exact matches" is the requirement, and MySQL's `MATCH … AGAINST`
 * relevance does not express it: fulltext scores by term rarity across the corpus,
 * so a rare word buried in the body of a long article outranks a page whose title
 * *is* the query. It also ignores words shorter than `ft_min_word_len` and anything
 * on the stopword list — which is most one-word searches a visitor actually types.
 * SQL is used for what it is good at (finding candidates through an index) and the
 * ordering is decided here, where "the title equals what they typed" can be
 * spelled out.
 *
 * ## Cost
 *
 * Three indexed queries plus an in-memory sort, cached whole for five minutes per
 * (term, type) pair. A repeated query — which is what a typing dropdown produces —
 * costs one Redis read.
 */
final readonly class SearchService
{
    /**
     * How many rows each source may contribute before ranking.
     *
     * A ceiling rather than a guess: it bounds both the query and the sort, and at
     * 10 results a page it still covers 40+ pages of results, which no visitor
     * reaches. Raising it makes deep pages more complete and every search slower.
     */
    private const CANDIDATES_PER_SOURCE = 150;

    /** Terms shorter than this match nothing — a one-character `LIKE 'a%'` is the whole catalog. */
    public const MIN_TERM_LENGTH = 2;

    private const CACHE_TTL = 300;

    public function __construct(
        private Cache $cache,
        private SitePageCatalog $pages,
        private Settings $settings,
    ) {}

    /**
     * Ranked results for a term, best first.
     *
     * @return list<SearchResult>
     */
    public function search(string $term, ?SearchResultType $type = null): array
    {
        $term = $this->normalise($term);

        if (Str::length($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        // Keyed by the *normalised* term, so "  YouTube  " and "youtube" share one
        // entry — which is most of what a debounced type-ahead sends.
        $scope = $type === null ? 'all' : $type->value;
        $key = 'search:v1:'.md5($term).':'.$scope;

        // Cached as plain rows rather than as objects — see SearchResult::toArray()
        // for why the object form cannot survive this application's cache.
        $rows = $this->cache->remember($key, self::CACHE_TTL, function () use ($term, $type): array {
            $candidates = [];

            foreach ($this->sourcesFor($type) as $source) {
                foreach ($source($term) as $candidate) {
                    $candidates[] = $candidate;
                }
            }

            return array_map(
                static fn (SearchResult $result): array => $result->toArray(),
                $this->rank($candidates, $term),
            );
        });

        return array_map(
            static fn (array $row): SearchResult => SearchResult::fromArray($row),
            $rows,
        );
    }

    /**
     * The source callables this search should run.
     *
     * @return list<callable(string): list<array{result: SearchResult, body: string}>>
     */
    private function sourcesFor(?SearchResultType $type): array
    {
        $sources = [
            SearchResultType::Page->value => fn (string $term): array => $this->pageCandidates($term),
            SearchResultType::Tool->value => fn (string $term): array => $this->toolCandidates($term),
            SearchResultType::Post->value => fn (string $term): array => $this->postCandidates($term),
            SearchResultType::TopRanking->value => fn (string $term): array => $this->rankingCandidates($term),
        ];

        return $type === null
            ? array_values($sources)
            : [$sources[$type->value]];
    }

    // ── Candidate collection ─────────────────────────────────────────────────

    /**
     * @return list<array{result: SearchResult, body: string}>
     */
    private function pageCandidates(string $term): array
    {
        $haystacks = $this->pages->haystacks();
        $candidates = [];

        // No query: the catalog is a dozen entries in memory. Filtering happens in
        // the scorer, which drops anything that matches nothing.
        foreach ($this->pages->all() as $page) {
            $candidates[] = [
                'result' => $page,
                'body' => $haystacks[$page->url] ?? '',
            ];
        }

        return $candidates;
    }

    /**
     * @return list<array{result: SearchResult, body: string}>
     */
    private function toolCandidates(string $term): array
    {
        $tools = Tool::query()
            ->public()
            ->with(['category:id,slug,name', 'seo.ogMedia'])
            ->where(fn (Builder $q) => $this->matches($q, $term, ['name', 'tagline', 'description'], 'name'))
            ->limit(self::CANDIDATES_PER_SOURCE)
            ->get();

        return array_values($tools->map(fn (Tool $tool): array => [
            'result' => new SearchResult(
                type: SearchResultType::Tool,
                id: $tool->public_id,
                title: $tool->name,
                url: '/tools/'.$tool->slug,
                summary: $tool->tagline ?: Str::limit((string) $tool->description, 180),
                image: $tool->seo?->ogMedia?->url(),
                score: 0,
                context: $tool->category?->name,
            ),
            'body' => trim($tool->tagline.' '.$tool->description),
        ])->all());
    }

    /**
     * @return list<array{result: SearchResult, body: string}>
     */
    private function postCandidates(string $term): array
    {
        // The blog can be switched off, and then its posts are not public content.
        if (! $this->settings->bool('features.blog_enabled', true)) {
            return [];
        }

        $posts = Post::query()
            ->public()
            ->with(['category:id,slug,name', 'featuredMedia', 'seo.ogMedia'])
            ->where(fn (Builder $q) => $this->matches($q, $term, ['title', 'excerpt', 'content_text'], 'title'))
            ->orderByDesc('published_at')
            ->limit(self::CANDIDATES_PER_SOURCE)
            ->get();

        return array_values($posts->map(fn (Post $post): array => [
            'result' => new SearchResult(
                type: SearchResultType::Post,
                id: $post->public_id,
                title: $post->title,
                url: '/blog/'.$post->slug,
                summary: $post->excerpt ?: Str::limit((string) $post->content_text, 180),
                image: $post->featuredMedia?->url() ?? $post->seo?->ogMedia?->url(),
                score: 0,
                context: $post->category?->name,
            ),
            // The rendered article text, capped: a 4,000-word post contributes one
            // boolean ("does the phrase appear") and there is no reason to hold the
            // whole of it in a cached array to answer that.
            'body' => Str::limit((string) $post->content_text, 4000, ''),
        ])->all());
    }

    /**
     * @return list<array{result: SearchResult, body: string}>
     */
    private function rankingCandidates(string $term): array
    {
        $pages = TopRankingPage::query()
            ->published()
            ->with('seo.ogMedia')
            // No fulltext index on this table on purpose: it holds one row per
            // ranking page — seven of them — so a scan is cheaper than an index and
            // cannot fall foul of the stopword list.
            ->where(fn (Builder $q) => $this->matches($q, $term, ['title', 'intro', 'metric_label'], 'title', fulltext: false))
            ->limit(self::CANDIDATES_PER_SOURCE)
            ->get();

        return array_values($pages->map(fn (TopRankingPage $page): array => [
            'result' => new SearchResult(
                type: SearchResultType::TopRanking,
                id: $page->public_id,
                title: $page->title,
                url: '/top-ranking/'.$page->slug,
                summary: $page->intro,
                image: $page->seo?->ogMedia?->url(),
                score: 0,
                context: $page->platform->label(),
            ),
            'body' => trim($page->intro.' '.$page->metric_label.' '.$page->platform->label()),
        ])->all());
    }

    /**
     * Find everything that could plausibly be a hit. Ordering happens later.
     *
     * Three passes, because no one of them is sufficient on its own:
     *
     * 1. `MATCH … AGAINST`, which is the only index-backed way to reach into a body
     *    of text, and brings stemming with it.
     * 2. The whole phrase as a `LIKE` against every searchable column. Fulltext
     *    silently ignores words below `innodb_ft_min_token_size` and everything on
     *    the stopword list, so "seo" and "how to" find nothing through it — and
     *    InnoDB does not index a row until its transaction commits, which makes the
     *    fulltext path invisible to anything running inside one.
     * 3. Each word of the query against the title alone, so "calculator youtube"
     *    still reaches "YouTube Money Calculator". This deliberately over-fetches:
     *    a row matching only one word of a two-word query scores zero and is
     *    dropped by {@see rank()}, which is where relevance is actually decided.
     *
     * The `LIKE` passes cannot use an index, and that is an accepted cost: the
     * corpus is the tool catalog, the blog and a handful of pages — a few hundred
     * rows — and the whole ranked answer is cached per term.
     *
     * @param  Builder<*>  $query
     * @param  list<string>  $columns
     */
    private function matches(Builder $query, string $term, array $columns, string $titleColumn, bool $fulltext = true): void
    {
        if ($fulltext) {
            $query->whereFullText($columns, $term);
        }

        $phrase = '%'.$this->escapeLike($term).'%';

        foreach ($columns as $index => $column) {
            // `where` rather than `orWhere` for the first clause of a group with no
            // fulltext term in front of it, so the group is not left starting on an
            // `OR` that Laravel would have to guess the meaning of.
            $method = ($fulltext || $index > 0) ? 'orWhere' : 'where';

            $query->{$method}($column, 'like', $phrase);
        }

        foreach ($this->words($term) as $word) {
            $query->orWhere($titleColumn, 'like', '%'.$this->escapeLike($word).'%');
        }
    }

    /**
     * The query split into the words worth matching separately.
     *
     * Capped, so a pasted paragraph cannot turn into a hundred `OR LIKE` clauses;
     * single characters are dropped, because `%a%` matches the entire catalog.
     *
     * @return list<string>
     */
    private function words(string $term): array
    {
        $words = array_values(array_filter(
            preg_split('/\s+/', $term) ?: [],
            static fn (string $word): bool => Str::length($word) >= 2,
        ));

        return array_slice($words, 0, 5);
    }

    /** `%` and `_` in a user's query are literal characters, not wildcards. */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }

    // ── Ranking ──────────────────────────────────────────────────────────────

    /**
     * Score every candidate, drop the misses, order the rest.
     *
     * @param  list<array{result: SearchResult, body: string}>  $candidates
     * @return list<SearchResult>
     */
    private function rank(array $candidates, string $term): array
    {
        $words = $this->words($term);

        $scored = [];

        foreach ($candidates as $candidate) {
            $score = $this->score($candidate['result'], $candidate['body'], $term, $words);

            // Zero means the SQL matched on something this scorer cannot see — a
            // fulltext hit on a stemmed form, most often. Keeping it would put a
            // result in the list with no visible reason for being there.
            if ($score <= 0) {
                continue;
            }

            $scored[] = $candidate['result']->withScore($score);
        }

        usort($scored, static function (SearchResult $a, SearchResult $b): int {
            return $b->score <=> $a->score
                ?: $b->type->weight() <=> $a->type->weight()
                ?: strcmp($a->title, $b->title);
        });

        return $scored;
    }

    /**
     * How well one result answers the query.
     *
     * The bands are wide and deliberately far apart, so a title match can never be
     * outscored by an accumulation of body matches. Reading top to bottom, this is
     * the requirement in order: exact first, then title, then summary, then body.
     *
     * @param  list<string>  $words
     */
    private function score(SearchResult $result, string $body, string $term, array $words): int
    {
        $title = $this->normalise($result->title);
        $summary = $this->normalise((string) $result->summary);
        $body = $this->normalise($body);

        $score = 0;

        if ($title === $term) {
            $score = 1000;
        } elseif (str_starts_with($title, $term)) {
            $score = 800;
        } elseif ($this->containsWord($title, $term)) {
            $score = 700;
        } elseif (str_contains($title, $term)) {
            $score = 600;
        } elseif ($this->allWordsIn($title, $words)) {
            // Every word present but not as a phrase — "youtube calculator" against
            // "YouTube Money Calculator", which is the query people actually type.
            $score = 500;
        } elseif (str_contains($summary, $term)) {
            $score = 400;
        } elseif (str_contains($body, $term)) {
            $score = 300;
        } elseif ($this->allWordsIn($summary.' '.$body, $words)) {
            $score = 200;
        }

        if ($score === 0) {
            return 0;
        }

        // Small, bounded top-ups so that two results in the same band are separated
        // by how much else they match — never enough to cross into the band above.
        if ($score < 600 && str_contains($summary, $term)) {
            $score += 40;
        }

        if ($score < 600 && str_contains($body, $term)) {
            $score += 20;
        }

        // A shorter title containing the query is a closer answer than a long one:
        // "Hashtag Generator" beats "The Complete Hashtag Generator Playbook".
        $score += max(0, 20 - intdiv(Str::length($title), 8));

        return $score + $result->type->weight();
    }

    /** Whole-word containment, so "art" does not match "start". */
    private function containsWord(string $haystack, string $needle): bool
    {
        return preg_match('/(?:^|\W)'.preg_quote($needle, '/').'(?:\W|$)/u', $haystack) === 1;
    }

    /** @param list<string> $words */
    private function allWordsIn(string $haystack, array $words): bool
    {
        if ($words === []) {
            return false;
        }

        foreach ($words as $word) {
            if (! str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    /** Lowercase, collapsed whitespace — the one form everything is compared in. */
    private function normalise(string $value): string
    {
        return trim(Str::lower(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
