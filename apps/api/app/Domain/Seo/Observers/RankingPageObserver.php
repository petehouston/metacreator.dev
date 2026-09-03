<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use Illuminate\Database\Eloquent\Model;

/** A ranking page expires its own table and the index that lists it. */
final class RankingPageObserver extends RevalidatesFrontend
{
    protected function tagsFor(Model $model): array
    {
        return ['top-ranking', ...$this->slugTags($model, 'ranking')];
    }
}
