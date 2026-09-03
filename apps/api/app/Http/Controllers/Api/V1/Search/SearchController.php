<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Domain\Search\Data\SearchResult;
use App\Domain\Search\Enums\SearchResultType;
use App\Domain\Search\Services\SearchService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\SearchResultResource;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Global search.
 *
 * One endpoint serves both surfaces: the header dropdown asks for `per_page=5` and
 * reads `meta.page.total` to label its "see all" button, and the results page asks
 * for ten. A second "suggest" endpoint would be the same query with a different cap
 * — and a second place for the ranking to drift.
 *
 * The `search.enabled` middleware 404s the route when an admin turns search off, so
 * nothing here checks the flag.
 */
final class SearchController extends Controller
{
    /** docs/09's blog uses 12 for a 3-up grid; this is a list, and ten is a page. */
    private const PER_PAGE = 10;

    private const MAX_PER_PAGE = 50;

    /**
     * The longest query worth running.
     *
     * Not a validation rule an honest visitor will ever meet — it is a ceiling on
     * how much text a stranger can push through the scorer and into the cache key.
     */
    private const MAX_TERM_LENGTH = 120;

    public function __construct(private readonly SearchService $search) {}

    /** @return ApiCollection<SearchResultResource> */
    public function __invoke(Request $request): ApiCollection
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:'.self::MAX_TERM_LENGTH],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'filter.type' => ['nullable', 'string', 'in:'.implode(',', array_column(SearchResultType::cases(), 'value'))],
        ]);

        $type = isset($validated['filter']['type'])
            ? SearchResultType::from($validated['filter']['type'])
            : null;

        $results = $this->search->search((string) ($validated['q'] ?? ''), $type);

        return new ApiCollection($this->paginate($request, $results), SearchResultResource::class);
    }

    /**
     * Slice the ranked list into a page.
     *
     * Ranking happens over the whole candidate set, so pagination cannot be pushed
     * into SQL without losing the ordering that makes the first page correct. The
     * set is bounded by {@see SearchService}, so this slices an array of a few
     * hundred entries at most.
     *
     * @param  list<SearchResult>  $results
     * @return LengthAwarePaginator<int, SearchResult>
     */
    private function paginate(Request $request, array $results): LengthAwarePaginator
    {
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->integer('per_page', self::PER_PAGE)));
        $page = max(1, $request->integer('page', 1));

        return new LengthAwarePaginator(
            items: array_slice($results, ($page - 1) * $perPage, $perPage),
            total: count($results),
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
