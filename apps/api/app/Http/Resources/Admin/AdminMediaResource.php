<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Media\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Media */
final class AdminMediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            // The numeric key, for the one thing a public id cannot do: fill
            // `posts.featured_media_id` and `seo.og_media_id`, both of which are
            // integer foreign keys. Same reason AdminTaxonomyResource carries one.
            'numeric_id' => (int) $this->getKey(),
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'kind' => str_contains((string) $this->mime_type, '/')
                ? explode('/', (string) $this->mime_type)[0]
                : 'file',
            'size' => (int) $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'title' => $this->title,
            'is_decorative' => (bool) $this->is_decorative,
            'usage_count' => (int) $this->usage_count,
            'url' => $this->url(),
            'credit' => $this->credit,
            'uploaded_by' => $this->whenLoaded('uploader', fn (): ?array => $this->uploader === null ? null : [
                'display_name' => $this->uploader->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
