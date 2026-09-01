<?php

declare(strict_types=1);

use App\Domain\Seo\Models\SeoMeta;
use App\Domain\Seo\Services\ToolSeoDefaults;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;

/**
 * Tool pages carry the organic traffic and convert the paywall, so "the admin left
 * it blank" has to produce publishable metadata rather than nothing. These assert
 * the guarantee the frontend relies on: every field, on every tool, always.
 */
it('gives every tool a complete SEO payload even with nothing stored', function () {
    $tool = counterTool();

    $seo = $this->getJson("/api/v1/catalog/tools/{$tool->slug}")
        ->assertOk()
        ->json('data.seo');

    expect($seo['title'])->not->toBeEmpty()
        ->and($seo['description'])->not->toBeEmpty()
        ->and($seo['og_title'])->not->toBeEmpty()
        ->and($seo['og_description'])->not->toBeEmpty()
        ->and($seo['focus_keyword'])->toBe('word counter')
        ->and($seo['twitter_card'])->toBe('summary_large_image')
        ->and($seo['schema_type'])->toBe('SoftwareApplication')
        ->and($seo['robots'])->toBe('index,follow');
});

it('lets a stored override win field by field without wiping the rest', function () {
    $tool = counterTool();

    SeoMeta::query()->create([
        'seoable_type' => Tool::class,
        'seoable_id' => $tool->id,
        'title' => 'A title somebody actually wrote',
        // Everything else left alone, including an explicitly blank description.
        'description' => '',
    ]);

    $seo = $this->getJson("/api/v1/catalog/tools/{$tool->slug}")->assertOk()->json('data.seo');

    expect($seo['title'])->toBe('A title somebody actually wrote')
        // A cleared input means "use the default", not "publish an empty string".
        ->and($seo['description'])->not->toBeEmpty()
        ->and($seo['og_title'])->not->toBeEmpty();
});

it('never promises a premium tool is free', function () {
    $defaults = app(ToolSeoDefaults::class);

    $free = $defaults->for(counterTool(ToolTier::Free));
    $premium = $defaults->for(counterTool(ToolTier::Premium, key: 'fixture.premium.'.uniqid()));

    expect($free['title'])->toContain('Free')
        ->and($premium['title'])->not->toContain('Free')
        ->and($premium['description'])->not->toContain('Free');
});

it('keeps titles and descriptions inside the lengths search results actually show', function () {
    $tool = counterTool();
    $tool->update([
        // Long enough that the tier qualifier will not fit after it.
        'name' => 'Social Media Caption Length And Readability Analyzer',
        'tagline' => str_repeat('An overlong promise that runs past any snippet. ', 4),
    ]);

    $defaults = app(ToolSeoDefaults::class)->for($tool->refresh());

    expect(mb_strlen($defaults['title']))->toBeLessThanOrEqual(60)
        // The qualifier is dropped rather than cut, so the title is the name alone.
        ->and($defaults['title'])->toBe($tool->name)
        ->and(mb_strlen($defaults['description']))->toBeLessThanOrEqual(155)
        ->and(mb_strlen($defaults['og_title']))->toBeLessThanOrEqual(70)
        ->and(mb_strlen($defaults['og_description']))->toBeLessThanOrEqual(200);
});

it('names the platform in the title only when the tool serves exactly one', function () {
    $single = counterTool();
    $single->update(['name' => 'Thumbnail Downloader', 'platforms' => ['youtube']]);

    $many = counterTool(key: 'fixture.multi.'.uniqid());
    $many->update(['name' => 'Caption Counter', 'platforms' => ['youtube', 'tiktok']]);

    $defaults = app(ToolSeoDefaults::class);

    expect($defaults->for($single->refresh())['title'])->toContain('YouTube')
        // A cross-platform tool promising one platform is a mismatch, and a bounce.
        ->and($defaults->for($many->refresh())['title'])->not->toContain('YouTube');
});
