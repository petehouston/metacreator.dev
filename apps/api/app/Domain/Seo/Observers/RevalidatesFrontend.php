<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use App\Domain\Seo\Services\FrontendCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared behaviour for the observers that expire front-end caches.
 *
 * Observing the *model* rather than the action is deliberate. There is more than
 * one way a post reaches the public site — the editor, a bulk status change, the
 * scheduled-publish command, a restore from the trash, a seeder — and hanging the
 * invalidation off the write path would mean auditing every one of them and
 * remembering to do it again for the next one. An observer cannot be bypassed by
 * a caller that forgot.
 *
 * The events are the persisted ones (`saved`, `deleted`, `restored`), so nothing
 * is expired on the strength of a write that then rolls back.
 */
abstract class RevalidatesFrontend
{
    public function __construct(protected readonly FrontendCache $cache) {}

    /**
     * The tags a change to this record invalidates.
     *
     * @return list<string>
     */
    abstract protected function tagsFor(Model $model): array;

    public function saved(Model $model): void
    {
        $this->flag($model);
    }

    public function deleted(Model $model): void
    {
        $this->flag($model);
    }

    public function restored(Model $model): void
    {
        $this->flag($model);
    }

    protected function flag(Model $model): void
    {
        $this->cache->invalidate(...$this->tagsFor($model));

        // The sitemap is a statically rendered route, not a tagged fetch, so
        // expiring data tags alone would leave it serving the previous URL list
        // until its own hourly timer came round.
        $this->cache->invalidatePath('/sitemap.xml');
    }

    /**
     * A slug-scoped tag, using the value the record had *before* this write when
     * the slug changed.
     *
     * Renaming a post leaves a cache entry under the old slug that nothing would
     * ever expire; the page at the old URL is gone, but the listing that links to
     * it is cached under the same tag. Returning both keeps the two in step.
     *
     * @return list<string>
     */
    protected function slugTags(Model $model, string $prefix): array
    {
        $slugs = [];

        $current = $model->getAttribute('slug');
        if (is_string($current) && $current !== '') {
            $slugs[] = $current;
        }

        $original = $model->getOriginal('slug');
        if (is_string($original) && $original !== '' && $original !== $current) {
            $slugs[] = $original;
        }

        return array_map(fn (string $slug): string => "{$prefix}:{$slug}", $slugs);
    }
}
