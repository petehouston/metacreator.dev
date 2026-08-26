<?php

declare(strict_types=1);

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Models\PostCategory;
use App\Domain\Blog\Models\Tag;
use App\Domain\Blog\Services\PostContentService;
use App\Domain\Settings\Setting;
use App\Domain\Settings\Settings;
use App\Domain\Users\Models\User;

/**
 * The public blog's contract: what a visitor may see, and what must never leak.
 *
 * Visibility is the part worth pinning down — a draft that becomes reachable is a
 * story published before its embargo, which is not a recoverable mistake.
 */
function makePost(array $attributes = []): Post
{
    $blocks = $attributes['blocks'] ?? ['blocks' => [
        ['type' => 'paragraph', 'data' => ['html' => 'Hello world.']],
    ]];

    unset($attributes['blocks']);

    $category = PostCategory::query()->firstOrCreate(
        ['slug' => 'testing'],
        ['name' => 'Testing', 'sort_order' => 0],
    );

    return Post::query()->create([
        ...app(PostContentService::class)->derive($blocks),
        'slug' => 'post-'.uniqid(),
        'title' => 'A test post',
        'excerpt' => 'A test excerpt.',
        'category_id' => $category->id,
        'author_id' => User::factory()->create()->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

it('lists only published posts', function () {
    $published = makePost();
    makePost(['status' => PostStatus::Draft, 'published_at' => null]);
    makePost(['status' => PostStatus::Scheduled, 'scheduled_for' => now()->addDay(), 'published_at' => null]);
    makePost(['status' => PostStatus::Archived]);
    makePost(['status' => PostStatus::Unpublished]);

    $response = $this->getJson('/api/v1/blog/posts')->assertOk();

    expect($response->json('meta.page.total'))->toBe(1)
        ->and($response->json('data.0.slug'))->toBe($published->slug);
});

it('hides a published post until its publish date arrives', function () {
    // Back-dating and embargoes both rely on this: `published` alone is not enough.
    $future = makePost(['published_at' => now()->addHour()]);

    $this->getJson('/api/v1/blog/posts')->assertOk()
        ->assertJsonPath('meta.page.total', 0);

    $this->getJson("/api/v1/blog/posts/{$future->slug}")->assertNotFound();
});

it('returns 404 for a draft and 410 for a withdrawn post', function () {
    $draft = makePost(['status' => PostStatus::Draft, 'published_at' => null]);
    $gone = makePost(['status' => PostStatus::Unpublished]);

    // 410 tells a crawler to drop the URL; 404 leaves it re-checking for months.
    $this->getJson("/api/v1/blog/posts/{$draft->slug}")->assertNotFound();
    $this->getJson("/api/v1/blog/posts/{$gone->slug}")->assertStatus(410);
});

it('never exposes the author email address', function () {
    $post = makePost();

    $response = $this->getJson("/api/v1/blog/posts/{$post->slug}")->assertOk();

    expect($response->json('data.author'))->not->toHaveKey('email')
        ->and($response->json())->not->toContain($post->author->email);
});

it('omits the post body from the listing', function () {
    makePost();

    $response = $this->getJson('/api/v1/blog/posts')->assertOk();

    // Twelve full articles to render twelve excerpts is the classic listing mistake.
    expect($response->json('data.0'))->not->toHaveKey('blocks');
});

it('filters by category and tag', function () {
    $tag = Tag::query()->create(['slug' => 'growth', 'name' => 'Growth']);
    $tagged = makePost();
    $tagged->tags()->sync([$tag->id]);
    makePost();

    $this->getJson('/api/v1/blog/posts?filter[tag]=growth')->assertOk()
        ->assertJsonPath('meta.page.total', 1)
        ->assertJsonPath('data.0.slug', $tagged->slug);

    $this->getJson('/api/v1/blog/posts?filter[category]=testing')->assertOk()
        ->assertJsonPath('meta.page.total', 2);
});

it('excludes categories with nothing published in them', function () {
    PostCategory::query()->create(['slug' => 'empty', 'name' => 'Empty']);
    makePost();

    $slugs = collect($this->getJson('/api/v1/blog/categories')->assertOk()->json('data'))
        ->pluck('slug');

    expect($slugs)->toContain('testing')->not->toContain('empty');
});

it('404s every blog route when the blog is disabled', function () {
    Setting::query()->updateOrCreate(
        ['key' => 'features.blog_enabled'],
        ['value' => ['v' => false], 'type' => 'bool', 'group' => 'features', 'is_public' => true],
    );
    app(Settings::class)->flush();

    $post = makePost();

    $this->getJson('/api/v1/blog/posts')->assertNotFound();
    $this->getJson('/api/v1/blog/categories')->assertNotFound();
    $this->getJson("/api/v1/blog/posts/{$post->slug}")->assertNotFound();
});

it('publishes scheduled posts once their time passes', function () {
    $due = makePost([
        'status' => PostStatus::Scheduled,
        'scheduled_for' => now()->subMinute(),
        'published_at' => null,
    ]);
    $notDue = makePost([
        'status' => PostStatus::Scheduled,
        'scheduled_for' => now()->addHour(),
        'published_at' => null,
    ]);

    $this->artisan('blog:publish-scheduled')->assertSuccessful();

    expect($due->fresh()->status)->toBe(PostStatus::Published)
        // The scheduled time is the publication time, not the moment the cron ran.
        ->and($due->fresh()->published_at->timestamp)->toBe($due->scheduled_for->timestamp)
        ->and($due->fresh()->scheduled_for)->toBeNull()
        ->and($notDue->fresh()->status)->toBe(PostStatus::Scheduled);
});

it('ranks related posts by shared tags, then category, then recency', function () {
    $tagA = Tag::query()->create(['slug' => 'a', 'name' => 'A']);
    $other = PostCategory::query()->create(['slug' => 'other', 'name' => 'Other']);

    $subject = makePost();
    $subject->tags()->sync([$tagA->id]);

    $sharesTag = makePost(['category_id' => $other->id, 'published_at' => now()->subYear()]);
    $sharesTag->tags()->sync([$tagA->id]);

    $sameCategoryOnly = makePost(['published_at' => now()->subMonth()]);

    $unrelated = makePost(['category_id' => $other->id, 'published_at' => now()->subDays(2)]);

    $related = $this->getJson("/api/v1/blog/posts/{$subject->slug}")->assertOk()->json('data.related');

    // The shared tag wins despite being the oldest post of the three.
    expect(array_column($related, 'slug'))
        ->toBe([$sharesTag->slug, $sameCategoryOnly->slug, $unrelated->slug]);
});
