<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use Illuminate\Database\Eloquent\Model;

/** A changelog release expires its own entry and the index. */
final class ReleaseObserver extends RevalidatesFrontend
{
    protected function tagsFor(Model $model): array
    {
        return ['changelog', ...$this->slugTags($model, 'release')];
    }
}
