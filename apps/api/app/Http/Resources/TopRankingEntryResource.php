<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\TopRanking\Models\TopRankingEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of a public ranking table.
 *
 * @mixin TopRankingEntry
 */
final class TopRankingEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->sort_order,
            'name' => $this->name,
            'handle' => $this->handle,
            'owner' => $this->owner,
            'profile_url' => $this->profile_url,

            // Cast off Eloquent's decimal string so the client gets a number and
            // does not have to decide whether "515.000" is text.
            'metric' => $this->metric_value === null ? null : (float) $this->metric_value,
            'secondary_metric' => $this->secondary_metric_value === null ? null : (float) $this->secondary_metric_value,

            'country' => $this->country,
            'category' => $this->category,
            'language' => $this->language,
            'description' => $this->description,

            // Null unless the link is both resolved *and* still inside its signature
            // window. The frontend therefore never has to reason about expiry — a
            // null here means "draw the monogram", which is exactly one branch.
            'avatar_url' => $this->hasUsableAvatar() ? $this->avatar_url : null,
            'initials' => $this->initials(),
        ];
    }
}
