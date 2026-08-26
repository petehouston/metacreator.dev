<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolCategory;
use App\Domain\Tools\Services\ToolAccessService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\ToolCategoryResource;
use App\Http\Resources\ToolDetailResource;
use App\Http\Resources\ToolResource;
use Illuminate\Http\Request;

/**
 * The public catalog. Thin by design: filtering lives in model scopes and access in
 * {@see ToolAccessService}, so this only translates HTTP into those.
 */
final class ToolCatalogController extends Controller
{
    public function __construct(private readonly ToolAccessService $access) {}

    /** @return ApiCollection<ToolResource> */
    public function index(Request $request): ApiCollection
    {
        $tools = Tool::query()
            ->public()
            ->with('category:id,slug,name,icon,accent_color')
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
            ->paginate(perPage: min(48, $request->integer('per_page', 24)))
            ->withQueryString();

        // One bulk decision for the whole page rather than N entitlement lookups.
        $accessMap = $this->access->decideMany($tools->items(), $request->user());

        return (new ApiCollection($tools, ToolResource::class))->additional([
            'meta' => ['access' => $accessMap],
        ]);
    }

    public function show(Request $request, string $slug): ToolDetailResource
    {
        $tool = Tool::query()
            ->public()
            ->with([
                'category',
                'seo',
                'related' => fn ($q) => $q->public()->with('category:id,slug,name')->limit(6),
                'successor:id,slug,name',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $decision = $this->access->decide($tool, $request->user());

        return (new ToolDetailResource($tool))->withAccess($decision);
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
