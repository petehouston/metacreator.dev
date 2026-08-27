<?php

declare(strict_types=1);

namespace App\Domain\Tools\Actions;

use App\Domain\Tools\Models\Tool;
use Illuminate\Support\Facades\DB;

/**
 * Keeps `tool_platform` in step with `tools.platforms`.
 *
 * The same fact is stored twice on purpose: the JSON column is what the API
 * serialises, and the pivot table is what the catalog's platform filter joins
 * against — a `WHERE JSON_CONTAINS` cannot use an index. Two representations means
 * they can drift, so nothing writes one without this writing the other.
 */
final class SyncToolPlatforms
{
    /** @param list<string> $platforms */
    public function handle(Tool $tool, array $platforms): void
    {
        $platforms = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $platforms,
        ))));

        DB::transaction(function () use ($tool, $platforms): void {
            $tool->forceFill(['platforms' => $platforms])->save();

            DB::table('tool_platform')->where('tool_id', $tool->id)->delete();

            if ($platforms === []) {
                return;
            }

            DB::table('tool_platform')->insert(array_map(
                static fn (string $platform): array => ['tool_id' => $tool->id, 'platform' => $platform],
                $platforms,
            ));
        });
    }
}
