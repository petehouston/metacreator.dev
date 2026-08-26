<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Paginated collections in the shape documented in docs/05-api.md.
 *
 * Laravel's default paginator meta is flat and includes a pre-rendered `links`
 * array meant for Blade pagination views — noise for an API client, and a different
 * shape from the one our contract (and the generated TypeScript client) expects.
 * Every list endpoint returns this instead, so clients never branch on shape.
 *
 * @template TResource of JsonResource
 */
class ApiCollection extends ResourceCollection
{
    /** @param  class-string<TResource>  $collects */
    public function __construct(mixed $resource, string $collects)
    {
        $this->collects = $collects;

        parent::__construct($resource);
    }

    /**
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'page' => [
                    'current' => $paginated['current_page'] ?? 1,
                    'per_page' => $paginated['per_page'] ?? 0,
                    'total' => $paginated['total'] ?? 0,
                    'last_page' => $paginated['last_page'] ?? 1,
                ],
            ],
            'links' => [
                'first' => $paginated['first_page_url'] ?? null,
                'prev' => $paginated['prev_page_url'] ?? null,
                'next' => $paginated['next_page_url'] ?? null,
                'last' => $paginated['last_page_url'] ?? null,
            ],
        ];
    }

    /** @return array<int, mixed> */
    public function toArray(Request $request): array
    {
        return $this->collection?->all() ?? [];
    }

    /**
     * Non-paginated collections still get a `meta.page`, so a client can read the
     * same fields whether or not the endpoint happens to paginate.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        if ($this->resource instanceof AbstractPaginator) {
            return [];
        }

        $count = $this->collection?->count() ?? 0;

        return [
            'meta' => [
                'page' => ['current' => 1, 'per_page' => $count, 'total' => $count, 'last_page' => 1],
            ],
        ];
    }
}
