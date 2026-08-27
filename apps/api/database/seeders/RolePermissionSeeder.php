<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\Actions\SyncRolesAndPermissions;
use Illuminate\Database\Seeder;

/**
 * Reference data: this runs in production, not just locally.
 *
 * The work lives in {@see SyncRolesAndPermissions} so that tests — which need the
 * same roles and have no console to write a summary to — can reach it without
 * going through a seeder.
 */
final class RolePermissionSeeder extends Seeder
{
    public function run(SyncRolesAndPermissions $sync): void
    {
        $result = $sync->handle();

        $this->command->info(
            "Synced {$result['permissions']} permissions across {$result['roles']} roles."
        );
    }
}
