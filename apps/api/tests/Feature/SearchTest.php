<?php

declare(strict_types=1);

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use App\Domain\Search\Data\SearchResult;
use App\Domain\Search\Enums\SearchResultType;
use App\Domain\Search\Services\SearchService;
use App\Domain\Search\SitePageCatalog;
use App\Domain\Settings\Setting;
use App\Domain\Settings\Settings;
use App\Domain\TopRanking\Enums\RankingPlatform;
use App\Domain\TopRanking\Models\TopRankingPage;
use App\Domain\Users\Models\User;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

/**
 * Global search: what it finds, what order it puts things in, and who may use it.
 *
 * The ordering assertions carry most of the weight. "Prioritise exact matches" is
 * the whole product requirement, and it is the one property that silently degrades
 * — a search that returns the right set in the wrong order still returns 200.
 */
function enableSearch(bool $enabled = true): void
{
    $setting = Setting::query()->firstOrNew(['key' => 'features.search_enabled']);
    $setting->fill(['type' => 'bool', 'group' => 'features', 'is_public' => true]);
    $setting->setTypedValue($enabled);
    $setting->save();

    app(Settings::class)->flush();
}

function searchablePost(array $attributes = []): Post
{
    return Post::query()->create([
        'slug' => 'post-'.uniqid(),
        'title' => 'A post',
        'blocks' => ['version' => 1, 'blocks' => []],
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
        'author_id' => User::factory()->create()->id,
        ...$attributes,
    ]);
}

function searchableRanking(array $attributes = []): TopRankingPage
{
    return TopRankingPage::query()->create([
        'slug' => 'ranking-'.uniqid(),
        'platform' => RankingPlatform::YouTube,
        'title' => 'Most subscribed YouTube channels',
        'source_page' => 'List of most-subscribed YouTube channels',
        'is_published' => true,
        ...$attributes,
    ]);
}

beforeEach(fn () => enableSearch());

// ── The feature switch ───────────────────────────────────────────────────────

it('404s while search is switched off', function () {
    enableSearch(false);

    $this->getJson('/api/v1/search?q=youtube')->assertNotFound();
});

it('defaults to off when the setting row is missing entirely', function () {
    Setting::query()->where('key', 'features.search_enabled')->delete();
    app(Settings::class)->flush();

    $this->getJson('/api/v1/search?q=youtube')->assertNotFound();
});

// ── What it searches ─────────────────────────────────────────────────────────

it('finds a tool by name', function () {
    counterTool();

    $response = $this->getJson('/api/v1/search?q=word counter')->assertOk();

    expect($response->json('data.0.type'))->toBe('tool')
        ->and($response->json('data.0.title'))->toBe('Word Counter')
        ->and($response->json('data.0.url'))->toStartWith('/tools/');
});

it('finds a post by title and by body text', function () {
    searchablePost(['title' => 'Growing on Threads', 'content_text' => 'A long piece about audience retention.']);

    expect($this->getJson('/api/v1/search?q=threads')->assertOk()->json('data.0.title'))
        ->toBe('Growing on Threads');

    expect($this->getJson('/api/v1/search?q=audience retention')->assertOk()->json('data.0.title'))
        ->toBe('Growing on Threads');
});

it('finds a ranking page', function () {
    searchableRanking();

    $response = $this->getJson('/api/v1/search?q=most subscribed')->assertOk();

    expect($response->json('data.0.type'))->toBe('top_ranking')
        ->and($response->json('data.0.url'))->toStartWith('/top-ranking/');
});

it('finds a static page by a keyword that is not in its title', function () {
    $response = $this->getJson('/api/v1/search?q=refund')->assertOk();

    expect($response->json('data.0.type'))->toBe('page')
        ->and($response->json('data.0.url'))->toBe('/terms');
});

it('leaves unpublished content out', function () {
    searchablePost(['title' => 'Embargoed launch', 'status' => PostStatus::Draft, 'published_at' => null]);
    searchableRanking(['title' => 'Embargoed ranking', 'is_published' => false]);

    expect($this->getJson('/api/v1/search?q=embargoed')->assertOk()->json('data'))->toBe([]);
});

it('drops the blog from results when the blog is switched off', function () {
    searchablePost(['title' => 'Growing on Threads']);

    $setting = Setting::query()->firstOrNew(['key' => 'features.blog_enabled']);
    $setting->fill(['type' => 'bool', 'group' => 'features', 'is_public' => true]);
    $setting->setTypedValue(false);
    $setting->save();
    app(Settings::class)->flush();

    $titles = $this->getJson('/api/v1/search?q=threads')->assertOk()->json('data.*.title');

    expect($titles)->not->toContain('Growing on Threads');
});

// ── Ranking ──────────────────────────────────────────────────────────────────

it('puts an exact title match above a body match', function () {
    searchablePost(['title' => 'Hashtags', 'content_text' => 'Short.']);
    searchablePost(['title' => 'A very long guide to social media in general', 'content_text' => 'It mentions hashtags in passing.']);

    $titles = $this->getJson('/api/v1/search?q=hashtags')->assertOk()->json('data.*.title');

    expect($titles[0])->toBe('Hashtags');
});

it('puts a title prefix match above a mid-title match', function () {
    searchablePost(['title' => 'The complete YouTube playbook']);
    searchablePost(['title' => 'YouTube playbook']);

    $titles = $this->getJson('/api/v1/search?q=youtube')->assertOk()->json('data.*.title');

    expect(array_slice($titles, 0, 2))->toBe(['YouTube playbook', 'The complete YouTube playbook']);
});

