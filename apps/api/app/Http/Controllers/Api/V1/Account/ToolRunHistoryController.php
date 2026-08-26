<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Tools\Models\ToolRun;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\ToolRunResource;
use Illuminate\Http\Request;

/**
 * Run history, windowed by the plan's `history_days` entitlement.
 *
 * The window is applied in the query rather than filtered afterwards, so a free user
 * paging deeply cannot walk past their retention limit.
 */
final class ToolRunHistoryController extends Controller
{
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
}
