<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Blog\Actions\SavePostAction;
use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePostRequest;
use App\Http\Resources\Admin\AdminMediaResource;
use App\Http\Resources\Admin\AdminPostResource;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\SeoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The WordPress-shaped management surface for the blog: a filterable list, bulk
 * actions, and one editor endpoint that saves content and metadata together.
 *
 * Ownership scoping is enforced per row (docs/06): a contributor holds
 * `posts.update.own` and this is where "own" is actually checked — the route
 * middleware can only tell that they may update *something*.
 */
final class PostController extends Controller
{
    public function __construct(
        private readonly SavePostAction $save,
        private readonly AuditLogger $audit,
    ) {}

    /** @return ApiCollection<AdminPostResource> */
    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filter.status' => ['sometimes', 'nullable', 'in:draft,scheduled,published,unpublished,archived,trashed'],
            'filter.category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filter.author' => ['sometimes', 'nullable', 'string', 'max:60'],
            'sort' => ['sometimes', 'nullable', 'in:updated_at,-updated_at,published_at,-published_at,title,-title,view_count,-view_count'],
        ]);

        $sort = (string) ($request->query('sort') ?: '-updated_at');
        $trashed = $request->input('filter.status') === 'trashed';

        $posts = Post::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->with(['author:id,ulid,display_name,name,email', 'category:id,slug,name', 'tags:id,slug,name'])
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->string('q')))
            ->when(
                ! $trashed && $request->filled('filter.status'),
                fn ($q) => $q->where('status', $request->string('filter.status'))
            )
            ->when($request->filled('filter.category'), fn ($q) => $q->whereRelation(
                'category', 'slug', $request->string('filter.category')
            ))
            ->when($request->filled('filter.author'), fn ($q) => $q->whereRelation(
                'author', 'ulid', $this->toUlid((string) $request->string('filter.author'))
            ))
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->paginate(perPage: min(100, $request->integer('per_page', 25)))
            ->withQueryString();

        return (new ApiCollection($posts, AdminPostResource::class))->additional([
            'meta' => ['counts' => $this->statusCounts()],
        ]);
    }

    /** The editor's payload: content, metadata and SEO in one request. */
    public function show(Request $request, Post $post): JsonResource
    {
        $this->authorizeRow($request, $post, 'view');

        $post->load(['author:id,ulid,display_name,name', 'category', 'categories', 'tags', 'seo', 'featuredMedia']);

        return new JsonResource([
            'post' => (new AdminPostResource($post))->toArray($request),
            'blocks' => $post->blocks,
            'seo' => $post->seo === null ? null : (new SeoResource($post->seo))->toArray($request),
            'featured_media' => $post->featuredMedia === null
                ? null
                : (new AdminMediaResource($post->featuredMedia))->toArray($request),
            'revisions' => $post->revisions()->limit(20)->get()->map(fn ($revision): array => [
                'id' => $revision->id,
                'title' => $revision->title,
                'is_autosave' => (bool) $revision->is_autosave,
                'created_at' => $revision->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function store(SavePostRequest $request): JsonResource
    {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $post = $this->save->handle(new Post, $request->payload(), $actor);

        $this->audit->record('created', $post, $request->user(), after: [
            'title' => $post->title,
            'status' => $post->status->value,
        ]);

        return $this->show($request, $post);
    }

    public function update(SavePostRequest $request, Post $post): JsonResource
    {
        $this->authorizeRow($request, $post, 'update');

        // Optimistic concurrency (docs/05). Two editors in the same post is the
        // normal case, not the exotic one; silently letting the second save win is
        // how a morning's work disappears.
        abort_if(
            $request->filled('version') && (int) $request->integer('version') !== (int) $post->version,
            409,
            'Someone else saved this post while you were editing. Reload to see their changes.',
        );

        $actor = $request->user();
        abort_if($actor === null, 401);

        $before = $post->only(['title', 'status', 'category_id', 'is_featured']);

        $post = $this->save->handle($post, $request->payload(), $actor);

        $this->audit->record(
            'updated', $post, $request->user(),
            before: $before,
            after: $post->only(array_keys($before)),
        );

        return $this->show($request, $post);
    }

    /** Soft delete — a thirty-day recovery window, not an erasure (docs/09). */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorizeRow($request, $post, 'delete');

        $this->audit->record('deleted', $post, $request->user(), before: ['title' => $post->title]);

        $post->delete();

        return response()->json(status: 204);
    }

    public function restore(Request $request, string $post): AdminPostResource
    {
        $model = Post::onlyTrashed()->where('ulid', $this->toUlid($post))->firstOrFail();

        $model->restore();

        $this->audit->record('restored', $model, $request->user(), after: ['title' => $model->title]);

        return new AdminPostResource($model->load(['author', 'category', 'tags']));
    }

    /**
     * Bulk actions over a selection.
     *
     * Each row is authorized and transitioned individually rather than in one mass
     * update: a contributor selecting forty posts must still only affect their own,
     * and an illegal transition on row twelve must not silently apply to rows one
     * through eleven under a different rule.
     */
    public function bulk(Request $request): JsonResource
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['string', 'max:60'],
            'action' => ['required', 'in:publish,unpublish,archive,draft,delete,restore,feature,unfeature'],
        ]);

        $applied = [];
        $skipped = [];

        foreach ($validated['ids'] as $publicId) {
            $post = Post::withTrashed()->where('ulid', $this->toUlid($publicId))->first();

            if ($post === null || ! $this->mayTouch($request, $post, $validated['action'])) {
                $skipped[] = $publicId;

                continue;
            }

            $result = $this->applyBulk($post, $validated['action'], $request);

            $result ? $applied[] = $publicId : $skipped[] = $publicId;
        }

        return new JsonResource([
            'action' => $validated['action'],
            'applied' => $applied,
            'skipped' => $skipped,
            'counts' => $this->statusCounts(),
        ]);
    }

    private function applyBulk(Post $post, string $action, Request $request): bool
    {
        $actor = $request->user();

        if ($actor === null) {
            return false;
        }

        $target = match ($action) {
            'publish' => PostStatus::Published,
            'unpublish' => PostStatus::Unpublished,
            'archive' => PostStatus::Archived,
            'draft' => PostStatus::Draft,
            default => null,
        };

        if ($target !== null) {
            if (! $post->status->canTransitionTo($target)) {
                return false;
            }

            $this->save->handle($post, ['status' => $target->value], $actor);
            $this->audit->record($target->value, $post, $request->user(), after: ['status' => $target->value]);

            return true;
        }

        return match ($action) {
            'delete' => tap(true, function () use ($post, $request): void {
                $this->audit->record('deleted', $post, $request->user());
                $post->delete();
            }),
            'restore' => tap(true, function () use ($post, $request): void {
                $post->restore();
                $this->audit->record('restored', $post, $request->user());
            }),
            'feature', 'unfeature' => tap(true, function () use ($post, $action): void {
                $post->forceFill(['is_featured' => $action === 'feature'])->save();
            }),
            default => false,
        };
    }

    /**
     * The narrow permission `posts.update.own` only means anything once we know
     * which row is being touched — which is here, not in the route middleware.
     */
    private function authorizeRow(Request $request, Post $post, string $action): void
    {
        abort_unless($this->mayTouch($request, $post, $action), 403);
    }

    private function mayTouch(Request $request, Post $post, string $action): bool
    {
        $actor = $request->user();

        if ($actor === null) {
            return false;
        }

        $resource = match ($action) {
            'view' => 'posts.view',
            'delete' => 'posts.delete',
            'restore' => 'posts.restore',
            'publish', 'unpublish', 'archive' => 'posts.publish',
            default => 'posts.update',
        };

        if ($actor->can($resource)) {
            return true;
        }

        // Ownership scoping: the broadest match wins, and `.own` is the fallback.
        return in_array($action, ['view', 'update', 'draft', 'feature', 'unfeature'], true)
            && $actor->can('posts.update.own')
            && $post->author_id === $actor->id;
    }

    /** Accept a prefixed public id or a bare ULID; both reach staff by email. */
    private function toUlid(string $value): string
    {
        return strtoupper(str_contains($value, '_') ? substr($value, strrpos($value, '_') + 1) : $value);
    }

    /** @return array<string, int> */
    private function statusCounts(): array
    {
        $counts = Post::query()
            ->toBase()
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status')
            ->all();

        return [
            ...array_map(static fn (mixed $v): int => (int) $v, $counts),
            'trashed' => Post::onlyTrashed()->count(),
        ];
    }
}
