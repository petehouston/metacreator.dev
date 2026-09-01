<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Changelog\Models\ChangelogRelease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A release as the public timeline reads it.
 *
 * Items ship with the release rather than behind a second request: a changelog card
 * is its entries, and a timeline that fetched them per release would issue twenty
 * requests to render one screen.
 *
 * @mixin ChangelogRelease
 */
final class ChangelogReleaseResource extends JsonResource
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
            'is_major' => $this->is_major,
            'released_at' => $this->released_at?->toIso8601String(),
            'items' => ChangelogItemResource::collection($this->whenLoaded('items')),
            // A pre-counted breakdown ("3 new, 1 fixed"), so a client can render the
            // summary line without walking every item on every release.
            'counts' => $this->whenLoaded('items', fn () => $this->items
                ->groupBy(fn ($item) => $item->type->value)
                ->map->count()
                ->all()),
        ];
    }
}
