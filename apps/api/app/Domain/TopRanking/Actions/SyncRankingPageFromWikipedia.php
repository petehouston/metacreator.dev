<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Actions;

use App\Domain\TopRanking\Enums\AvatarStatus;
use App\Domain\TopRanking\Enums\EntrySource;
use App\Domain\TopRanking\Enums\SyncStatus;
use App\Domain\TopRanking\Models\TopRankingEntry;
use App\Domain\TopRanking\Models\TopRankingPage;
use App\Domain\TopRanking\Wikipedia\ParsedRow;
use App\Domain\TopRanking\Wikipedia\WikipediaClient;
use App\Domain\TopRanking\Wikipedia\WikitableParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Refreshes one page from its Wikipedia article.
 *
 * **This reconciles; it does not replace.** Deleting every row and re-importing
 * would be four lines shorter and would be wrong twice over: it would throw away a
 * resolved avatar every week — the expensive part of this feature, fifty HTTP
 * requests a page — and it would silently undo whatever an editor arranged by hand.
 * So rows are matched on a normalised key, and what happens to an unmatched row
 * depends on who put it there:
 *
 *   - imported and still in the article  →  numbers updated, avatar left alone
 *   - imported and gone from the article →  removed; it is no longer in the top N
 *   - added by hand in admin             →  never touched, never removed
 *   - pinned                             →  never moved and never removed, whatever
 *                                           the article says
 *
 * That last one is the release valve that makes an unattended weekly job safe on a
 * page someone has curated. Without it, the only way to keep an edit would be to
 * turn the sync off.
 */
final class SyncRankingPageFromWikipedia
{
    public function __construct(
        private readonly WikipediaClient $wikipedia,
        private readonly WikitableParser $parser,
    ) {}

    public function handle(TopRankingPage $page): SyncResult
    {
        try {
            $rows = $this->parser->parse(
                $this->wikipedia->html($page->source_page),
                $page->source_table,
                $page->platform,
                $page->row_limit,
            );
        } catch (Throwable $e) {
            // The two failures that actually happen — a renamed article and a
            // restructured table — are both an admin's to fix, and both are
            // described precisely by the exception. Recording the message on the
            // page is what turns "sync failed" into something actionable.
            return $this->record($page, SyncResult::failed($e->getMessage()));
        }

        if ($rows === []) {
            return $this->record($page, SyncResult::failed('That article parsed, but the table had no usable rows.'));
        }

        $result = DB::transaction(fn (): SyncResult => $this->reconcile($page, $rows));

        return $this->record($page, $result);
    }

    /** @param  list<ParsedRow>  $rows */
    private function reconcile(TopRankingPage $page, array $rows): SyncResult
    {
        /** @var array<string, TopRankingEntry> $existing */
        $existing = $page->entries()->get()->keyBy('match_key')->all();

        $added = 0;
        $updated = 0;

        /** @var list<TopRankingEntry> $ordered The rows the article dictates, in its order. */
        $ordered = [];
        $seen = [];

        foreach ($rows as $row) {
            $key = TopRankingEntry::matchKeyFor($row->handle, $row->name);

            // A duplicate key inside one article — the same account listed twice
            // under different spellings — would violate the unique index and abort
            // the whole run. First mention wins.
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $entry = $existing[$key] ?? null;

            if ($entry === null) {
                $entry = new TopRankingEntry([
                    'page_id' => $page->id,
                    'match_key' => $key,
                    'source' => EntrySource::Wikipedia,
                    'avatar_status' => AvatarStatus::Pending,
                ]);
                $added++;
            } elseif ($entry->source === EntrySource::Manual) {
                // A hand-added row that the article has now caught up with. It stays
                // the editor's row — their name and description survive — but it
                // keeps its place in the ranking rather than being appended.
                $ordered[] = $entry;

                continue;
            } else {
                $updated++;
            }

            $this->fill($entry, $row);
            $entry->save();

            $ordered[] = $entry;
        }

        // Rows the article no longer lists. Imported ones fall off; anything an
        // editor added or pinned stays, and is appended after the ranked rows in
        // the order it already had.
        $leftovers = [];
        $removed = 0;
        $kept = 0;

        foreach ($existing as $key => $entry) {
            if (isset($seen[$key])) {
                continue;
            }

            if ($entry->source === EntrySource::Manual || $entry->is_pinned) {
                $leftovers[] = $entry;
                $kept++;

                continue;
            }

            $entry->delete();
            $removed++;
        }

        usort($leftovers, static fn (TopRankingEntry $a, TopRankingEntry $b): int => $a->sort_order <=> $b->sort_order);

        $this->reorder([...$ordered, ...$leftovers]);

        // "Partial" means the article gave back so much less than asked for that the
        // table has probably been restructured. Half is the line: an article listing
        // 45 of 50 is simply an article, while one listing 3 of 50 is a parse that
        // found a fragment and should not be reported as a success.
        $shortfall = count($rows) * 2 < $page->row_limit;

        return new SyncResult(
            status: $shortfall ? SyncStatus::Partial : SyncStatus::Ok,
            imported: count($rows),
            added: $added,
            updated: $updated,
            removed: $removed,
            kept: $kept,
            message: count($rows) < $page->row_limit
                ? sprintf('The article currently lists %d of the %d rows this page asks for.', count($rows), $page->row_limit)
                : null,
        );
    }

