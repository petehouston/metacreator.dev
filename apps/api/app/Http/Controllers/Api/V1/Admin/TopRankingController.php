<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Seo\Actions\SaveSeoMeta;
use App\Domain\TopRanking\Actions\ReorderRankingEntries;
use App\Domain\TopRanking\Actions\SyncRankingAvatars;
use App\Domain\TopRanking\Actions\SyncRankingPageFromWikipedia;
use App\Domain\TopRanking\Enums\AvatarStatus;
use App\Domain\TopRanking\Enums\EntrySource;
use App\Domain\TopRanking\Enums\RankingPlatform;
use App\Domain\TopRanking\Jobs\RefreshTopRankingPage;
use App\Domain\TopRanking\Models\TopRankingEntry;
use App\Domain\TopRanking\Models\TopRankingPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderTopRankingEntriesRequest;
use App\Http\Requests\Admin\SaveTopRankingEntryRequest;
use App\Http\Requests\Admin\SaveTopRankingPageRequest;
use App\Http\Resources\Admin\AdminTopRankingEntryResource;
use App\Http\Resources\Admin\AdminTopRankingPageResource;
use App\Http\Resources\ApiCollection;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Managing the top-ranking pages and their rows.
 *
 * The two sync endpoints run **inline**, not queued, and that is a considered
 * choice rather than an oversight. An editor who presses "Sync from Wikipedia"
 * is asking a question — does this article still parse — and a 202 that answers
 * "we will let you know" gives them nothing to act on; they would reload the
 * screen until something changed. One article is a single request and returns in
 * about a second. The unattended weekly pass is the queued path
 * ({@see RefreshTopRankingPage}), because nobody is
 * waiting on that one.
 *
 * "Sync every avatar on this page" is the exception inside the exception: fifty
 * sequential fetches is a minute, which is too long to hold a request open, so it
 * is capped and reports how far it got.
 */
final class TopRankingController extends Controller
{
    public function __construct(
        private readonly SyncRankingPageFromWikipedia $sync,
        private readonly SyncRankingAvatars $avatars,
        private readonly ReorderRankingEntries $reorder,
        private readonly SaveSeoMeta $saveSeo,
        private readonly AuditLogger $audit,
    ) {}

    /** @return ApiCollection<AdminTopRankingPageResource> */
    public function index(Request $request): ApiCollection
    {
        $pages = TopRankingPage::query()
            ->withCount([
                'entries',
                // Counted in the same round trip as the list, so the "12 rows have
                // no picture" chip costs nothing per row.
                'entries as missing_avatars' => fn ($query) => $query
                    ->where(fn ($q) => $q
                        ->whereNull('avatar_url')
                        ->orWhere('avatar_status', '!=', AvatarStatus::Ok->value)
                        ->orWhere('avatar_expires_at', '<=', CarbonImmutable::now())),
            ])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('platform'), fn ($q) => $q->where('platform', $request->string('platform')))
            ->inMenuOrder()
            ->get();

