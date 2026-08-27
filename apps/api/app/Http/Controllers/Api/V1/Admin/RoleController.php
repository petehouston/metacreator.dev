<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\PermissionCatalog;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Access\Services\RoleDirectory;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and the granular permissions behind them.
 *
 * This is the screen that makes "some editors can view only, some can edit but not
 * delete" a composition problem instead of a deploy (docs/06). Every write here is
 * privilege escalation if it goes wrong, so the guardrails are explicit rather than
 * implied by the permission alone.
 */
final class RoleController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RoleDirectory $directory,
    ) {}

    /** @return ApiCollection<RoleResource> */
    public function index(): ApiCollection
    {
        return new ApiCollection($this->directory->all(), RoleResource::class);
    }

    /** The permission catalog, grouped the way the role editor renders it. */
    public function permissions(): JsonResource
    {
        return new JsonResource([
            'resources' => collect(PermissionCatalog::RESOURCES)
                ->map(fn (array $actions, string $resource): array => [
                    'resource' => $resource,
                    'label' => str($resource)->replace('_', ' ')->replace('.', ' → ')->title()->toString(),
                    'permissions' => array_map(
                        static fn (string $action): array => [
                            'name' => "{$resource}.{$action}",
                            'action' => $action,
                        ],
                        $actions,
                    ),
                ])
                ->values()
                ->all(),
            'groups' => PermissionCatalog::groups(),
            // Surfaced so the UI can explain *why* a checkbox is refused rather than
            // silently dropping it on save.
            'admin_exclusions' => PermissionCatalog::ADMIN_EXCLUSIONS,
        ]);
    }

    public function store(SaveRoleRequest $request): RoleResource
    {
        /** @var Role $role Spatie's factory is typed to its contract, not its model. */
        $role = Role::findOrCreate((string) $request->string('name'), 'web');
        $role->syncPermissions($request->permissions());

        $this->audit->record(
            event: 'created',
            subject: $role,
            causer: $request->user(),
            after: ['name' => $role->name, 'permissions' => $request->permissions()],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return new RoleResource($role->load('permissions:id,name'));
    }

    public function update(SaveRoleRequest $request, Role $role): RoleResource
    {
        abort_if(
            $role->name === 'super-admin',
            422,
            'Super admin bypasses permission checks entirely; its permission set is not editable.'
        );

        $before = $role->permissions->pluck('name')->all();

        $role->syncPermissions($request->permissions());

        $this->audit->record(
            event: 'updated',
            subject: $role,
            causer: $request->user(),
            before: ['permissions' => $before],
            after: ['permissions' => $request->permissions()],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return new RoleResource($role->fresh(['permissions']));
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_if(
            array_key_exists($role->name, PermissionCatalog::ROLES),
            422,
            "The [{$role->name}] role is part of the platform and cannot be deleted. Edit its permissions instead."
        );

        abort_if(
            $role->users()->exists(),
            422,
            'This role is still assigned. Move those people to another role first.'
        );

        $this->audit->record('deleted', $role, $request->user(), before: ['name' => $role->name]);

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(status: 204);
    }

    /**
     * Assign roles to a person.
     *
     * The last super admin cannot be demoted — including by themselves. Locking
     * everyone out of the permission system is not a recoverable mistake through
     * any screen this application has.
     */
    public function assign(Request $request, User $user): JsonResource
    {
        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $before = $user->getRoleNames()->all();
        $after = array_values(array_unique($validated['roles']));

        if (in_array('super-admin', $before, true) && ! in_array('super-admin', $after, true)) {
            abort_if(
                User::role('super-admin')->count() <= 1,
                422,
                'This is the only super admin. Promote someone else before removing the role.'
            );
        }

        $user->syncRoles($after);

        $this->audit->record(
            event: 'roles_changed',
            subject: $user,
            causer: $request->user(),
            before: ['roles' => $before],
            after: ['roles' => $after],
        );

        return new JsonResource(['roles' => $user->refresh()->getRoleNames()->values()->all()]);
    }
}
