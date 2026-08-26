<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Blog\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;

/**
 * Full article: the card fields plus the block document, SEO overrides and the
 * related posts the frontend renders under the article.
 *
 * @mixin Post
 */
final class PostDetailResource extends PostResource
{
    /** @var Collection<int, Post>|null */
    private ?Collection $related = null;

    /** @param  Collection<int, Post>  $related */
    public function withRelated(Collection $related): self
    {
        $this->related = $related;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'blocks' => $this->blockList(),
            'seo' => $this->seoPayload(),
            'related' => $this->related === null
                ? []
                : PostResource::collection($this->related)->resolve($request),
        ];
    }

    /**
     * SEO overrides only. Falling back to title/excerpt is the frontend's job — it
     * already owns the site-wide metadata template, and doing it in one place keeps
     * the two from disagreeing.
     *
     * @return array<string, mixed>
     */
    private function seoPayload(): array
    {
        $seo = $this->whenLoaded('seo') instanceof MissingValue
            ? null
            : $this->seo;

        return [
            'title' => $seo?->title,
            'description' => $seo?->description,
            'canonical_url' => $seo?->canonical_url,
            'robots' => $seo?->robots,
            'og_title' => $seo?->og_title,
            'og_description' => $seo?->og_description,
            'og_image_url' => $seo?->ogMedia?->url(),
            'twitter_card' => $seo?->twitter_card,
            'schema_type' => $seo?->schema_type,
        ];
    }
}
