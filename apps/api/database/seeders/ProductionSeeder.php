<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything the application needs to function, in a form that is safe to re-run on
 * every deploy. Each of these seeders is idempotent.
 */
final class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            PlanSeeder::class,
            ToolCategorySeeder::class,
            ToolCatalogSeeder::class,
            // After the catalog: it reads tool names back out of the table the
            // seeder above writes, so a tool and its changelog entry can ship in
            // the same deploy.
            ToolReleaseChangelogSeeder::class,
        ]);
    }
}
