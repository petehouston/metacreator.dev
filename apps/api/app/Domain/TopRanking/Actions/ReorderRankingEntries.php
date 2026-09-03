<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Actions;

use App\Domain\TopRanking\Models\TopRankingPage;
use Illuminate\Support\Facades\DB;

/**
 * Applies an order the admin arranged.
 *
 * Takes the full list of ids in their new order rather than a "move row 4 to
 * position 2" instruction. Two reasons: a drag that is expressed as a final
 * arrangement cannot half-apply, and two editors reordering the same page at once
 * end with one of the two arrangements rather than an interleaving of both.
 *
 * Ids not in the payload keep their rows — they are simply appended after
 * everything that was listed, which is what happens when a page is reordered from a
 * screen that was loaded before someone else added a row.
 */
final class ReorderRankingEntries
{
    /** @param  list<int>  $orderedIds */
    public function handle(TopRankingPage $page, array $orderedIds): int
    {
        $entries = $page->entries()->get()->keyBy('id');
        $position = 0;
        $applied = 0;

        DB::transaction(function () use ($entries, $orderedIds, &$position, &$applied): void {
            foreach ($orderedIds as $id) {
                $entry = $entries->get($id);

                if ($entry === null) {
                    continue;
                }

                $entries->forget($id);
                $position++;
                $applied++;

                if ($entry->sort_order !== $position) {
                    $entry->sort_order = $position;
                    $entry->save();
                }
            }

            // Whatever the payload did not mention, in the order it already had.
            foreach ($entries->sortBy('sort_order') as $entry) {
                $position++;

                if ($entry->sort_order !== $position) {
                    $entry->sort_order = $position;
                    $entry->save();
                }
            }
        });

        return $applied;
    }
}
