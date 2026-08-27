<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Notifications\Notifier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolGrant;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ToolGrantResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Comping a specific tool to a specific person.
 *
 * The requirement is "admin can explicitly grant a user access to a tool"; the
 * discipline around it is that a grant is *visible* — it is listed, attributed,
 * expirable and audited — because a comp that nobody can see is revenue leaking
 * through a support conversation.
 */
final class ToolGrantController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    /** @return ApiCollection<ToolGrantResource> */
    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filter.state' => ['sometimes', 'nullable', 'in:active,expired'],
        ]);

        $grants = ToolGrant::query()
            ->with(['user:id,ulid,email,display_name,name', 'tool:id,slug,name,tier', 'grantedBy:id,ulid,display_name,name,email'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas(
                'user',
                fn ($u) => $u->where('email', 'like', '%'.$request->string('q').'%')
            ))
            ->when($request->input('filter.state') === 'active', fn ($q) => $q->active())
            ->when(
                $request->input('filter.state') === 'expired',
                fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', now())
            )
            ->latest('id')
            ->paginate(perPage: min(100, $request->integer('per_page', 25)))
            ->withQueryString();

        return new ApiCollection($grants, ToolGrantResource::class);
    }

    public function store(Request $request): ToolGrantResource
    {
        $validated = $request->validate([
            'user' => ['required', 'string', 'max:60'],
            'tool' => ['required', 'string', 'exists:tools,slug'],
            'reason' => ['required', 'string', 'max:255'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        $user = User::findByPublicId($validated['user'])
            ?? User::query()->where('email', $validated['user'])->firstOrFail();

        $tool = Tool::query()->where('slug', $validated['tool'])->firstOrFail();

        $grant = ToolGrant::query()->updateOrCreate(
            ['user_id' => $user->id, 'tool_id' => $tool->id],
            [
                'granted_by' => $request->user()?->id,
                'reason' => $validated['reason'],
                'expires_at' => $validated['expires_at'] ?? null,
            ],
        );

        $this->forgetEntitlements($user);

        $this->audit->record(
            event: 'granted',
            subject: $grant,
            causer: $request->user(),
            after: [
                'user' => $user->email,
                'tool' => $tool->slug,
                'reason' => $validated['reason'],
                'expires_at' => $validated['expires_at'] ?? null,
            ],
        );

        // Telling someone they have been given something is the whole point of
        // giving it to them.
        $this->notifier->send($user, 'tool.access_granted', [
            'tool' => $tool->name,
            'expiry_note' => $grant->expires_at === null
                ? ''
                : ' until '.$grant->expires_at->toFormattedDayDateString(),
        ], actionUrl: config('app.frontend_url')."/tools/{$tool->slug}");

        return new ToolGrantResource($grant->load(['user', 'tool', 'grantedBy']));
    }

    public function destroy(Request $request, ToolGrant $grant): JsonResponse
    {
        $grant->load(['user', 'tool']);

        $this->audit->record(
            event: 'revoked',
            subject: $grant,
            causer: $request->user(),
            before: ['user' => $grant->user?->email, 'tool' => $grant->tool?->slug],
        );

        $user = $grant->user;
        $grant->delete();

        if ($user !== null) {
            $this->forgetEntitlements($user);
        }

        return response()->json(status: 204);
    }

    /**
     * Entitlements are cached for a minute (see EntitlementService). A comp that
     * takes sixty seconds to appear is a comp the support agent reports as broken.
     */
    private function forgetEntitlements(User $user): void
    {
        Cache::forget("entitlement:paid:{$user->id}");
    }
}
