<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Blog;

use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Models\PostCategory;
use App\Domain\Blog\Models\Tag;
use App\Domain\Blog\Services\RelatedPostService;
use App\Http\Controllers\Api\V1\Catalog\ToolCatalogController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\PostCategoryResource;
use App\Http\Resources\PostDetailResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TagResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The public blog. Mirrors {@see ToolCatalogController}:
 * thin, with visibility in the model's `public` scope and ranking in a service.
 *
 * The `blog.enabled` middleware 404s every route in this group when an admin turns
 * the blog off, so no action here has to check the flag.
 */
final class BlogController extends Controller
{
    /** docs/09: 12 per page, so the 3-column grid always fills complete rows. */
    private const PER_PAGE = 12;

    public function __construct(private readonly RelatedPostService $related) {}

    /** @return ApiCollection<PostResource> */
    public function index(Request $request): ApiCollection
    {
        $posts = Post::query()
            ->public()
            ->with(['category:id,slug,name,accent_color', 'author:id,name,display_name,avatar_path', 'featuredMedia'])
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->string('q')))
            ->when($request->filled('filter.category'), fn ($q) => $q->whereRelation(
                'category', 'slug', $request->string('filter.category')
            ))
            ->when($request->filled('filter.tag'), fn ($q) => $q->whereRelation(
                'tags', 'slug', $request->string('filter.tag')
            ))
            ->when($request->boolean('filter.featured'), fn ($q) => $q->where('is_featured', true))
            ->orderByDesc('published_at')
            ->paginate(perPage: min(24, $request->integer('per_page', self::PER_PAGE)))
            ->withQueryString();

        return new ApiCollection($posts, PostResource::class);
    }

    public function show(string $slug): PostDetailResource
    {
        $post = Post::query()
            ->with([
                'category:id,slug,name,accent_color',
                'author:id,name,display_name,avatar_path',
                'featuredMedia',
                'tags',
                'seo.ogMedia',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        // A post that was public and was withdrawn returns 410 rather than 404, so
        // search engines drop the URL promptly instead of re-crawling it for months.
        if (! $post->isPublic()) {
            throw new HttpException(
                $post->status->isGone() ? 410 : 404,
                'This post is not available.',
            );
        }

        return (new PostDetailResource($post))->withRelated($this->related->for($post));
    }

    /** @return ApiCollection<PostCategoryResource> */
    public function categories(): ApiCollection
    {
        $categories = PostCategory::query()
            ->ordered()
            ->withCount(['posts' => fn ($q) => $q->public()])
            // A category with nothing published in it is an empty page and a weak
            // sitemap entry, so it stays out of the public list.
            ->having('posts_count', '>', 0)
            ->get();

        return new ApiCollection($categories, PostCategoryResource::class);
    }

    /** @return ApiCollection<TagResource> */
    public function tags(): ApiCollection
    {
        $tags = Tag::query()
            ->withCount(['posts' => fn ($q) => $q->public()])
            ->having('posts_count', '>', 0)
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->get();

        return new ApiCollection($tags, TagResource::class);
    }
}
