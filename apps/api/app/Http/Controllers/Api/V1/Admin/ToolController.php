<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Seo\Models\SeoMeta;
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
        return new AdminToolResource($tool->load(['category', 'seo.ogMedia'])->loadCount('grants'));
    }

    public function update(UpdateToolRequest $request, Tool $tool): AdminToolResource
    {
        $tracked = ['tier', 'status', 'is_visible', 'name', 'tagline', 'sort_order', 'category_id'];
        $before = $tool->only($tracked);

        $data = $request->validated();

        // Held back from the fill: SEO lives in its own polymorphic row, and a
        // `seo` key reaching `fill()` would be a column that does not exist.
        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        // `featured` is a timestamp in the database and a switch in the UI. Doing the
        // translation here keeps "when was this featured?" answerable without asking
        // the client to invent a timestamp.
        if (array_key_exists('is_featured', $data)) {
            $data['featured_at'] = $data['is_featured'] ? ($tool->featured_at ?? now()) : null;
            unset($data['is_featured']);
        }

        // `config` holds runner settings this form knows nothing about alongside the
        // run caps it does, so a partial payload is merged rather than assigned —
        // saving the limits panel must not erase a runner's own configuration.
        if (array_key_exists('config', $data)) {
            $data['config'] = $this->mergeConfig($tool->config ?? [], $data['config'] ?? []);
        }

        // Publishing for the first time stamps the date the catalog page cites.
        if (($data['status'] ?? null) === ToolStatus::Published->value && $tool->published_at === null) {
            $data['published_at'] = now();
        }

        $tool->fill($data)->save();

        if (array_key_exists('platforms', $data)) {
            $this->syncPlatforms->handle($tool, $data['platforms'] ?? []);
        }

        if (is_array($seo)) {
            $this->saveSeo($tool, $seo);
        }

        $this->audit->record(
            event: 'updated',
            subject: $tool,
            causer: $request->user(),
            before: $before,
            after: $tool->only($tracked),
        );

        return new AdminToolResource($tool->refresh()->load(['category', 'seo.ogMedia'])->loadCount('grants'));
    }

    /**
     * Fold a partial `config` payload into the stored one.
     *
     * Nulls are removals, not values: the limits form sends `{daily: null}` for a
     * cleared field, and storing that would leave a key that reads as "capped at
     * nothing" to anyone inspecting the row. Deferring to the tier is the absence of
     * a number, so that is how it is stored.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeConfig(array $current, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if ($key === 'limits' && is_array($value)) {
                $limits = array_filter(
                    array_merge($current['limits'] ?? [], $value),
                    fn ($limit): bool => $limit !== null && $limit !== '',
                );

                // Setting a windowed daily cap retires the pre-window key, so the
                // two cannot disagree about what the daily number actually is.
                unset($current['runs_per_day']);

                $current['limits'] = $limits;

                continue;
            }

            if ($value === null) {
                unset($current[$key]);

                continue;
            }

            $current[$key] = $value;
        }

        if (($current['limits'] ?? null) === []) {
            unset($current['limits']);
        }

        return $current;
    }

    /**
     * Upsert the tool's SEO overrides.
     *
     * Only the keys the request actually carried are written, so a form that posts
     * the social fields alone cannot blank the meta description it never showed.
     *
     * @param  array<string, mixed>  $seo
     */
    private function saveSeo(Tool $tool, array $seo): void
    {
        $fields = array_intersect_key($seo, array_flip([
            'title', 'description', 'canonical_url', 'robots', 'focus_keyword',
            'og_title', 'og_description', 'og_media_id', 'twitter_card', 'schema_type',
        ]));

        if ($fields === []) {
            return;
        }

        // An empty string is how a cleared text input arrives; storing it would make
        // `?? fallback` on the frontend stop firing and publish a blank meta title.
        $fields = array_map(
            fn ($value) => is_string($value) && trim($value) === '' ? null : $value,
            $fields,
        );

        // `robots` and `twitter_card` are NOT NULL with a sensible default. A cleared
        // input means "back to the default", not "write null and hit a constraint".
        foreach (['robots' => 'index,follow', 'twitter_card' => 'summary_large_image'] as $column => $default) {
            if (array_key_exists($column, $fields) && $fields[$column] === null) {
                $fields[$column] = $default;
            }
        }

        SeoMeta::query()->updateOrCreate(
            ['seoable_type' => $tool->getMorphClass(), 'seoable_id' => $tool->id],
            $fields,
        );
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
