<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Changelog;

use App\Domain\Changelog\Enums\ChangeType;
use App\Domain\Changelog\Models\ChangelogRelease;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\ChangelogReleaseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The public changelog. Thin, like every other public controller here: visibility
 * lives in the model's `public` scope and presentation in the resources.
 */
final class ChangelogController extends Controller
{
    /**
     * Twenty releases a page. A changelog is read by scrolling, not by paging, so
     * the page is sized to make the second one rare rather than to fill a grid.
     */
    private const PER_PAGE = 20;

    /** @return ApiCollection<ChangelogReleaseResource> */
    public function index(Request $request): ApiCollection
    {
        $releases = ChangelogRelease::query()
            ->public()
            ->with('items')
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->string('q')))
            // "Show me only the fixes" filters the releases *and* the lines inside
            // them — a release kept for one matching item must not then render the
            // nine that did not match.
            ->when($request->filled('filter.type'), fn ($q) => $q
                ->whereHas('items', fn (Builder $items) => $items
                    ->where('type', $request->string('filter.type')))
                ->with(['items' => fn ($items) => $items
                    ->where('type', $request->string('filter.type'))]))
            ->when($request->filled('filter.year'), fn ($q) => $q
                ->whereYear('released_at', $request->integer('filter.year')))
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->paginate(perPage: min(50, $request->integer('per_page', self::PER_PAGE)))
            ->withQueryString();

        return new ApiCollection($releases, ChangelogReleaseResource::class);
    }

    public function show(string $slug): ChangelogReleaseResource
    {
        $release = ChangelogRelease::query()
            ->with('items')
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $release->isPublic()) {
            throw new HttpException(404, 'This release note is not available.');
        }

        return new ChangelogReleaseResource($release);
    }

    /**
     * The filters the page can offer: every change type, and the years that
     * actually have releases in them.
     *
     * Served rather than hard-coded in the frontend so a year chip appears the day
     * the first release of that year publishes, without a deploy.
     */
    public function meta(): JsonResponse
    {
        $years = ChangelogRelease::query()
            ->public()
            ->selectRaw('YEAR(released_at) as year, COUNT(*) as total')
            ->groupBy('year')
            ->orderByDesc('year')
            ->get()
            // `getAttribute`, not `->year`: these are aggregate columns that exist only
            // on this result set, and phpstan is right that the model has no such
            // properties.
            ->map(fn (ChangelogRelease $row) => [
                'year' => (int) $row->getAttribute('year'),
                'total' => (int) $row->getAttribute('total'),
            ])
            ->all();

        return response()->json([
            'data' => [
                'types' => ChangeType::catalog(),
                'years' => $years,
            ],
        ]);
    }
}