    /**
     * Copy what the article says onto the row, and nothing else.
     *
     * The avatar fields are conspicuously absent. They are ours, not Wikipedia's,
     * and re-resolving fifty pictures because a follower count moved would turn a
     * cheap weekly refresh into an expensive one for no gain.
     */
    private function fill(TopRankingEntry $entry, ParsedRow $row): void
    {
        // A changed profile URL means the account moved or was renamed, which makes
        // the stored picture a picture of something else. That is the one case
        // where the avatar has to be given up and looked for again.
        if ($entry->exists && $entry->profile_url !== $row->profileUrl && $row->profileUrl !== null) {
            $entry->avatar_url = null;
            $entry->avatar_status = AvatarStatus::Pending;
            $entry->avatar_source = null;
            $entry->avatar_expires_at = null;
        }

        $entry->fill([
            'name' => mb_substr(ltrim($row->name, '@'), 0, 200),
            'handle' => $row->handle,
            'owner' => $row->owner,
            'profile_url' => $row->profileUrl ?? $entry->profile_url,
            'metric_value' => $row->metric,
            'secondary_metric_value' => $row->secondaryMetric,
            'country' => $row->country,
            'category' => $row->category,
            'language' => $row->language,
            'description' => $row->description,
        ]);
    }

    /**
     * Number the page 1..N, with pinned rows holding the positions they were given.
     *
     * The two-pass shape is what makes a pin mean what an editor thinks it means:
     * pin row 3 and it is still row 3 next week, with the ranking flowing around it,
     * rather than row 3 until something above it changes.
     *
     * @param  list<TopRankingEntry>  $flow
     */
    private function reorder(array $flow): void
    {
        $taken = [];

        foreach ($flow as $entry) {
            if ($entry->is_pinned) {
                $taken[$entry->sort_order] = true;
            }
        }

        $position = 1;

        foreach ($flow as $entry) {
            if ($entry->is_pinned) {
                continue;
            }

            while (isset($taken[$position])) {
                $position++;
            }

            if ($entry->sort_order !== $position) {
                $entry->sort_order = $position;
                $entry->save();
            }

            $position++;
        }
    }

    /** Stamp the outcome on the page, so the admin screen can report it without re-running anything. */
    private function record(TopRankingPage $page, SyncResult $result): SyncResult
    {
        $page->sync_status = $result->status;
        $page->sync_message = mb_substr($result->summary(), 0, 500);

        // The timestamp only moves on a run that actually read the article. A failed
        // run that refreshed `synced_at` would report the page as current while
        // showing a year-old ranking, which is the one lie this feature must not tell.
        if ($result->status !== SyncStatus::Failed) {
            $page->synced_at = CarbonImmutable::now();
        }

        $page->save();

        return $result;
    }
}
