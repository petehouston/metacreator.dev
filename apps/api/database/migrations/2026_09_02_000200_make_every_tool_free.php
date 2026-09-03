<?php

declare(strict_types=1);

use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The whole catalog is free.
 *
 * {@see ToolCatalogSeeder} now says `free` for every tool, but the
 * seeder no longer overwrites a tier an admin has saved — which is the correct rule
 * and also means the file alone cannot move a row that was gated by hand. This does
 * it once, and releases the lock so the catalog file owns the tier again.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tools')->where('tier', '!=', 'free')->update(['tier' => 'free']);

        DB::table('tools')
            ->whereNotNull('locked_fields')
            ->orderBy('id')
            ->select('id', 'locked_fields')
            ->chunk(200, function ($rows): void {
                foreach ($rows as $row) {
                    $locked = json_decode((string) $row->locked_fields, true);

                    if (! is_array($locked) || ! in_array('tier', $locked, true)) {
                        continue;
                    }

                    DB::table('tools')->where('id', $row->id)->update([
                        'locked_fields' => json_encode(array_values(
                            array_diff($locked, ['tier'])
                        )),
                    ]);
                }
            });
    }

    /**
     * Nothing to undo: which tools were paid before this ran is not recorded
     * anywhere, and inventing a tier to roll back to would gate tools at random.
     */
    public function down(): void {}
};
