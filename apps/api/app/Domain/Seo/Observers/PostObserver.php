<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use Illuminate\Database\Eloquent\Model;

/** A post change expires its own page and every listing that can contain it. */
final class PostObserver extends RevalidatesFrontend
{
    protected function tagsFor(Model $model): array
    {
        return ['posts', ...$this->slugTags($model, 'post')];
    }
}
