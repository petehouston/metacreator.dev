<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Tools\Models\ToolGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ToolGrant */
final class ToolGrantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'is_active' => $this->isActive(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->public_id,
                'email' => $this->user->email,
                'display_name' => $this->user->displayName(),
                'initials' => $this->user->initials(),
            ]),
            'tool' => $this->whenLoaded('tool', fn (): ?array => $this->tool === null ? null : [
                'slug' => $this->tool->slug,
                'name' => $this->tool->name,
                'tier' => $this->tool->tier->value,
            ]),
            'granted_by' => $this->whenLoaded('grantedBy', fn (): ?string => $this->grantedBy?->displayName()),
        ];
    }
}
