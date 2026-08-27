<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Tools\Actions\SyncToolPlatforms;
use App\Domain\Tools\Enums\ToolStatus;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateToolRequest;
use App\Http\Resources\Admin\AdminTaxonomyResource;
use App\Http\Resources\Admin\AdminToolResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\Request;

/**
 * Catalog management.
 *
 * An admin changes what a tool *is* — its tier, its visibility, its copy — never
 * what it *does*: behaviour lives in the runner bound to `key`, and `key` is not
 * editable. A catalog row whose key drifted from its runner is a 500 on the next
 * run, which the architecture test exists to prevent.
 */
final class ToolController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SyncToolPlatforms $syncPlatforms,
    ) {}

    /** @return ApiCollection<AdminToolResource> */
    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filter.tier' => ['sometimes', 'nullable', 'in:free,account,premium'],
            'filter.status' => ['sometimes', 'nullable', 'in:draft,published,hidden,deprecated'],
            'filter.category' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $tools = Tool::query()
            ->with('category:id,slug,name,icon,accent_color')
            ->withCount('grants')
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->string('q')))
            ->when(
                $request->filled('filter.tier'),
                fn ($q) => $q->where('tier', $request->string('filter.tier'))
            )
            ->when(
                $request->filled('filter.status'),
                fn ($q) => $q->where('status', $request->string('filter.status'))
            )
            ->when($request->filled('filter.category'), fn ($q) => $q->whereRelation(
                'category', 'slug', $request->string('filter.category')
            ))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(perPage: min(100, $request->integer('per_page', 40)))
            ->withQueryString();

        return new ApiCollection($tools, AdminToolResource::class);
    }

    public function show(Tool $tool): AdminToolResource
    {
        return new AdminToolResource($tool->load('category')->loadCount('grants'));
    }

    public function update(UpdateToolRequest $request, Tool $tool): AdminToolResource
    {
        $tracked = ['tier', 'status', 'is_visible', 'name', 'tagline', 'sort_order', 'category_id'];
        $before = $tool->only($tracked);

        $data = $request->validated();

        // `featured` is a timestamp in the database and a switch in the UI. Doing the
        // translation here keeps "when was this featured?" answerable without asking
        // the client to invent a timestamp.
        if (array_key_exists('is_featured', $data)) {
            $data['featured_at'] = $data['is_featured'] ? ($tool->featured_at ?? now()) : null;
            unset($data['is_featured']);
        }

        // Publishing for the first time stamps the date the catalog page cites.
        if (($data['status'] ?? null) === ToolStatus::Published->value && $tool->published_at === null) {
            $data['published_at'] = now();
        }

        $tool->fill($data)->save();

        if (array_key_exists('platforms', $data)) {
            $this->syncPlatforms->handle($tool, $data['platforms'] ?? []);
        }

        $this->audit->record(
            event: 'updated',
            subject: $tool,
            causer: $request->user(),
            before: $before,
            after: $tool->only($tracked),
        );

        return new AdminToolResource($tool->refresh()->load('category')->loadCount('grants'));
    }

    /** @return ApiCollection<AdminTaxonomyResource> */
    public function categories(): ApiCollection
    {
        $categories = ToolCategory::query()
            ->withCount('tools')
            ->orderBy('sort_order')
            ->get();

        return new ApiCollection($categories, AdminTaxonomyResource::class);
    }
}
