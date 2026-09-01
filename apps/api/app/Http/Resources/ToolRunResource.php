<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Tools\Models\ToolRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One run, as its owner sees it.
 *
 * Two shapes from one class: a list row leaves the payloads out (a page of twenty
 * results is megabytes nobody scrolled to), and a detail view includes them. The
 * caller chooses with {@see self::detailed()} rather than the resource guessing
 * from which columns happen to be loaded — guessing is how a list endpoint quietly
 * starts shipping every result it has.
 *
 * @mixin ToolRun
 */
final class ToolRunResource extends JsonResource
{
    private bool $detailed = false;

    /** Include the stored input and result. Only ever for the run's own owner. */
    public function detailed(bool $detailed = true): self
    {
        $this->detailed = $detailed;

        return $this;
    }

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

            // The in-memory result for a synchronous run that just happened, the
            // payload kept for its owner, or the externalised copy for a completed
            // asynchronous one — in that order of freshness.
            'result' => $this->result?->toArray()
                ?? $this->getAttribute('stored_result')
                ?? ($this->detailed ? $this->result_payload : null),

            // What the run was given. Present only on the detail view, and only
            // when the run has an owner — an anonymous run never stores one.
            'input' => $this->when($this->detailed, fn () => $this->input_payload),

            // So the UI can distinguish "this run kept nothing" from "this run
            // produced nothing", which read identically as an empty panel.
            // The list query computes this in SQL so it can leave the payload
            // columns unselected; the detail query has them and answers directly.
            'has_stored_result' => (bool) ($this->getAttribute('has_stored_result')
                ?? ($this->result_payload !== null || $this->result_ref !== null)),

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
