<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Jobs;

use App\Domain\Seo\Services\FrontendCache;
use App\Domain\TopRanking\Actions\SyncRankingAvatars;
use App\Domain\TopRanking\Actions\SyncRankingPageFromWikipedia;
use App\Domain\TopRanking\Models\TopRankingPage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bring one page up to date: re-read the article, then fill in any missing pictures.
 *
 * **One job per page, not one for all of them.** A single job over nine pages would
 * make four hundred outbound requests inside one worker slot, comfortably past the
 * `maintenance` queue's 900-second timeout — and a timeout there would leave the
 * ninth page never refreshed while reporting nothing. Per page, each run is one
 * article plus at most fifty pictures, which finishes in a minute or two and fails
 * in isolation.
 *
 * `tries = 1`, matching the queue's own configuration. A retry would re-fetch an
 * article that is not going to have changed in thirty seconds; the weekly schedule
 * is the retry, and a page that failed says so in the admin until then.
 */
final class RefreshTopRankingPage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private readonly int $pageId,
        private readonly bool $withAvatars = true,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(
        SyncRankingPageFromWikipedia $sync,
        SyncRankingAvatars $avatars,
        FrontendCache $cache,
    ): void {
        $page = TopRankingPage::query()->find($this->pageId);

        if ($page === null) {
            return;
        }

        $result = $sync->handle($page);

        Log::info('top-ranking.sync', [
            'page' => $page->slug,
            'status' => $result->status->value,
            'summary' => $result->summary(),
        ]);

        // Avatars are attempted even after a failed article read. The rows already
        // on the page are still real accounts, and a page whose article moved should
        // not also go pictureless while somebody renames the source.
        if (! $this->withAvatars) {
            return;
        }

        try {
            $counts = $avatars->forPage($page);

            Log::info('top-ranking.avatars', ['page' => $page->slug, ...$counts]);
        } catch (Throwable $e) {
            // Never fail the job over pictures. The ranking is the product; an
            // avatar is decoration, and a retry storm over a rate-limited CDN would
            // cost more than the missing images.
            Log::warning('top-ranking.avatars.failed', ['page' => $page->slug, 'error' => $e->getMessage()]);
        } finally {
            // Flushed by hand rather than left to the terminating callback. That
            // callback is tied to the request or command lifecycle, and a worker
            // process outlives both — a job that queued its invalidation and never
            // flushed would refresh the data and leave the public page serving the
            // previous ranking until its own timer expired.
            $cache->flush();
        }
    }
}
