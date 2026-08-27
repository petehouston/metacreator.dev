<?php

declare(strict_types=1);

namespace App\Domain\Blog\Services;

use App\Domain\Blog\Models\Post;
use Illuminate\Database\Eloquent\Collection;

/**
 * Chooses the "keep reading" posts under a published article.
 *
 * Ranking is shared tags → same category → recency (docs/09). It runs as one query
 * with a computed score rather than three queries plus a merge, so the ordering is
 * stable and the page cost does not grow with the number of candidates.
 */
final class RelatedPostService
{
    public function __construct(private readonly int $limit = 3) {}

    /** @return Collection<int, Post> */
    public function for(Post $post): Collection
    {
        $tagIds = $post->tags->pluck('id')->all();

        return Post::query()
            ->public()
            ->whereKeyNot($post->getKey())
            // `featuredMedia` has to be eager-loaded here, not just declared on the
            // resource: PostResource exposes the image behind `whenLoaded`, so an
            // unloaded relation silently drops the key and the "keep reading" cards
            // render with an empty grey box where the thumbnail should be.
            ->with([
                'category:id,slug,name,accent_color',
                'author:id,name,avatar_path',
                'featuredMedia',
            ])
            ->select('posts.*')
            // Shared tags dominate; same-category is the tie-breaker; `published_at`
            // below settles the rest. Weighting keeps it to a single ORDER BY.
            ->selectRaw(
                '(SELECT COUNT(*) FROM post_tag WHERE post_tag.post_id = posts.id AND post_tag.tag_id IN ('
                .($tagIds === [] ? 'NULL' : implode(',', array_fill(0, count($tagIds), '?')))
                .')) * 10 + IF(posts.category_id <=> ?, 3, 0) AS relevance',
                [...$tagIds, $post->category_id],
            )
            ->orderByDesc('relevance')
            ->orderByDesc('published_at')
            ->limit($this->limit)
            ->get();
    }
}
