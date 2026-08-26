<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Blog\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tag */
final class TagResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'post_count' => $this->whenCounted('posts', default: $this->post_count),
        ];
    }
}
