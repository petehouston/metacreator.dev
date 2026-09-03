<?php

declare(strict_types=1);

use App\Domain\Media\Models\Media;
use App\Domain\Seo\Models\SeoMeta;
use App\Domain\Tools\Models\Tool;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The generated open-graph card.
 *
 * What matters here is not how the picture looks — that is a judgement call made in
 * `marketing/tools/seo/tool-images` — but that running the command leaves the tool
 * with an image an admin can see and a crawler can fetch: right dimensions, small
 * enough to survive a scraper's patience, described for accessibility, and never
 * silently on top of a choice somebody made by hand.
 */
beforeEach(function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
});

it('draws a 1200x630 card and attaches it to the tool', function () {
    $tool = Tool::factory()->create(['slug' => 'card-test-tool', 'name' => 'Card Test Tool']);

    $this->artisan('tools:social-cards', ['slug' => ['card-test-tool']])
        ->assertSuccessful();

    $tool->refresh()->load('seo.ogMedia');
    $media = $tool->seo?->ogMedia;

    expect($media)->not->toBeNull()
        ->and($media->width)->toBe(1200)
        ->and($media->height)->toBe(630)
        ->and($media->mime_type)->toBe('image/png')
        ->and($media->alt_text)->toContain('Card Test Tool')
        // A crawler on a budget gives up on a slow image, and a first fetch that
        // times out is cached as "no image" for days.
        ->and($media->size)->toBeLessThan(300 * 1024)
        ->and($tool->seo->twitter_card)->toBe('summary_large_image');

    Storage::disk('local')->assertExists('media/og/tools/card-test-tool.png');

    // The bytes on the disk really are a 1200 × 630 image, not just a row claiming so.
    $bytes = Storage::disk('local')->get('media/og/tools/card-test-tool.png');
    expect(getimagesizefromstring($bytes))->toMatchArray([0 => 1200, 1 => 630]);
});

it('leaves an already generated card alone unless forced', function () {
    $tool = Tool::factory()->create(['slug' => 'idempotent-tool']);

    $this->artisan('tools:social-cards', ['slug' => ['idempotent-tool']])->assertSuccessful();
    $first = $tool->fresh()->seo->og_media_id;

    $this->artisan('tools:social-cards', ['slug' => ['idempotent-tool']])
        ->expectsOutputToContain('skipped')
        ->assertSuccessful();

    $this->artisan('tools:social-cards', ['slug' => ['idempotent-tool'], '--force' => true])
        ->assertSuccessful();

    // Redrawing replaces the file in place rather than piling up a media row per run.
    expect($tool->fresh()->seo->og_media_id)->toBe($first)
        ->and(Media::query()->where('path', 'media/og/tools/idempotent-tool.png')->count())->toBe(1);
});

it('never overwrites an image an admin picked by hand', function () {
    $tool = Tool::factory()->create(['slug' => 'hand-picked-tool']);

    $chosen = Media::query()->create([
        'ulid' => strtoupper((string) Str::ulid()),
        'disk' => 'local',
        'path' => 'media/2026/01/somebody-else.png',
        'filename' => 'somebody-else.png',
        'mime_type' => 'image/png',
        'size' => 1024,
        'checksum' => str_repeat('a', 64),
    ]);

    SeoMeta::query()->create([
        'seoable_type' => Tool::class,
        'seoable_id' => $tool->id,
        'og_media_id' => $chosen->id,
    ]);

    $this->artisan('tools:social-cards', ['slug' => ['hand-picked-tool']])
        ->expectsOutputToContain('hand-picked')
        ->assertSuccessful();

    expect($tool->fresh()->seo->og_media_id)->toBe($chosen->id);

    $this->artisan('tools:social-cards', ['slug' => ['hand-picked-tool'], '--force' => true])
        ->assertSuccessful();

    expect($tool->fresh()->seo->og_media_id)->not->toBe($chosen->id);
});

it('writes nothing on a dry run', function () {
    Tool::factory()->create(['slug' => 'dry-run-tool']);

    $this->artisan('tools:social-cards', ['slug' => ['dry-run-tool'], '--dry-run' => true])
        ->assertSuccessful();

    Storage::disk('local')->assertMissing('media/og/tools/dry-run-tool.png');
    expect(SeoMeta::query()->count())->toBe(0);
});

it('refuses to run with neither a slug nor --all', function () {
    $this->artisan('tools:social-cards')->assertFailed();
});
