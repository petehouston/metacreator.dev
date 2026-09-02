<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use App\Domain\Blog\Models\PostCategory;
use Illuminate\Database\Eloquent\Model;

/**
 * Categories and tags.
 *
 * Renaming either changes the filter chips on the listing and the crumb on every
 * post that uses it, so the whole `posts` tag goes rather than one slug.
 */
final class PostTaxonomyObserver extends RevalidatesFrontend
{
    protected function tagsFor(Model $model): array
    {
        return [
            'posts',
            $model instanceof PostCategory ? 'post-categories' : 'post-tags',
        ];
    }
}
