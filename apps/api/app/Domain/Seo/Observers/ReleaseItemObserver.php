<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use App\Domain\Changelog\Models\ChangelogItem;
use Illuminate\Database\Eloquent\Model;

/**
 * A line inside a release.
 *
 * The item has no slug of its own — it is rendered as part of its parent — so the
 * tag to expire is the parent's. `release` is loaded rather than assumed present:
 * an item deleted through a relation may not have it hydrated.
 */
final class ReleaseItemObserver extends RevalidatesFrontend
{
    protected function tagsFor(Model $model): array
    {
        $tags = ['changelog'];

        if ($model instanceof ChangelogItem) {
            $slug = $model->release()->value('slug');

            if (is_string($slug) && $slug !== '') {
                $tags[] = "release:{$slug}";
            }
        }

        return $tags;
    }
}
