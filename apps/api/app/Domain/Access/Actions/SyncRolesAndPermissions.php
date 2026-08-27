<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the database in line with {@see PermissionCatalog}.
 *
 * Idempotent, and destructive in one direction only: permissions that were dropped
 * from the catalog are deleted, because a permission nothing checks but a role
 * still grants is worse than no permission at all. Roles an admin created by hand
 * are left alone — only the seeded ones are re-synced.
 */
final class SyncRolesAndPermissions
{
    /** @return array{permissions: int, roles: int} */
    public function handle(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $declared = PermissionCatalog::all();

        foreach ($declared as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Permission::query()->whereNotIn('name', $declared)->delete();

        foreach (array_keys(PermissionCatalog::ROLES) as $roleName) {
            Role::findOrCreate($roleName, 'web')
                ->syncPermissions(PermissionCatalog::permissionsFor($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['permissions' => count($declared), 'roles' => count(PermissionCatalog::ROLES)];
    }
}
