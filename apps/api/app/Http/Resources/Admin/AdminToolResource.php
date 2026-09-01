<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Tools\Models\Tool;
use App\Http\Resources\SeoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A catalog row as an admin edits it — everything the public resource hides,
 * including the draft state, the schema and the lifetime counters.
 *
 * @mixin Tool
 */
final class AdminToolResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'key' => $this->key,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'tier' => $this->tier->value,
            'status' => $this->status->value,
            'is_visible' => (bool) $this->is_visible,
            'is_featured' => $this->isFeatured(),
            'version' => $this->version,
            'sort_order' => (int) $this->sort_order,
            'platforms' => $this->platformList(),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ]),
            'config' => $this->config,
            // The caps this row sets for itself, already normalised out of the two
            // places they can be stored. The editor renders these rather than
            // re-deriving the legacy `runs_per_day` key in TypeScript.
            'run_limits' => $this->quotaOverrides(),
            // Null, not an empty object, when the tool has never been tuned: the
            // form can then show its placeholders rather than blank overrides.
            'seo' => $this->whenLoaded(
                'seo',
                fn (): ?array => $this->seo === null
                    ? null
                    : (new SeoResource($this->seo))->toArray($request),
            ),
            'stats' => [
                'runs' => (int) $this->run_count,
                'avg_duration_ms' => (int) $this->avg_duration_ms,
                'success_rate' => (float) $this->success_rate,
                'grants' => $this->whenNotNull($this->grants_count ?? null),
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
