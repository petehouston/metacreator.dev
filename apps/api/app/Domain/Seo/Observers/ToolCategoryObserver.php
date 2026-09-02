<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use Illuminate\Database\Eloquent\Model;

/** Catalog categories drive the filter rail, which every tool page renders. */
final class ToolCategoryObserver extends RevalidatesFrontend
{
    protected function tagsFor(Model $model): array
    {
        return ['tools', 'tool-categories'];
    }
}
