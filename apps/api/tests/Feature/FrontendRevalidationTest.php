<?php

declare(strict_types=1);

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Models\PostCategory;
use App\Domain\Blog\Services\PostContentService;
use App\Domain\Changelog\Models\ChangelogRelease;
use App\Domain\Seo\Services\FrontendCache;
use App\Domain\Settings\Setting;
use App\Domain\Tools\Models\Tool;
use App\Domain\TopRanking\Enums\RankingPlatform;
use App\Domain\TopRanking\Models\TopRankingPage;
use App\Domain\Users\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Publishing has to reach the front end.
 *
 * Public pages cache their API reads for five minutes, so an editor's save is
 * invisible until the matching cache tags are dropped. These tests pin the wiring
 * that does the dropping: without it everything still "works" — the row is
 * written, the API returns it — and the site simply shows yesterday's content for
 * five minutes, which is exactly the failure that is easy to ship and hard to
 * notice.
 */
beforeEach(function (): void {
    config()->set('frontend.revalidate_url', 'http://web.test/api/revalidate');
    config()->set('frontend.revalidate_secret', 'test-secret');

    Http::preventStrayRequests();
    Http::fake(['web.test/*' => Http::response(['data' => []])]);
});

/** Runs the terminating callbacks the way a finished request or command would. */
function flushRevalidation(): void
{
    app(FrontendCache::class)->flush();
}

/** @return list<string> every tag sent to the front end, deduplicated. */
function sentTags(): array
{
    $tags = [];

    foreach (Http::recorded() as [$request]) {
        /** @var Request $request */
        $tags = [...$tags, ...($request->data()['tags'] ?? [])];
    }

    return array_values(array_unique($tags));
}

function makeRevalidationPost(array $attributes = []): Post
{
    $category = PostCategory::query()->firstOrCreate(
        ['slug' => 'revalidation'],
        ['name' => 'Revalidation', 'sort_order' => 0],
    );

    return Post::query()->create([
        ...app(PostContentService::class)->derive(['blocks' => [
            ['type' => 'paragraph', 'data' => ['html' => 'Hello world.']],
        ]]),
        'slug' => 'post-'.uniqid(),
        'title' => 'A test post',
        'category_id' => $category->id,
        'author_id' => User::factory()->create()->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subMinute(),
        ...$attributes,
    ]);
}

it('expires the post listing and the post itself when a post is saved', function (): void {
    $post = makeRevalidationPost(['slug' => 'a-published-post']);

    flushRevalidation();

    expect(sentTags())
        ->toContain('posts')
        ->toContain("post:{$post->slug}");
});

it('expires the old slug as well when a post is renamed', function (): void {
    $post = makeRevalidationPost(['slug' => 'the-original-slug']);
    flushRevalidation();
    Http::fake(['web.test/*' => Http::response(['data' => []])]);

    $post->update(['slug' => 'the-new-slug']);
    flushRevalidation();

    // Only expiring the new slug would leave the listing cached under a tag that
    // nothing ever drops, so the renamed post keeps its old title in the grid.
    expect(sentTags())
        ->toContain('post:the-new-slug')
        ->toContain('post:the-original-slug');
});

it('expires the listing when a post is deleted', function (): void {
    $post = makeRevalidationPost();
    flushRevalidation();
    Http::fake(['web.test/*' => Http::response(['data' => []])]);

    $post->delete();
    flushRevalidation();

    expect(sentTags())->toContain('posts');
});

it('expires the catalog when a tool changes', function (): void {
    $tool = Tool::factory()->create(['slug' => 'a-tool']);

    flushRevalidation();

    expect(sentTags())
        ->toContain('tools')
        ->toContain('tool:a-tool');
});

it('expires the changelog when a release changes', function (): void {
    ChangelogRelease::query()->create([
        'slug' => 'v1-2-3',
        'version' => '1.2.3',
        'title' => 'A release',
        'released_at' => now(),
    ]);

    flushRevalidation();

    expect(sentTags())
        ->toContain('changelog')
        ->toContain('release:v1-2-3');
});

it('expires the settings map when a setting is written', function (): void {
    Setting::query()->create([
        'key' => 'site.tagline',
        'value' => ['v' => 'A new tagline'],
        'type' => 'string',
        'group' => 'general',
        'is_public' => true,
    ]);

    flushRevalidation();

    expect(sentTags())->toContain('settings');
});

it('expires the sitemap, which no data tag covers', function (): void {
    makeRevalidationPost();

    flushRevalidation();

    $paths = [];
    foreach (Http::recorded() as [$request]) {
        $paths = [...$paths, ...($request->data()['paths'] ?? [])];
    }

    expect($paths)->toContain('/sitemap.xml');
});

it('sends one request for a batch of writes rather than one per model', function (): void {
    makeRevalidationPost();
    makeRevalidationPost();
    makeRevalidationPost();

    flushRevalidation();

    // The batching is the reason this is safe to hang off model events at all: a
    // bulk status change over two hundred posts must not become two hundred HTTP
    // calls made while the editor waits.
    Http::assertSentCount(1);
});

it('signs the call with the shared secret', function (): void {
    makeRevalidationPost();

    flushRevalidation();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Revalidate-Secret', 'test-secret'));
});

it('does not call the front end when no URL is configured', function (): void {
    config()->set('frontend.revalidate_url', null);

    makeRevalidationPost();
    flushRevalidation();

    // A CLI-only install, a test run and a local stack without the web container
    // all land here. This must be silent, not an error.
    Http::assertNothingSent();
});

it('never lets a front-end failure break the write that triggered it', function (): void {
    Http::fake(['web.test/*' => Http::response(['error' => 'boom'], 500)]);

    $post = makeRevalidationPost();

    // The row is committed before the flush runs, so a front end that is down or
    // misconfigured degrades to the old five-minute timer rather than 500ing a save.
    expect(fn () => flushRevalidation())->not->toThrow(Exception::class);
    expect(Post::query()->whereKey($post->id)->exists())->toBeTrue();
});

it('expires a ranking page and the index when a row changes', function (): void {
    // The case this feature would have got wrong: a sync rewrites hundreds of rows
    // and never touches the page row, so observing the page alone would leave a
    // refreshed ranking behind a six-hour cache with nothing to expire it.
    $page = TopRankingPage::query()->create([
        'slug' => 'most-followed-somewhere',
        'platform' => RankingPlatform::Instagram,
        'title' => 'Top accounts',
        'source_page' => 'List of most-followed accounts',
    ]);

    flushRevalidation();
    Http::fake(['web.test/*' => Http::response(['data' => []])]);

    $page->entries()->create([
        'name' => 'someone',
        'handle' => 'someone',
        'sort_order' => 1,
        'match_key' => 'someone',
    ]);

    flushRevalidation();

    expect(sentTags())
        ->toContain('top-ranking')
        ->toContain('ranking:most-followed-somewhere');
});
