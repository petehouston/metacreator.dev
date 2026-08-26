<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Tools\Models\ToolCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ToolCategory */
final class ToolCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'icon' => $this->icon,
            'accent_color' => $this->accent_color,
            'tool_count' => $this->whenCounted('tools'),
        ];
    }
}
