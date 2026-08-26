<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reference data: this runs in production, not just locally.
 *
 * Idempotent, and it *removes* permissions that were dropped from the catalog, so
 * the database can never drift from {@see PermissionCatalog}.
 */
final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $declared = PermissionCatalog::all();

        foreach ($declared as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Anything no longer declared is stale — drop it rather than leaving a
        // permission nothing checks but a role still grants.
        Permission::query()->whereNotIn('name', $declared)->delete();

        foreach (array_keys(PermissionCatalog::ROLES) as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(PermissionCatalog::permissionsFor($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info(sprintf(
            'Synced %d permissions across %d roles.',
            count($declared),
            count(PermissionCatalog::ROLES),
        ));
    }
}
