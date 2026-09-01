<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Changelog\Models\ChangelogRelease;
use App\Http\Resources\ChangelogItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A release as the admin needs it.
 *
 * Adds the three things the public resource has no business carrying: the status,
 * whether the row is actually live right now, and who wrote it.
 *
 * `is_live` is computed server-side rather than left to the client to derive from
 * status and date. That derivation is the one an editor gets wrong — a `published`
 * release dated next Tuesday is not live, and a list that labels it "Published"
 * without saying so is how an entry sits invisible for a week.
 *
 * @mixin ChangelogRelease
 */
final class AdminChangelogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'version' => $this->version,
            'title' => $this->title,
            'summary' => $this->summary,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_live' => $this->isPublic(),
            'is_major' => $this->is_major,
            'released_at' => $this->released_at?->toIso8601String(),
            'items' => ChangelogItemResource::collection($this->whenLoaded('items')),
            // `items_count` when the query counted it; otherwise the loaded relation,
            // so a `show` response reports a count without a second query.
            'items_count' => (int) ($this->items_count
                ?? ($this->relationLoaded('items') ? $this->items->count() : 0)),
            'author' => $this->whenLoaded('author', fn () => $this->author === null ? null : [
                'id' => $this->author->public_id,
                'display_name' => $this->author->display_name ?? $this->author->name,
            ]),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
