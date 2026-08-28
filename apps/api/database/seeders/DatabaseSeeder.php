<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data first, then demo data only outside production.
 *
 * The split matters: `ProductionSeeder` is safe to run on a live deploy, `DemoSeeder`
 * would be a data-integrity incident there.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductionSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
            // After DemoSeeder: posts are attributed to the editor account it creates.
            $this->call(BlogDemoSeeder::class);
            // Both depend on the accounts DemoSeeder creates.
            $this->call(CommerceDemoSeeder::class);
            $this->call(SupportDemoSeeder::class);
        }
    }
}
