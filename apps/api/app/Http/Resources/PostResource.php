<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Blog\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Card shape for the blog grid and archives. No `blocks` — a listing of twelve
 * posts must not ship twelve full articles to render twelve excerpts.
 *
 * {@see PostDetailResource} extends this to add the block document, so it is not
 * final — the card fields are deliberately shared between the two.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'featured_image' => $this->whenLoaded('featuredMedia', fn () => $this->featuredMedia === null ? null : [
                'url' => $this->featuredMedia->url(),
                'alt' => $this->featuredMedia->alt_text ?? '',
                'width' => $this->featuredMedia->width,
                'height' => $this->featuredMedia->height,
                'blur_hash' => $this->featuredMedia->blur_hash,
            ]),
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
                'accent_color' => $this->category->accent_color,
            ]),
            'author' => $this->whenLoaded('author', fn () => new PostAuthorResource($this->author)),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'is_featured' => $this->is_featured,
            'reading_minutes' => $this->reading_minutes,
            'word_count' => $this->word_count,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
