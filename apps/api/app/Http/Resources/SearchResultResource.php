<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Search\Data\SearchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One search hit.
 *
 * `url` is a site-relative path, not an absolute URL: the frontend renders these
 * through `next/link`, and an absolute address would turn every in-app navigation
 * into a full page load. `score` is deliberately absent — it is a ranking detail,
 * and publishing it invites a client to re-sort on a number whose scale is not part
 * of any contract.
 *
 * @mixin SearchResult
 */
final class SearchResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var SearchResult $result */
        $result = $this->resource;

        return [
            'id' => $result->id,
            'type' => $result->type->value,
            'type_label' => $result->type->label(),
            'title' => $result->title,
            'url' => $result->url,
            'summary' => $result->summary,
            // Null is a normal answer, not a failure: most static pages have no
            // image of their own, and the frontend has a default to fall back on.
            'image' => $result->image,
            'context' => $result->context,
        ];
    }
}
