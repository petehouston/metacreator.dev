<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Seo\Models\SeoMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The stored overrides, exactly as stored.
 *
 * No fallbacks are applied here on purpose. The frontend owns the site-wide
 * metadata template and already falls back to the entity's own title and excerpt;
 * resolving `og_title` to the meta title *here* as well would mean an admin form
 * loading this payload shows a value nobody typed, and saving it back turns a
 * fallback into a hard-coded override.
 *
 * @mixin SeoMeta
 */
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
            'focus_keyword' => $this->focus_keyword,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_media_id' => $this->og_media_id,
            // Resolved to a URL as well as an id: a crawler needs the address, and
            // the admin form needs the key it saves back.
            'og_image_url' => $this->ogMedia?->url(),
            'twitter_card' => $this->twitter_card,
            'schema_type' => $this->schema_type,
            'schema_overrides' => $this->schema_overrides,
        ];
    }
}
