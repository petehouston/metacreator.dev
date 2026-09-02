<?php

declare(strict_types=1);

namespace App\Domain\Seo\Observers;

use Illuminate\Database\Eloquent\Model;

/**
 * Public settings are read by the layout, so they are on every rendered page.
 *
 * Only the `settings` tag is expired, not the pages themselves: each page fetches
 * the settings map with that tag, so dropping it is enough to re-render all of
 * them on next request.
 */
final class SettingObserver extends RevalidatesFrontend
{
    protected function tagsFor(Model $model): array
    {
        return ['settings'];
    }
}
