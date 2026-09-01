<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domain\Analytics\Services\FunnelRecorder;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolCategory;
use App\Domain\Tools\Services\FavoriteTools;
use App\Domain\Tools\Services\ToolAccessService;
use App\Domain\Tools\Services\TrendingTools;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\ToolCategoryResource;
use App\Http\Resources\ToolDetailResource;
use App\Http\Resources\ToolResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public catalog. Thin by design: filtering lives in model scopes and access in
 * {@see ToolAccessService}, so this only translates HTTP into those.
 */
final class ToolCatalogController extends Controller
{
    public function __construct(
        private readonly ToolAccessService $access,
        private readonly FunnelRecorder $funnel,
        private readonly TrendingTools $trending,
        private readonly FavoriteTools $favorites,
    ) {}

    /** @return ApiCollection<ToolResource> */
    public function index(Request $request): ApiCollection
    {
        $tools = Tool::query()
            ->public()
            // One extra query for the whole page, not one per card: the sitemap
            // needs to know which of these are set to no-index.
            ->with(['category:id,slug,name,icon,accent_color', 'seo:id,seoable_type,seoable_id,robots'])
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->string('q')))
            ->when($request->filled('filter.category'), fn ($q) => $q->whereRelation(
                'category', 'slug', $request->string('filter.category')
            ))
            ->when($request->filled('filter.platform'), fn ($q) => $q->platform(
                (string) $request->string('filter.platform')
            ))
            ->when($request->filled('filter.tier'), fn ($q) => $q->tier(
                ToolTier::from((string) $request->string('filter.tier'))
            ))
            ->when($request->boolean('filter.featured'), fn ($q) => $q->whereNotNull('featured_at'))
            ->orderByRaw('featured_at IS NULL, featured_at DESC')
            ->orderBy('sort_order')
            ->orderByDesc('run_count')
            // The public catalog renders every tool on one page and filters client-side,
            // so the cap has to clear the whole catalog rather than one screen of it.
            ->paginate(perPage: min(250, $request->integer('per_page', 24)))
            ->withQueryString();

        // One bulk decision for the whole page rather than N entitlement lookups.
        $accessMap = $this->access->decideMany($tools->items(), $request->user());

        // Both orderings travel with the page rather than being re-derived per card:
        // the catalog filters and sorts client-side, so it needs the whole ranking
        // once, not a request per sort change.
        return (new ApiCollection($tools, ToolResource::class))->additional([
            'meta' => [
                'access' => $accessMap,
                'trending' => $this->trending->describe(),
                // Empty for a guest, which is exactly what the Favourites sort
                // should do when nobody is signed in.
                'favorites' => $this->favorites->slugsFor($request->user()),
            ],
        ]);
    }

    /**
     * The trending ranking on its own.
     *
     * Split out because the catalog page is server-rendered and cached for everyone,
     * so it cannot carry a ranking that changes every ten minutes without holding
     * the whole page hostage to the shortest-lived thing on it.
     */
    public function trending(): JsonResource
    {
        return new JsonResource($this->trending->describe());
    }

    public function show(Request $request, string $slug): ToolDetailResource
    {
        $tool = Tool::query()
            ->public()
            ->with([
                'category',
                // `ogMedia` with it: the social card image is part of the same one
                // round trip the page is built around.
                'seo.ogMedia',
                'related' => fn ($q) => $q->public()->with('category:id,slug,name')->limit(6),
                'successor:id,slug,name',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        // The top of the funnel. Queued, so a view costs the visitor nothing.
        $this->funnel->view($tool->id);

        $decision = $this->access->decide($tool, $request->user());
        $user = $request->user();

        return (new ToolDetailResource($tool))
            ->withAccess($decision)
            ->withFavorite($user === null ? null : $this->favorites->has($user, $tool));
    }

    /** @return ApiCollection<ToolCategoryResource> */
    public function categories(): ApiCollection
    {
        $categories = ToolCategory::query()
            ->visible()
            ->withCount(['tools' => fn ($q) => $q->public()])
            ->get();

        return new ApiCollection($categories, ToolCategoryResource::class);
    }
}
