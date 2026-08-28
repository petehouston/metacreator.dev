<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Blog\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post row for the management list.
 *
 * `blocks` is deliberately absent: the list renders forty rows, and shipping forty
 * block documents to draw forty titles is how a management screen becomes slow.
 * The detail endpoint carries the content.
 *
 * @mixin Post
 */
final class AdminPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_featured' => (bool) $this->is_featured,
            'word_count' => (int) $this->word_count,
            'reading_minutes' => (int) $this->reading_minutes,
            'view_count' => (int) $this->view_count,
            'version' => (int) $this->version,
            'author' => $this->whenLoaded('author', fn (): ?array => $this->author === null ? null : [
                'id' => $this->author->public_id,
                'display_name' => $this->author->displayName(),
                'initials' => $this->author->initials(),
            ]),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ]),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category): array => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
            ])->all()),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag): array => [
                'id' => $tag->id,
                'slug' => $tag->slug,
                'name' => $tag->name,
            ])->all()),
            'published_at' => $this->published_at?->toIso8601String(),
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'allowed_transitions' => array_map(
                static fn ($status): string => $status->value,
                $this->status->allowedTransitions(),
            ),
        ];
    }
}