        return (new ApiCollection($pages, AdminTopRankingPageResource::class))->additional([
            'meta' => ['platforms' => RankingPlatform::catalog()],
        ]);
    }

    public function show(TopRankingPage $page): AdminTopRankingPageResource
    {
        return new AdminTopRankingPageResource($page->load(['entries', 'seo.ogMedia']));
    }

    public function store(SaveTopRankingPageRequest $request): AdminTopRankingPageResource
    {
        $data = $request->validated();
        $data['slug'] = $this->slug($data['slug'] ?? null, (string) $data['title']);

        // Held back from the create: SEO lives in its own polymorphic row, and a
        // `seo` key reaching the model would be a column that does not exist.
        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        $page = TopRankingPage::query()->create($data);

        if (is_array($seo)) {
            $this->saveSeo->handle($page, $seo);
        }

        $this->audit->record('created', $page, $request->user(), after: [
            'title' => $page->title,
            'slug' => $page->slug,
            'source_page' => $page->source_page,
        ]);

        return new AdminTopRankingPageResource($page);
    }

    public function update(SaveTopRankingPageRequest $request, TopRankingPage $page): AdminTopRankingPageResource
    {
        $before = [
            'title' => $page->title,
            'slug' => $page->slug,
            'source_page' => $page->source_page,
            'source_table' => $page->source_table,
            'row_limit' => $page->row_limit,
            'is_published' => $page->is_published,
        ];

        $data = $request->validated();
        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->slug($data['slug'], $data['title'] ?? $page->title, $page);
        }

        $page->update($data);

        if (is_array($seo)) {
            $this->saveSeo->handle($page, $seo);

            // The SEO row is a sibling, so writing it does not touch the page and
            // its observer never fires — the public page would keep serving the old
            // meta tags until the weekly sync happened to save something else.
            $page->touch();
        }

        $this->audit->record('updated', $page, $request->user(), before: $before, after: [
            'title' => $page->title,
            'slug' => $page->slug,
            'source_page' => $page->source_page,
            'source_table' => $page->source_table,
            'row_limit' => $page->row_limit,
            'is_published' => $page->is_published,
        ]);

        return new AdminTopRankingPageResource($page->load(['entries', 'seo.ogMedia']));
    }

    public function destroy(Request $request, TopRankingPage $page): JsonResponse
    {
        $this->audit->record('deleted', $page, $request->user(), before: [
            'title' => $page->title,
            'slug' => $page->slug,
            'entries' => $page->entries()->count(),
        ]);

        // Hard, with the rows cascading. Nothing depends on a ranking page and
        // rebuilding one is a sync away, so a soft-delete column would be a
        // permanent complication bought for a recovery nobody would use.
        $page->delete();

        return response()->json(status: 204);
    }

    // ── Sync ─────────────────────────────────────────────────────────────────

    /** Re-read the article now, and report exactly what changed. */
    public function sync(Request $request, TopRankingPage $page): AdminTopRankingPageResource
    {
        $result = $this->sync->handle($page);

        $this->audit->record('synced', $page, $request->user(), after: [
            'status' => $result->status->value,
            'imported' => $result->imported,
            'added' => $result->added,
            'removed' => $result->removed,
        ]);

        return new AdminTopRankingPageResource($page->fresh()?->load(['entries', 'seo.ogMedia']) ?? $page);
    }

    /**
     * Resolve the pictures for every row that needs one.
     *
     * `?force=1` re-checks rows that already have a good link — the flag for after
     * a platform changes the shape of its profile page.
     */
    public function syncAvatars(Request $request, TopRankingPage $page): AdminTopRankingPageResource
    {
        $counts = $this->avatars->forPage($page, $request->boolean('force'));

        $this->audit->record('synced_avatars', $page, $request->user(), after: $counts);

        return (new AdminTopRankingPageResource($page->fresh()?->load(['entries', 'seo.ogMedia']) ?? $page))
            ->additional(['meta' => ['avatars' => $counts]]);
    }

    /** Resolve one row's picture. The per-row retry button. */
    public function syncEntryAvatar(TopRankingPage $page, TopRankingEntry $entry): AdminTopRankingEntryResource
    {
        abort_if($entry->page_id !== $page->id, 404);

        $this->avatars->forEntry($entry, $page);

        return new AdminTopRankingEntryResource($entry->refresh());
    }

    // ── Rows ─────────────────────────────────────────────────────────────────

    public function storeEntry(SaveTopRankingEntryRequest $request, TopRankingPage $page): AdminTopRankingEntryResource
    {
        $data = $request->validated();
        $name = (string) $data['name'];
        $handle = $data['handle'] ?? null;

        $entry = new TopRankingEntry([
            ...$data,
            'page_id' => $page->id,
            // Added by hand, so the weekly sync will leave it alone for good.
            'source' => EntrySource::Manual,
            'match_key' => TopRankingEntry::matchKeyFor($handle, $name),
            // Appended, not inserted. Where it belongs is the editor's call, made
            // with a drag on the screen they are already looking at.
            'sort_order' => (int) $page->entries()->max('sort_order') + 1,
            'avatar_status' => isset($data['avatar_url']) ? AvatarStatus::Ok : AvatarStatus::Pending,
        ]);

        // No link and no handle leaves nothing to fetch a picture from, so give the
        // row the platform's guess rather than making the editor build the URL.
        $entry->profile_url ??= $handle === null ? null : $page->platform->profileUrl($handle);

        $entry->save();

        $this->audit->record('added_entry', $page, $request->user(), after: ['name' => $entry->name]);

        return new AdminTopRankingEntryResource($entry);
    }

    public function updateEntry(
        SaveTopRankingEntryRequest $request,
        TopRankingPage $page,
        TopRankingEntry $entry,
    ): AdminTopRankingEntryResource {
        abort_if($entry->page_id !== $page->id, 404);

        $data = $request->validated();

        // A pasted picture is trusted and marked good immediately — the editor is
        // doing this precisely because the resolver could not, and sending them
        // away to press a second button would be theatre. Clearing the field puts
        // the row back to "not checked" so the next sync will try again.
        if (array_key_exists('avatar_url', $data)) {
            $url = $data['avatar_url'];
            $entry->avatar_status = $url === null ? AvatarStatus::Pending : AvatarStatus::Ok;
            $entry->avatar_source = $url === null ? null : 'manual';
            $entry->avatar_checked_at = CarbonImmutable::now();
            $entry->avatar_expires_at = $url === null ? null : TopRankingEntry::expiryFor($url);
        }

        $entry->fill($data);

        // The key follows the identity. Without this, renaming a row would make the
        // next sync fail to match it and insert a duplicate alongside.
        $entry->match_key = TopRankingEntry::matchKeyFor($entry->handle, $entry->name);

        $entry->save();

        return new AdminTopRankingEntryResource($entry);
    }

    public function destroyEntry(Request $request, TopRankingPage $page, TopRankingEntry $entry): JsonResponse
    {
        abort_if($entry->page_id !== $page->id, 404);

        $this->audit->record('removed_entry', $page, $request->user(), before: ['name' => $entry->name]);

        $entry->delete();

        // The rows below close the gap, so the table is never numbered 1, 2, 4.
        $this->reorder->handle($page, []);

        return response()->json(status: 204);
    }

    /** @return ApiCollection<AdminTopRankingEntryResource> */
    public function reorderEntries(ReorderTopRankingEntriesRequest $request, TopRankingPage $page): ApiCollection
    {
        /** @var list<int> $ids */
        $ids = array_values(array_map('intval', $request->validated()['ids']));

        $this->reorder->handle($page, $ids);

        return new ApiCollection($page->entries()->get(), AdminTopRankingEntryResource::class);
    }

    /**
     * A slug that is unique and readable.
     *
     * Generated from the title when the editor leaves it blank, which is nearly
     * always — and suffixed rather than rejected on a collision, because a save
     * that fails on a field the editor did not fill in is a puzzle, not a message.
     */
    private function slug(?string $given, string $title, ?TopRankingPage $ignore = null): string
    {
        $base = Str::slug($given !== null && trim($given) !== '' ? $given : $title) ?: 'ranking';
        $slug = $base;
        $suffix = 2;
        $ignoreId = $ignore?->id;

        while (TopRankingPage::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
