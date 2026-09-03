<?php

declare(strict_types=1);

use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolCategory;
use Database\Seeders\ToolCatalogSeeder;
use Database\Seeders\ToolCategorySeeder;

/**
 * The rule every deploy depends on: seeding is not allowed to undo an admin.
 *
 * `ProductionSeeder` runs on every deploy, and before this it reassigned every
 * column of every row it knew about — so the catalog file quietly reverted work
 * done in the console, and the deploy looked like it had wiped production. These
 * tests are the guard on the other behaviour: new rows still arrive, untouched
 * fields still track the file, and only what somebody actually changed is frozen.
 */
it('keeps a field an admin edited when the catalog is re-seeded', function () {
    $this->seed(ToolCategorySeeder::class);
    $this->seed(ToolCatalogSeeder::class);

    $tool = Tool::query()->where('key', 'youtube.thumbnail-downloader')->firstOrFail();
    $seededTagline = $tool->tagline;

    // An admin rewrites the tagline and nothing else.
    $tool->update(['tagline' => 'A line somebody actually wrote.']);

    $this->seed(ToolCatalogSeeder::class);

    $tool->refresh();

    expect($tool->tagline)->toBe('A line somebody actually wrote.')
        ->and($tool->lockedFields())->toContain('tagline')
        ->and($seededTagline)->not->toBe($tool->tagline)
        // Everything they did not touch still tracks the file.
        ->and($tool->name)->toBe('YouTube Thumbnail Downloader');
});

it('still updates the fields nobody has claimed', function () {
    $this->seed(ToolCategorySeeder::class);
    $this->seed(ToolCatalogSeeder::class);

    $tool = Tool::query()->where('key', 'youtube.thumbnail-downloader')->firstOrFail();
    $tool->update(['tagline' => 'Mine now.']);

    // Something the code owns drifts — the runner's schema, say — and the row is
    // wrong until the next seed puts it back.
    $tool->forceFill(['name' => 'Renamed by hand in the database', 'input_schema' => []])->saveQuietly();

    $this->seed(ToolCatalogSeeder::class);

    expect($tool->fresh()->name)->toBe('YouTube Thumbnail Downloader')
        ->and($tool->fresh()->input_schema)->not->toBe([]);
});

it('never locks a column the application maintains for itself', function () {
    $tool = counterTool();

    // A run finishing writes counters. That is not an editorial decision, and
    // freezing the seeder out of those columns would be nonsense.
    $tool->update(['run_count' => 42, 'avg_duration_ms' => 12]);

    expect($tool->fresh()->lockedFields())->toBe([]);
});

it('lets an admin edit through the API take ownership of exactly what they changed', function () {
    $tool = toolFixture(ToolTier::Free);

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", ['tagline' => 'Edited in the console'])
        ->assertOk();

    expect($tool->fresh()->lockedFields())->toBe(['tagline']);
});

it('seeds a category without resetting one an admin has renamed', function () {
    $this->seed(ToolCategorySeeder::class);

    $category = ToolCategory::query()->where('slug', 'utility')->firstOrFail();
    $category->update(['name' => 'Handy things']);

    $this->seed(ToolCategorySeeder::class);

    expect($category->fresh()->name)->toBe('Handy things');
});
