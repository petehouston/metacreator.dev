<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Tools\Models\ToolRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ToolRun */
final class ToolRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'tool' => $this->whenLoaded('tool', fn () => [
                'slug' => $this->tool->slug,
                'name' => $this->tool->name,
                'version' => $this->tool->version,
            ]),

            // The in-memory result for a synchronous run, or the stored payload for a
            // completed asynchronous one.
            'result' => $this->result?->toArray()
                ?? $this->getAttribute('stored_result'),

            'error' => $this->when($this->error_code !== null, fn () => [
                'code' => $this->error_code,
                'message' => $this->error_message,
            ]),

            'meta' => [
                'cache_hit' => (bool) $this->cache_hit,
                'duration_ms' => $this->duration_ms,
                'access_reason' => $this->access_reason->value,
            ],
            'created_at' => $this->created_at?->toAtomString() ?? now()->toAtomString(),
        ];
    }
}
