<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Blog\Models\PostCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PostCategory */
final class PostCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'accent_color' => $this->accent_color,
            'post_count' => $this->whenCounted('posts', default: $this->post_count),
        ];
    }
}
