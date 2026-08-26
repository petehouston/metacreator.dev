<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Tools\Data\AccessDecision;
use App\Domain\Tools\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything a tool page needs in one request: the form schema, the instructions,
 * the worked example, the SEO payload and the access decision.
 *
 * One round trip matters here — this is the page that has to render fast enough to
 * rank.
 *
 * @mixin Tool
 */
final class ToolDetailResource extends JsonResource
{
    private ?AccessDecision $decision = null;

    public function withAccess(AccessDecision $decision): self
    {
        $this->decision = $decision;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $decision = $this->decision;

        $access = $decision === null ? null : [
            'allowed' => $decision->allowed,
            'reason' => $decision->reason?->value,
            'error_code' => $decision->errorCode,
            'message' => $decision->message,
            'required_tier' => $decision->requiredTier?->value,
        ];

        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'version' => $this->version,
            'tier' => [
                'value' => $this->tier->value,
                'label' => $this->tier->label(),
                'description' => $this->tier->description(),
            ],
            'platforms' => $this->platformList(),
            'category' => $this->whenLoaded('category', fn () => new ToolCategoryResource($this->category)),

            // Drives the generated form; the same schema the server validates against.
            'input_schema' => $this->input_schema,

            // Block JSON, rendered by the same renderer as blog posts (ADR 0003).
            'instructions' => $this->instructions,
            'example' => $this->example,
            'faq' => $this->faq,

            'access' => $this->when($access !== null, $access),

            'related' => ToolResource::collection($this->whenLoaded('related')),
            'successor' => $this->whenLoaded('successor', fn () => [
                'slug' => $this->successor?->slug,
                'name' => $this->successor?->name,
            ]),

            'seo' => $this->whenLoaded('seo', fn () => new SeoResource($this->seo)),

            'stats' => [
                'runs' => $this->run_count,
                'avg_duration_ms' => $this->avg_duration_ms,
                'success_rate' => $this->success_rate,
            ],
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
