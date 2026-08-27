<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Blog\Models\PostCategory;
use App\Domain\Blog\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category or a tag, as the admin needs it.
 *
 * The distinguishing field is `id`. The public resources deliberately omit it —
 * numeric primary keys never leave the API (docs/05), and on the public side a
 * taxonomy is addressed by slug. The admin is different: `posts.category_id` and
 * the `post_tag` pivot are numeric, so an editor screen that only knew slugs could
 * render a category picker it could not actually save from.
 *
 * Every other admin endpoint already exposes numeric ids for the same reason —
 * plans, roles, grants, invoices. This is that convention, not an exception to it.
 *
 * @mixin PostCategory
 * @mixin Tag
 */
final class AdminTaxonomyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'accent_color' => $this->accent_color ?? null,
            'sort_order' => $this->sort_order ?? null,
            // `posts_count` when the query counted it, falling back to the
            // denormalised column so a list that skipped the count still shows one.
            'posts_count' => (int) ($this->posts_count ?? $this->post_count ?? 0),
            'tools_count' => $this->whenNotNull($this->tools_count ?? null),
        ];
    }
}
