<?php

declare(strict_types=1);

namespace App\Domain\Seo\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Drops the front end's cache entries for content that just changed.
 *
 * Public pages on the Next side fetch this API with `next: { tags, revalidate }`,
 * where the revalidate window is five minutes. That window is what an editor
 * experiences as "my post is saved but the site still shows the old one" — the
 * page is not stale by mistake, it is stale on purpose until the timer runs out.
 *
 * This class is the other half of that design: the API says which tags a write
 * touched, the front end drops exactly those, and the next request re-renders.
 * The timer stays as the safety net for anything that changes the database
 * without going through Eloquent.
 *
 * Two properties matter more than the HTTP call itself:
 *
 * - **Batched.** One save fires several model events (the post, its tags, its SEO
 *   row). Collecting tags and flushing once per request turns what would be five
 *   round trips into one.
 * - **Deferred.** The flush is registered as a terminating callback, so it happens
 *   after the editor's response has been sent. Saving a post is never slowed down
 *   by, and never fails because of, the front end.
 */
final class FrontendCache
{
    /**
     * Tags collected so far this request, used as a set.
     *
     * @var array<string, true>
     */
    private array $tags = [];

    /** @var array<string, true> */
    private array $paths = [];

    /** Whether the terminating callback for this request is already registered. */
    private bool $scheduled = false;

    public function __construct(
        private readonly Application $app,
        private readonly Http $http,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Queue one or more cache tags for invalidation at the end of the request.
     *
     * Blank entries are dropped rather than sent: a tag built from a null slug
     * would otherwise ask the front end to expire the tag named "post:".
     */
    public function invalidate(string ...$tags): void
    {
        foreach ($tags as $tag) {
            if ($tag !== '') {
                $this->tags[$tag] = true;
            }
        }

        $this->schedule();
    }

    /**
     * Queue a route path for invalidation.
     *
     * Only needed for routes whose freshness is not governed by a data tag —
     * `/sitemap.xml` is the one that matters, because it is a statically rendered
     * route that would otherwise keep serving until its own hourly timer expires.
     */
    public function invalidatePath(string ...$paths): void
    {
        foreach ($paths as $path) {
            if ($path !== '') {
                $this->paths[$path] = true;
            }
        }

        $this->schedule();
    }

    /**
     * Send everything collected so far, and reset.
     *
     * Public so a caller that needs the front end updated *before* it returns —
     * a console command that exits immediately, say — can force the flush rather
     * than rely on the terminating callback.
     */
    public function flush(): void
    {
        $tags = array_keys($this->tags);
        $paths = array_keys($this->paths);

        $this->tags = [];
        $this->paths = [];
        $this->scheduled = false;

        if ($tags === [] && $paths === []) {
            return;
        }

        $url = (string) config('frontend.revalidate_url');
        $secret = (string) config('frontend.revalidate_secret');

        // Not configured is a normal state, not a failure: tests, the local CLI and
        // any install without a front end all land here. Logging it as an error
        // would make a clean test run noisy.
        if ($url === '' || $secret === '') {
            return;
        }

        try {
            $response = $this->http
                ->timeout((float) config('frontend.revalidate_timeout', 5))
                ->withHeaders(['X-Revalidate-Secret' => $secret])
                ->post($url, ['tags' => $tags, 'paths' => $paths]);

            if ($response->failed()) {
                // Worth a warning rather than silence: a 403 here means the two
                // secrets have drifted apart, and the only visible symptom would
                // otherwise be that publishing quietly went back to taking five
                // minutes.
                $this->logger->warning('Front-end revalidation was rejected.', [
                    'status' => $response->status(),
                    'tags' => $tags,
                    'paths' => $paths,
                ]);
            }
        } catch (Throwable $e) {
            // Never propagate: this runs after the response, and the write it
            // relates to has already been committed. The five-minute timer is the
            // fallback, so the worst case is the old behaviour.
            $this->logger->warning('Front-end revalidation failed.', [
                'message' => $e->getMessage(),
                'tags' => $tags,
                'paths' => $paths,
            ]);
        }
    }

    /**
     * Register the one terminating callback for this request.
     *
     * Guarded by a flag rather than registered per call, so a bulk edit touching
     * two hundred posts still results in a single HTTP request at the end.
     */
    private function schedule(): void
    {
        if ($this->scheduled) {
            return;
        }

        $this->scheduled = true;

        $this->app->terminating(fn () => $this->flush());
    }
}
