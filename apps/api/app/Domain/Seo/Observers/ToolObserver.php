<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use Illuminate\Database\Eloquent\Model;

/** A tool change expires its own page and the catalog listing. */
final class ToolObserver extends RevalidatesFrontend
{
    protected function tagsFor(Model $model): array
    {
        return ['tools', ...$this->slugTags($model, 'tool')];
    }
}
