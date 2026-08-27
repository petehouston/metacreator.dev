<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User management.
 *
 * Reads are broad, writes are narrow: staff can find anyone, but the only things
 * this endpoint will change are the fields a support conversation legitimately
 * produces. Email stays immutable (docs/06) and roles move through their own route,
 * which carries the `roles.manage` permission an admin deliberately does not hold.
 */
final class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return ApiCollection<AdminUserResource> */
    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filter.status' => ['sometimes', 'nullable', 'in:active,suspended,pending'],
            'filter.role' => ['sometimes', 'nullable', 'string', 'max:60'],
            'filter.plan' => ['sometimes', 'nullable', 'in:free,paid'],
            'sort' => ['sometimes', 'nullable', 'in:created_at,-created_at,last_seen_at,-last_seen_at,email,-email'],
        ]);

        $sort = (string) ($request->query('sort') ?: '-created_at');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $users = User::query()
            ->withTrashed()
            ->with('roles:id,name')
            ->withCount('toolRuns', 'tickets')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = (string) $request->string('q');

                $query->where(fn ($q) => $q
                    ->where('email', 'like', "%{$term}%")
                    ->orWhere('display_name', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    // Lets staff paste a public id straight from a support email.
                    ->orWhere('ulid', str_contains($term, '_') ? strtoupper(explode('_', $term, 2)[1]) : strtoupper($term)));
            })
            ->when(
                $request->filled('filter.status'),
                fn ($query) => $query->where('status', $request->string('filter.status'))
            )
            ->when(
                $request->filled('filter.role'),
                fn ($query) => $query->whereHas('roles', fn ($r) => $r->where('name', $request->string('filter.role')))
            )
            ->when($request->input('filter.plan') === 'paid', fn ($query) => $query->whereHas(
                'subscriptions',
                fn ($s) => $s->whereIn('stripe_status', ['active', 'trialing'])
            ))
            ->when($request->input('filter.plan') === 'free', fn ($query) => $query->whereDoesntHave(
                'subscriptions',
                fn ($s) => $s->whereIn('stripe_status', ['active', 'trialing'])
            ))
            ->orderBy($column, $descending ? 'desc' : 'asc')
            ->paginate(perPage: min(100, $request->integer('per_page', 25)))
            ->withQueryString();

        return new ApiCollection($users, AdminUserResource::class);
    }

    public function show(User $user): AdminUserResource
    {
        $user->load([
            'roles:id,name',
            'subscriptions.plan',
            'toolGrants.tool:id,slug,name',
            'invoices' => fn ($q) => $q->latest('issued_at')->limit(12),
        ])->loadCount('toolRuns', 'tickets');

        return new AdminUserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): AdminUserResource
    {
        $before = $user->only(['display_name', 'status', 'locale', 'timezone', 'marketing_opt_in']);

        $user->fill($request->validated())->save();

        $this->audit->record(
            event: 'updated',
            subject: $user,
            causer: $request->user(),
            before: $before,
            after: $user->only(array_keys($before)),
        );

        return new AdminUserResource($user->fresh(['roles']));
    }

    /**
     * Suspension, not deletion.
     *
     * A suspended user keeps their history and their invoices — which is what makes
     * the action reversible, and what an accountant needs six months later.
     */
    public function suspend(Request $request, User $user): AdminUserResource
    {
        $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:255']]);

        abort_if(
            $user->hasRole('super-admin'),
            422,
            'A super admin cannot be suspended. Remove the role first.'
        );

        $before = ['status' => $user->status];
        $user->status = $user->status === 'suspended' ? 'active' : 'suspended';
        $user->save();

        $this->audit->record(
            event: $user->status === 'suspended' ? 'suspended' : 'reinstated',
            subject: $user,
            causer: $request->user(),
            before: $before,
            after: ['status' => $user->status, 'reason' => $request->string('reason')->toString()],
        );

        return new AdminUserResource($user->fresh(['roles']));
    }

    /**
     * Soft delete. The row survives so invoices keep a payer and tool runs keep an
     * owner; only `users.delete` — which an admin does not hold — can reach it.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()?->id, 422, 'You cannot delete your own account here.');
        abort_if($user->hasRole('super-admin'), 422, 'A super admin cannot be deleted.');

        $this->audit->record(
            event: 'deleted',
            subject: $user,
            causer: $request->user(),
            before: ['email' => $user->email, 'status' => $user->status],
            after: ['status' => 'deleted'],
        );

        $user->delete();

        return response()->json(status: 204);
    }
}
