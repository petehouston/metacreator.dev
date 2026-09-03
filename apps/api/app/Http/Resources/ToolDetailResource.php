<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Seo\Services\ToolSeoDefaults;
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

    private ?bool $isFavorite = null;

    public function withAccess(AccessDecision $decision): self
    {
        $this->decision = $decision;

        return $this;
    }

    /** Null for a guest — "not saved" and "nobody to save it" are different states. */
    public function withFavorite(?bool $isFavorite): self
    {
        $this->isFavorite = $isFavorite;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $decision = $this->decision;
        $tier = $this->effectiveTier();

        $access = $decision === null ? null : [
            'allowed' => $decision->allowed,
            'reason' => $decision->reason?->value,
            'error_code' => $decision->errorCode,
            'message' => $decision->message,
            'required_tier' => $decision->requiredTier?->value,
        ];

        return [
            'id' => $this->public_id,
            // The registry key, not just an internal detail: the web app keys its
            // custom tool UIs off it, and unlike the slug it never changes when
            // an admin rewrites a URL.
            'key' => $this->key,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'version' => $this->version,
            // The *effective* tier, not the stored one: while billing is off a
            // premium tool is gated at `account`, and a card that still said "Pro"
            // would be advertising a plan the site does not sell.
            'tier' => [
                'value' => $tier->value,
                'label' => $tier->label(),
                'description' => $tier->description(),
            ],
            'platforms' => $this->platformList(),
            'category' => $this->whenLoaded('category', fn () => new ToolCategoryResource($this->category)),

            // Drives the generated form: the schema the server validates against,
            // with the per-field placeholders and starting values an admin has set
            // in the console layered on top. Only the presentation differs — what is
            // accepted is still exactly what the runner declared.
            'input_schema' => $this->presentedInputSchema(),

            // Block JSON, rendered by the same renderer as blog posts (ADR 0003).
            'instructions' => $this->instructions,
            'example' => $this->presentedExample(),
            'faq' => $this->faq,

            'access' => $this->when($access !== null, $access),

            'related' => ToolResource::collection($this->whenLoaded('related')),
            'successor' => $this->whenLoaded('successor', fn () => [
                'slug' => $this->successor?->slug,
                'name' => $this->successor?->name,
            ]),

            // Never null and never partially blank. Whatever an admin has stored
            // wins field by field; every field they have not filled in comes from
            // {@see ToolSeoDefaults}, so a tool nobody has tuned still ships a
            // complete title, description and share card (docs/16).
            'seo' => $this->resolvedSeo($request),

            'is_favorite' => $this->when($this->isFavorite !== null, fn (): bool => (bool) $this->isFavorite),

            'stats' => [
                'runs' => $this->run_count,
                'avg_duration_ms' => $this->avg_duration_ms,
                'success_rate' => $this->success_rate,
            ],
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }

    /**
     * Stored overrides layered on top of the generated defaults.
     *
     * Resolved here rather than in {@see SeoResource} because that resource also
     * feeds the admin editor, which must show what was actually typed — a form
     * pre-filled with a fallback turns it into a hard-coded override the first time
     * anybody presses Save.
     *
     * @return array<string, mixed>
     */
    private function resolvedSeo(Request $request): array
    {
        /** @var Tool $tool */
        $tool = $this->resource;

        $stored = $this->relationLoaded('seo') && $this->seo !== null
            ? (new SeoResource($this->seo))->toArray($request)
            : [];

        $resolved = app(ToolSeoDefaults::class)->merge($tool, $stored);

        // The image is not something defaults can invent: the frontend falls back
        // to the site-wide card, and this key has to exist for it to test.
        $resolved['og_image_url'] ??= null;
        $resolved['og_media_id'] ??= null;
        $resolved['schema_overrides'] ??= null;

        return $resolved;
    }
}
