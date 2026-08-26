<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Seo\Models\SeoMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SeoMeta */
final class SeoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical_url' => $this->canonical_url,
            'robots' => $this->robots,
            'og_title' => $this->og_title ?? $this->title,
            'og_description' => $this->og_description ?? $this->description,
            'twitter_card' => $this->twitter_card,
            'schema_type' => $this->schema_type,
            'schema_overrides' => $this->schema_overrides,
        ];
    }
}
