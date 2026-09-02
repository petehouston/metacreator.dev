<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Models\PostCategory;
use App\Domain\Blog\Models\Tag;
use App\Domain\Changelog\Models\ChangelogItem;
use App\Domain\Changelog\Models\ChangelogRelease;
use App\Domain\Seo\Observers\PostObserver;
use App\Domain\Seo\Observers\PostTaxonomyObserver;
use App\Domain\Seo\Observers\ReleaseItemObserver;
use App\Domain\Seo\Observers\ReleaseObserver;
use App\Domain\Seo\Observers\SettingObserver;
use App\Domain\Seo\Observers\ToolCategoryObserver;
use App\Domain\Seo\Observers\ToolObserver;
use App\Domain\Seo\Services\FrontendCache;
use App\Domain\Settings\Setting;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolCategory;
use Illuminate\Support\ServiceProvider;

/**
 * Wires publishable content to the front end's on-demand ISR endpoint.
 *
 * Public pages cache their API reads for five minutes. Without these observers
 * that window is the *only* thing that ever refreshes them, which is what makes a
 * published post take minutes to appear. With them, a write expires the affected
 * cache tags as soon as the response is flushed and the next request re-renders.
 *
 * Everything registered here is content that is rendered on a public, cached page.
 * Models that only appear behind auth (runs, tickets, notifications) are left out
 * on purpose — those pages are not cached, so expiring tags for them would be pure
 * overhead.
 */
final class FrontendCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton, because the batching is the point: every observer in a request
        // must add its tags to the same collection for them to go out as one call.
        $this->app->singleton(FrontendCache::class);
    }

    public function boot(): void
    {
        Post::observe(PostObserver::class);
        PostCategory::observe(PostTaxonomyObserver::class);
        Tag::observe(PostTaxonomyObserver::class);

        Tool::observe(ToolObserver::class);
        ToolCategory::observe(ToolCategoryObserver::class);

        ChangelogRelease::observe(ReleaseObserver::class);
        ChangelogItem::observe(ReleaseItemObserver::class);

        Setting::observe(SettingObserver::class);
    }
}
