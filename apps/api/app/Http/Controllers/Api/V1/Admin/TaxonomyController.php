<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Blog\Models\PostCategory;
use App\Domain\Blog\Models\Tag;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminTaxonomyResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Blog categories and tags.
 *
 * One controller for two near-identical resources rather than two that drift: the
 * only real difference is which model is being written, and duplicating slug
 * generation and delete-guards across both is how they end up behaving differently.
 */
final class TaxonomyController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return ApiCollection<AdminTaxonomyResource> */
    public function categories(): ApiCollection
    {
        $categories = PostCategory::query()
            ->withCount('posts')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return new ApiCollection($categories, AdminTaxonomyResource::class);
    }

    /** @return ApiCollection<AdminTaxonomyResource> */
    public function tags(Request $request): ApiCollection
    {
        $tags = Tag::query()
            ->withCount('posts')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return new ApiCollection($tags, AdminTaxonomyResource::class);
    }

    /**
     * One category, addressed by slug.
     *
     * Editing happens on its own page rather than in a panel over the list, so the
     * screen has to be able to resolve its subject from the URL alone.
     */
    public function showCategory(PostCategory $category): AdminTaxonomyResource
    {
        return new AdminTaxonomyResource($category->loadCount('posts'));
    }

    public function storeCategory(Request $request): AdminTaxonomyResource
    {
        return new AdminTaxonomyResource($this->write($request, new PostCategory, 'post_categories'));
    }

    public function updateCategory(Request $request, PostCategory $category): AdminTaxonomyResource
    {
        return new AdminTaxonomyResource($this->write($request, $category, 'post_categories'));
    }

    public function destroyCategory(Request $request, PostCategory $category): JsonResponse
    {
        // Posts do not cascade — a category is a label, and deleting a label must
        // never take the writing with it. The FK is `nullOnDelete`, so this only
        // orphans them; saying so up front is kinder than letting it surprise.
        $this->audit->record('deleted', $category, $request->user(), before: [
            'name' => $category->name,
            'posts' => $category->posts()->count(),
        ]);

        $category->delete();

        return response()->json(status: 204);
    }

    /** One tag, addressed by slug. See {@see self::showCategory()}. */
    public function showTag(Tag $tag): AdminTaxonomyResource
    {
        return new AdminTaxonomyResource($tag->loadCount('posts'));
    }

    public function storeTag(Request $request): AdminTaxonomyResource
    {
        return new AdminTaxonomyResource($this->write($request, new Tag, 'tags'));
    }

    public function updateTag(Request $request, Tag $tag): AdminTaxonomyResource
    {
        return new AdminTaxonomyResource($this->write($request, $tag, 'tags'));
    }

    public function destroyTag(Request $request, Tag $tag): JsonResponse
    {
        $this->audit->record('deleted', $tag, $request->user(), before: ['name' => $tag->name]);

        $tag->delete();

        return response()->json(status: 204);
    }

    /**
     * @template TModel of PostCategory|Tag
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function write(Request $request, PostCategory|Tag $model, string $table): PostCategory|Tag
    {
        $validated = $request->validate([
            'name' => [$model->exists ? 'sometimes' : 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:140', Rule::unique($table, 'slug')->ignore($model)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            // Categories are ordered by hand; tags are ordered by how much they are
            // used, so the column only exists on one of them.
            'sort_order' => [$model instanceof PostCategory ? 'sometimes' : 'prohibited', 'integer', 'min:0', 'max:999'],
            'accent_color' => [$model instanceof PostCategory ? 'sometimes' : 'prohibited', 'nullable', 'string', 'max:20'],
        ]);

        $before = $model->exists ? $model->only(['name', 'slug', 'description']) : [];

        $model->fill($validated);

        if (blank($model->slug)) {
            $model->slug = Str::slug((string) $model->name);
        }

        $model->save();

        $this->audit->record(
            event: $before === [] ? 'created' : 'updated',
            subject: $model,
            causer: $request->user(),
            before: $before,
            after: $model->only(['name', 'slug', 'description']),
        );

        return $model->loadCount('posts');
    }
}
