<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Tools\Enums\RunStatus;
use App\Domain\Tools\Models\ToolRun;
use App\Domain\Tools\Services\ArtifactStore;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\ToolRunResource;
use Illuminate\Http\Request;

/**
 * Run history, windowed by the plan's `history_days` entitlement.
 *
 * The window is applied in the query rather than filtered afterwards, so a free user
 * paging deeply cannot walk past their retention limit.
 *
 * History is an authenticated surface and only an authenticated surface: an
 * anonymous run stores nothing but a hash, so there is nothing to show and nobody
 * to show it to.
 */
final class ToolRunHistoryController extends Controller
{
    /**
     * Every column except the payloads.
     *
     * A page of twenty runs must not carry twenty stored results — they are only
     * ever read one at a time, on the detail view below.
     *
     * @var list<string>
     */
    private const LIST_COLUMNS = [
        'id', 'ulid', 'tool_id', 'tool_version', 'user_id', 'status', 'access_reason',
        'result_ref', 'result_view', 'duration_ms', 'cache_hit', 'error_code',
        'error_message', 'created_at',
    ];

    /** @return ApiCollection<ToolRunResource> */
    public function index(Request $request, EntitlementService $entitlements): ApiCollection
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $request->validate([
            'filter.tool' => ['sometimes', 'string', 'max:120'],
            'filter.status' => ['sometimes', 'string', 'max:20'],
        ]);

        $historyDays = $entitlements->limitsFor($user)['history_days'] ?? null;

        $runs = ToolRun::query()
            ->select(self::LIST_COLUMNS)
            // Whether there is something to open, without reading what it is.
            ->selectRaw('(result_payload IS NOT NULL OR result_ref IS NOT NULL) as has_stored_result')
            ->with('tool:id,slug,name,version')
            ->where('user_id', $user->id)
            ->when($historyDays !== null, fn ($query) => $query->where('created_at', '>=', now()->subDays((int) $historyDays)))
            ->when(
                $request->input('filter.tool'),
                fn ($query, string $slug) => $query->whereHas('tool', fn ($t) => $t->where('slug', $slug)),
            )
            ->when(
                $request->input('filter.status'),
                fn ($query, string $status) => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate(perPage: min((int) $request->integer('per_page', 20), 100));

        return new ApiCollection($runs, ToolRunResource::class);
    }

    /**
     * One run, with the input it was given and the result it produced.
     *
     * Scoped to the caller's own runs in the query rather than checked afterwards:
     * a 404 for somebody else's ULID is the right answer, and it is also the only
     * answer that does not confirm the run exists.
     *
     * The retention window applies here too — a run the list will not show is not a
     * run the detail view should serve.
     */
    public function show(
        Request $request,
        string $ulid,
        EntitlementService $entitlements,
        ArtifactStore $artifacts,
    ): ToolRunResource {
        $user = $request->user();
        abort_if($user === null, 401);

        $historyDays = $entitlements->limitsFor($user)['history_days'] ?? null;

        $run = ToolRun::query()
            ->with('tool:id,slug,name,version')
            ->where('user_id', $user->id)
            ->where('ulid', strtoupper($this->normaliseUlid($ulid)))
            ->when($historyDays !== null, fn ($query) => $query->where('created_at', '>=', now()->subDays((int) $historyDays)))
            ->firstOrFail();

        // A result too large for the row lives in object storage, and its artifact
        // URLs are re-signed on the way out — the ones stored with it have expired.
        if ($run->status === RunStatus::Succeeded && $run->result_payload === null && $run->result_ref !== null) {
            $run->setAttribute('stored_result', $artifacts->retrieve($run));
        }

        return (new ToolRunResource($run))->detailed();
    }

    /**
     * Run ids are handed out prefixed (`run_01J…`), which is what a user copies out
     * of the address bar. Accepting either form means a pasted id works.
     */
    private function normaliseUlid(string $id): string
    {
        return str_starts_with($id, 'run_') ? substr($id, 4) : $id;
    }
}
