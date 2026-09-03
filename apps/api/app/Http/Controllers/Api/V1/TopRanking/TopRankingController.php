<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TopRanking;

use App\Domain\TopRanking\Models\TopRankingPage;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\TopRankingPageResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public top-ranking pages.
 *
 * Two endpoints and no pagination anywhere, which is deliberate on both counts.
 * The index is what draws the header menu, so it has to be the whole list or the
 * menu is wrong; and a ranking is capped at 250 rows by validation, so a single
 * response is always a sensible size and a paginated table would make "the top 100"
 * arrive in two pieces.
 */
final class TopRankingController extends Controller
{
    /**
     * Every published page — the header menu, and the index page's cards.
     *
     * @return ApiCollection<TopRankingPageResource>
     */
    public function index(): ApiCollection
    {
        $pages = TopRankingPage::query()
            ->published()
            ->withCount('entries')
            ->with('seo')
            ->inMenuOrder()
            ->get();

        return new ApiCollection($pages, TopRankingPageResource::class);
    }

    /** One page with its whole table. */
    public function show(string $slug): TopRankingPageResource
    {
        $page = TopRankingPage::query()
            ->published()
            ->where('slug', $slug)
            ->with(['entries', 'seo.ogMedia'])
            ->first();

        if ($page === null) {
            throw new NotFoundHttpException('No ranking page at that address.');
        }

        // A page that has never synced has no rows, and an empty table under a
        // confident heading is worse than no page: it reads as a product bug rather
        // than as work in progress. It 404s until it has something to say.
        if ($page->entries->isEmpty()) {
            throw new NotFoundHttpException('That ranking has not been published yet.');
        }

        return new TopRankingPageResource($page);
    }
}
