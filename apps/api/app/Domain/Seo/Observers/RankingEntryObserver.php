<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use App\Domain\Seo\Services\FrontendCache;
use App\Domain\TopRanking\Models\TopRankingEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * A row inside a ranking.
 *
 * The row has no address of its own, so what it expires is its page — and the
 * index, because the index prints each page's row count and its refresh date.
 *
 * This observer is what makes the whole feature's caching honest. A sync writes
 * five hundred rows without touching a single page's own attributes on most runs;
 * hanging invalidation off the page alone would mean a refreshed ranking sat behind
 * a six-hour cache with nothing to expire it. {@see FrontendCache}
 * batches per request, so a hundred-row import still sends one call.
 */
final class RankingEntryObserver extends RevalidatesFrontend
{
    protected function tagsFor(Model $model): array
    {
        $tags = ['top-ranking'];

        if ($model instanceof TopRankingEntry) {
            // Read off the relation rather than assumed loaded: a row deleted
            // through its parent may not have the page hydrated.
            $slug = $model->page()->value('slug');

            if (is_string($slug) && $slug !== '') {
                $tags[] = "ranking:{$slug}";
            }
        }

        return $tags;
    }
}