it('matches every word of a query against a title, in any order', function () {
    searchablePost(['title' => 'YouTube Money Calculator']);

    $titles = $this->getJson('/api/v1/search?q=calculator youtube')->assertOk()->json('data.*.title');

    expect($titles)->toContain('YouTube Money Calculator');
});

// ── Contract ─────────────────────────────────────────────────────────────────

it('paginates ten to a page by default and reports the true total', function () {
    foreach (range(1, 14) as $index) {
        searchablePost(['title' => "Instagram note {$index}"]);
    }

    $first = $this->getJson('/api/v1/search?q=instagram note')->assertOk();

    expect($first->json('data'))->toHaveCount(10)
        ->and($first->json('meta.page.total'))->toBe(14)
        ->and($first->json('meta.page.last_page'))->toBe(2);

    expect($this->getJson('/api/v1/search?q=instagram note&page=2')->assertOk()->json('data'))
        ->toHaveCount(4);
});

it('caps the suggestion request at what the dropdown asked for', function () {
    foreach (range(1, 8) as $index) {
        searchablePost(['title' => "Instagram note {$index}"]);
    }

    $response = $this->getJson('/api/v1/search?q=instagram note&per_page=5')->assertOk();

    expect($response->json('data'))->toHaveCount(5)
        ->and($response->json('meta.page.total'))->toBe(8);
});

it('filters by content type', function () {
    counterTool();
    searchablePost(['title' => 'Word counter notes']);

    $types = $this->getJson('/api/v1/search?q=word counter&filter[type]=tool')
        ->assertOk()
        ->json('data.*.type');

    expect(array_unique($types))->toBe(['tool']);
});

it('returns nothing for a term too short to be meaningful', function () {
    counterTool();

    expect($this->getJson('/api/v1/search?q=w')->assertOk()->json('data'))->toBe([])
        ->and($this->getJson('/api/v1/search')->assertOk()->json('data'))->toBe([]);
});

it('treats LIKE wildcards in the query as literal characters', function () {
    counterTool();

    expect($this->getJson('/api/v1/search?q=%')->assertOk()->json('data'))->toBe([]);
});

it('rejects a query longer than the endpoint accepts', function () {
    $this->getJson('/api/v1/search?q='.str_repeat('a', 200))->assertStatus(422);
});

// ── The static page catalog ──────────────────────────────────────────────────

it('lists only pages whose feature is switched on', function () {
    $setting = Setting::query()->firstOrNew(['key' => 'features.changelog_enabled']);
    $setting->fill(['type' => 'bool', 'group' => 'features', 'is_public' => true]);
    $setting->setTypedValue(false);
    $setting->save();
    app(Settings::class)->flush();

    $paths = array_map(
        fn ($page) => $page->url,
        app(SitePageCatalog::class)->all(),
    );

    expect($paths)->not->toContain('/changelog')->and($paths)->toContain('/privacy');
});

it('gives every catalog page a body to match on', function () {
    $haystacks = app(SitePageCatalog::class)->haystacks();

    foreach (app(SitePageCatalog::class)->all() as $page) {
        expect($haystacks[$page->url] ?? '')->not->toBe('', "{$page->url} has no keywords");
    }
});

// ── What is safe to cache ────────────────────────────────────────────────────

it('caches only values this application is willing to unserialize', function () {
    counterTool();

    // `cache.serializable_classes` is `false` here, so the Redis store unserializes
    // with `allowed_classes: false` and every object comes back as
    // `__PHP_Incomplete_Class`. The test suite runs on the array store, which never
    // serializes at all — so nothing else in this file can catch a service that
    // caches an object, and the failure only appears in production, on the *second*
    // request for a term. This asserts the production rule against the real payload.
    $repository = new class(new ArrayStore) extends Repository
    {
        /** @var array<string, mixed> */
        public array $written = [];

        public function put($key, $value, $ttl = null): bool
        {
            $this->written[$key] = $value;

            return parent::put($key, $value, $ttl);
        }
    };

    $service = new SearchService($repository, app(SitePageCatalog::class), app(Settings::class));

    expect($service->search('word counter'))->not->toBeEmpty();
    expect($repository->written)->not->toBeEmpty();

    foreach ($repository->written as $key => $value) {
        expect(unserialize(serialize($value), ['allowed_classes' => false]))
            ->toEqual($value, "Cached value at [{$key}] contains an object");
    }
});

it('round-trips a result through its cached form without losing a field', function () {
    $result = new SearchResult(
        type: SearchResultType::Tool,
        id: 'tl_1',
        title: 'Word Counter',
        url: '/tools/word-counter',
        summary: 'Counts words.',
        image: 'https://example.test/og.png',
        score: 812,
        context: 'Utilities',
    );

    expect(SearchResult::fromArray($result->toArray()))->toEqual($result);
});

it('keeps a result with no summary, image or context null rather than empty', function () {
    $result = new SearchResult(
        type: SearchResultType::Page,
        id: 'page:/terms',
        title: 'Terms of Service',
        url: '/terms',
        summary: null,
        image: null,
        score: 700,
        context: null,
    );

    expect(SearchResult::fromArray($result->toArray()))->toEqual($result);
});
