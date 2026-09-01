<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Tools\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Catalog-card shape. Explicit field list — adding a database column can never
 * accidentally expose data through the API.
 *
 * @mixin Tool
 */
final class ToolResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $tier = $this->effectiveTier();

        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            // The *effective* tier, not the stored one: while billing is off a
            // premium tool is gated at `account`, and a card that still said "Pro"
            // would be advertising a plan the site does not sell.
            'tier' => [
                'value' => $tier->value,
                'label' => $tier->label(),
                'description' => $tier->description(),
            ],
            'platforms' => $this->platformList(),
            'category' => $this->whenLoaded('category', fn () => [
                'slug' => $this->category?->slug,
                'name' => $this->category?->name,
                'icon' => $this->category?->icon,
                'accent_color' => $this->category?->accent_color,
            ]),
            'is_featured' => $this->isFeatured(),
            'is_deprecated' => $this->status->value === 'deprecated',
            // So the sitemap can leave out what an admin has set to no-index.
            // Indexable unless a stored override says otherwise, which is what an
            // absent SEO row means for the long tail of tools nobody has tuned.
            'is_indexable' => $this->whenLoaded(
                'seo',
                fn (): bool => $this->seo?->isIndexable() ?? true,
                true,
            ),
            'stats' => [
                'runs' => $this->run_count,
                'avg_duration_ms' => $this->avg_duration_ms,
            ],
        ];
    }
}
