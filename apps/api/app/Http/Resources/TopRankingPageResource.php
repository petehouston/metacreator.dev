<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\TopRanking\Models\TopRankingPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ranking page as the public site renders it.
 *
 * Carries no sync status and no source-table index — those are operational facts
 * about how the page is maintained, not about what it says. What the reader does
 * get is `source_url` and `synced_at`, because a page of numbers with no
 * attribution and no date is a page asking to be distrusted.
 *
 * @mixin TopRankingPage
 */
final class TopRankingPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'platform' => $this->platform->value,
            'platform_label' => $this->platform->label(),
            'platform_accent' => $this->platform->accent(),
            'noun' => $this->platform->noun(),
            'metric_label' => $this->metric_label,
            'metric_unit' => $this->metric_unit,
            'secondary_metric_label' => $this->secondary_metric_label,
            'secondary_metric_unit' => $this->secondary_metric_unit,
            'intro' => $this->intro,
            'seo' => $this->resolvedSeo($request),
            'source_page' => $this->source_page,
            'source_url' => $this->sourceUrl(),
            'synced_at' => $this->synced_at?->toIso8601String(),
            'entries_count' => (int) ($this->entries_count
                ?? ($this->relationLoaded('entries') ? $this->entries->count() : 0)),
            'entries' => TopRankingEntryResource::collection($this->whenLoaded('entries')),
        ];
    }

    /**
     * The stored overrides, with this page's own words filled in behind them.
     *
     * Resolved here rather than in {@see SeoResource} — which also feeds the admin
     * form, and must show what was actually typed, or the first Save turns a
     * fallback into a hard-coded override. The same split the tool page makes.
     *
     * @return array<string, mixed>
     */
    private function resolvedSeo(Request $request): array
    {
        $stored = $this->relationLoaded('seo') && $this->seo !== null
            ? (new SeoResource($this->seo))->toArray($request)
            : [];

        $blank = static fn (?string $value): bool => $value === null || trim($value) === '';

        $title = $blank($stored['title'] ?? null) ? $this->title : $stored['title'];
        $description = $blank($stored['description'] ?? null) ? $this->intro : $stored['description'];

        return [
            ...$stored,
            'title' => $title,
            'description' => $description,
            'robots' => $stored['robots'] ?? 'index,follow',
            'twitter_card' => $stored['twitter_card'] ?? 'summary_large_image',
            // The keys the frontend tests for have to exist even when nothing is
            // stored, or `seo?.og_image_url` is undefined rather than null and the
            // site-wide card is never reached.
            'og_title' => $stored['og_title'] ?? null,
            'og_description' => $stored['og_description'] ?? null,
            'og_image_url' => $stored['og_image_url'] ?? null,
            'canonical_url' => $stored['canonical_url'] ?? null,
            'focus_keyword' => $stored['focus_keyword'] ?? null,
        ];
    }
}
