<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\TopRanking\Models\TopRankingPage;
use App\Http\Resources\SeoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ranking page as the admin manages it.
 *
 * @mixin TopRankingPage
 */
final class AdminTopRankingPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'platform' => $this->platform->value,
            'platform_label' => $this->platform->label(),
            'platform_accent' => $this->platform->accent(),

            'metric_label' => $this->metric_label,
            'metric_unit' => $this->metric_unit,
            'secondary_metric_label' => $this->secondary_metric_label,
            'secondary_metric_unit' => $this->secondary_metric_unit,
            'intro' => $this->intro,
            'seo' => $this->whenLoaded(
                'seo',
                fn (): ?array => $this->seo === null
                    ? null
                    : (new SeoResource($this->seo))->toArray($request),
            ),

            'source_page' => $this->source_page,
            'source_table' => $this->source_table,
            'source_url' => $this->sourceUrl(),
            'row_limit' => $this->row_limit,

            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,

            'sync_status' => $this->sync_status->value,
            'sync_status_label' => $this->sync_status->label(),
            'sync_message' => $this->sync_message,
            'synced_at' => $this->synced_at?->toIso8601String(),
            'days_since_sync' => $this->daysSinceSync(),
            'avatars_synced_at' => $this->avatars_synced_at?->toIso8601String(),

            'entries_count' => (int) ($this->entries_count
                ?? ($this->relationLoaded('entries') ? $this->entries->count() : 0)),

            // How many rows the public page is drawing a monogram for. The single
            // number an editor needs to decide whether this page is worth a pass —
            // and cheap, because the list screen counts it in the same query.
            'missing_avatars' => $this->when(
                $this->resource->getAttribute('missing_avatars') !== null,
                fn (): int => (int) $this->resource->getAttribute('missing_avatars'),
            ),

            'entries' => AdminTopRankingEntryResource::collection($this->whenLoaded('entries')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
