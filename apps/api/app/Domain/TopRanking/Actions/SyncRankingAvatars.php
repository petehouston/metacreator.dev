<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Actions;

use App\Domain\TopRanking\Avatars\AvatarResolver;
use App\Domain\TopRanking\Enums\AvatarStatus;
use App\Domain\TopRanking\Models\TopRankingEntry;
use App\Domain\TopRanking\Models\TopRankingPage;
use App\Support\Http\SafeHttpClient;
use Carbon\CarbonImmutable;

/**
 * Fills in the pictures for one row, or for a whole page.
 *
 * Sequential, deliberately. {@see SafeHttpClient::attemptPool()}
 * exists and would finish a page in a fifth of the time, and it is the wrong tool
 * here: fifty parallel requests to one host is indistinguishable from a small
 * denial-of-service, and these are the platforms most likely to answer it with a
 * block that costs us the feature. A page takes a minute in a queued job that
 * nobody is waiting on.
 */
final class SyncRankingAvatars
{
    public function __construct(private readonly AvatarResolver $resolver) {}

    /**
     * Four outcomes, not two.
     *
     * `kept` is the one that has to exist: when a platform rate-limits us, a row
     * that already has a good picture fails its re-check and still shows a picture.
     * Counting that as "unavailable" reports a page as broken when nothing a reader
     * can see has changed — and sends an editor looking for a problem that is not
     * there.
     *
     * @return array{resolved: int, kept: int, unavailable: int, skipped: int}
     */
    public function forPage(TopRankingPage $page, bool $force = false): array
    {
        $counts = ['resolved' => 0, 'kept' => 0, 'unavailable' => 0, 'skipped' => 0];

        foreach ($page->entries()->get() as $entry) {
            if (! $force && ! $this->needsAttention($entry)) {
                $counts['skipped']++;

                continue;
            }

            $resolved = $this->forEntry($entry, $page);

            $counts[match (true) {
                $resolved => 'resolved',
                $entry->avatar_url !== null => 'kept',
                default => 'unavailable',
            }]++;
        }

        $page->avatars_synced_at = CarbonImmutable::now();
        $page->save();

        return $counts;
    }

    /** True when a picture was found. */
    public function forEntry(TopRankingEntry $entry, ?TopRankingPage $page = null): bool
    {
        $page ??= $entry->page;
        $result = $this->resolver->resolve($entry, $page->platform);

        $entry->avatar_checked_at = CarbonImmutable::now();

        if (! $result->isFound()) {
            // A failed re-check does *not* clear a picture we already have. The
            // usual cause is a rate limit or a momentary block, and blanking fifty
            // working avatars because a platform was grumpy for a minute is a far
            // worse outcome than showing one that is a week stale.
            if ($entry->avatar_url === null) {
                $entry->avatar_status = AvatarStatus::Unavailable;
            }

            $entry->save();

            return false;
        }

        $entry->avatar_url = $result->url;
        $entry->avatar_status = AvatarStatus::Ok;
        $entry->avatar_source = $result->source;
        $entry->avatar_expires_at = $result->expiresAt;
        $entry->save();

        return true;
    }

    /**
     * Which rows a non-forced run bothers with.
     *
     * Never re-checked, expired, or expiring before the next weekly pass. A row
     * with a good unsigned link is left alone — re-fetching a YouTube channel page
     * every week to learn that the picture has not changed is fifty requests spent
     * on nothing, and it is the difference between this job being polite and being
     * a nuisance.
     */
    private function needsAttention(TopRankingEntry $entry): bool
    {
        if ($entry->avatar_url === null || $entry->avatar_status !== AvatarStatus::Ok) {
            return true;
        }

        return $entry->avatar_expires_at !== null
            && $entry->avatar_expires_at->isBefore(CarbonImmutable::now()->addDays(8));
    }
}
