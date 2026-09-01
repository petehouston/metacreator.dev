<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Changelog\Actions\SaveReleaseAction;
use App\Domain\Changelog\Enums\ChangeType;
use App\Domain\Changelog\Enums\ReleaseStatus;
use App\Domain\Changelog\Models\ChangelogRelease;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveChangelogReleaseRequest;
use App\Http\Resources\Admin\AdminChangelogResource;
use App\Http\Resources\ApiCollection;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Managing the changelog.
 *
 * The write path is {@see SaveReleaseAction}; this controller resolves the row,
 * hands the validated attributes over and records the audit entry.
 */
final class ChangelogController extends Controller
{
    public function __construct(
        private readonly SaveReleaseAction $save,
        private readonly AuditLogger $audit,
    ) {}

    /** @return ApiCollection<AdminChangelogResource> */
    public function index(Request $request): ApiCollection
    {
        $releases = ChangelogRelease::query()
            ->withCount('items')
            ->with('author:id,name,display_name')
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->string('q')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            // Undated drafts sort to the top rather than the bottom: they are the
            // rows that need attention, and `ORDER BY released_at DESC` would bury
            // every one of them below a decade of shipped releases.
            ->orderByRaw('released_at IS NULL DESC')
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->paginate(perPage: min(100, $request->integer('per_page', 25)))
            ->withQueryString();

        return (new ApiCollection($releases, AdminChangelogResource::class))->additional([
            'meta' => [
                // The status tab counts, unfiltered — a tab row that only counted
                // the current filter would show "Draft 0" while sitting on Published.
                'counts' => $this->counts(),
                'types' => ChangeType::catalog(),
            ],
        ]);
    }

    public function show(ChangelogRelease $release): AdminChangelogResource
    {
        return new AdminChangelogResource(
            $release->load(['items', 'author:id,name,display_name,avatar_path'])
        );
    }

    public function store(SaveChangelogReleaseRequest $request): AdminChangelogResource
    {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $release = $this->save->handle(new ChangelogRelease, $request->validated(), $actor);

        $this->audit->record('created', $release, $request->user(), after: [
            'title' => $release->title,
            'version' => $release->version,
            'status' => $release->status->value,
        ]);

        return new AdminChangelogResource($release);
    }

    public function update(SaveChangelogReleaseRequest $request, ChangelogRelease $release): AdminChangelogResource
    {
        $before = [
            'title' => $release->title,
            'version' => $release->version,
            'status' => $release->status->value,
            'released_at' => $release->released_at?->toIso8601String(),
            'items' => $release->items()->count(),
        ];

        $actor = $request->user();
        abort_if($actor === null, 401);

        $release = $this->save->handle($release, $request->validated(), $actor);

        $this->audit->record('updated', $release, $request->user(), before: $before, after: [
            'title' => $release->title,
            'version' => $release->version,
            'status' => $release->status->value,
            'released_at' => $release->released_at?->toIso8601String(),
            'items' => $release->items->count(),
        ]);

        return new AdminChangelogResource($release);
    }

    /**
     * Publish now.
     *
     * Its own endpoint rather than a PATCH with two fields, for the same reason
     * `users/{user}/suspend` is: it is the action that carries consequence, it needs
     * its own permission (`changelog.publish`), and it must be greppable in the
     * audit log without inspecting a diff to work out what a PATCH did.
     */
    public function publish(Request $request, ChangelogRelease $release): AdminChangelogResource
    {
        $before = ['status' => $release->status->value, 'released_at' => $release->released_at?->toIso8601String()];

        $release->status = ReleaseStatus::Published;

        // A release published by hand is live now, whatever date it was carrying.
        // Keeping a future date here would report success and publish nothing.
        if ($release->released_at === null || $release->released_at->isFuture()) {
            $release->released_at = CarbonImmutable::now();
        }

        $release->save();

        $this->audit->record('published', $release, $request->user(), before: $before, after: [
            'status' => $release->status->value,
            'released_at' => $release->released_at->toIso8601String(),
        ]);

        return new AdminChangelogResource($release->load('items'));
    }

    public function destroy(Request $request, ChangelogRelease $release): JsonResponse
    {
        // A hard delete: the items cascade, and there is no soft-delete column to
        // recover from. Deliberate — a changelog entry has no dependants and no
        // authorship history worth a 30-day grace period, and the audit record
        // below keeps what it said.
        $this->audit->record('deleted', $release, $request->user(), before: [
            'title' => $release->title,
            'version' => $release->version,
            'status' => $release->status->value,
            'items' => $release->items()->count(),
        ]);

        $release->delete();

        return response()->json(status: 204);
    }

    /**
     * How many releases sit in each status, plus how many are live right now.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        $byStatus = ChangelogRelease::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $counts = ['all' => array_sum($byStatus)];

        foreach (ReleaseStatus::cases() as $status) {
            $counts[$status->value] = (int) ($byStatus[$status->value] ?? 0);
        }

        $counts['live'] = ChangelogRelease::query()->public()->count();

        return $counts;
    }
}
