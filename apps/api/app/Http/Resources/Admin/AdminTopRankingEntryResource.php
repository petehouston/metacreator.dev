<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\TopRanking\Models\TopRankingEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row as the editor manages it.
 *
 * Adds what the public resource deliberately hides: the raw avatar link even when
 * it has expired, why it is in whatever state it is in, and where the row came
 * from. The editor's job is to fix the rows that are wrong, which means seeing the
 * ones the public page is quietly drawing a monogram for.
 *
 * @mixin TopRankingEntry
 */
final class AdminTopRankingEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $expired = $this->avatar_expires_at !== null && $this->avatar_expires_at->isPast();

        return [
            'id' => $this->id,
            'rank' => $this->sort_order,
            'name' => $this->name,
            'handle' => $this->handle,
            'owner' => $this->owner,
            'profile_url' => $this->profile_url,
            'metric' => $this->metric_value === null ? null : (float) $this->metric_value,
            'secondary_metric' => $this->secondary_metric_value === null ? null : (float) $this->secondary_metric_value,
            'country' => $this->country,
            'category' => $this->category,
            'language' => $this->language,
            'description' => $this->description,

            'avatar_url' => $this->avatar_url,
            // An expired link reports as expired here rather than as "ok", so the
            // status column matches what a reader would actually see on the page.
            'avatar_status' => $expired ? 'expired' : $this->avatar_status->value,
            'avatar_status_label' => $expired ? 'Expired' : $this->avatar_status->label(),
            'avatar_source' => $this->avatar_source,
            'avatar_checked_at' => $this->avatar_checked_at?->toIso8601String(),
            'avatar_expires_at' => $this->avatar_expires_at?->toIso8601String(),
            'initials' => $this->initials(),

            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'is_pinned' => $this->is_pinned,
        ];
    }
}
