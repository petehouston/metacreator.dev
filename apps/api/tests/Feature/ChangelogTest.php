<?php

declare(strict_types=1);

use App\Domain\Changelog\Enums\ChangeType;
use App\Domain\Changelog\Enums\ReleaseStatus;
use App\Domain\Changelog\Models\ChangelogRelease;
use App\Domain\Settings\Setting;
use App\Domain\Settings\Settings;
use App\Domain\Users\Models\User;

/**
 * The changelog's contract: what a visitor may see, and who may change it.
 *
 * Visibility carries most of the weight here for the same reason it does on the
 * blog — an embargoed release note that becomes reachable has announced something
 * before it shipped, and that cannot be taken back.
 */
function makeRelease(array $attributes = [], array $items = []): ChangelogRelease
{
    $release = ChangelogRelease::query()->create([
        'slug' => 'release-'.uniqid(),
        'version' => '1.0.0',
        'title' => 'A test release',
        'status' => ReleaseStatus::Published,
        'released_at' => now()->subDay(),
        'author_id' => User::factory()->create()->id,
        ...$attributes,
    ]);

    foreach ($items ?: [['type' => ChangeType::Added, 'title' => 'A new thing']] as $index => $item) {
        $release->items()->create([
            'type' => $item['type'],
            'title' => $item['title'],
            'description' => $item['description'] ?? null,
            'sort_order' => $index,
        ]);
    }

    return $release->load('items');
}

// ── Public visibility ────────────────────────────────────────────────────────

it('lists published releases newest first', function () {
    $older = makeRelease(['title' => 'Older', 'released_at' => now()->subMonth()]);
    $newer = makeRelease(['title' => 'Newer', 'released_at' => now()->subDay()]);

    $response = $this->getJson('/api/v1/changelog')->assertOk();

    expect($response->json('data.*.title'))->toBe([$newer->title, $older->title]);
});

it('hides drafts from the public listing', function () {
    makeRelease(['title' => 'Secret', 'status' => ReleaseStatus::Draft, 'released_at' => null]);
    makeRelease(['title' => 'Shipped']);

    $response = $this->getJson('/api/v1/changelog')->assertOk();

    expect($response->json('data.*.title'))->toBe(['Shipped']);
});

it('hides a release dated in the future, whatever its status says', function () {
    // The case that matters: an editor marks something Published and dates it next
    // week. "Published" is intent; the date decides visibility.
    makeRelease([
        'title' => 'Embargoed',
        'status' => ReleaseStatus::Published,
        'released_at' => now()->addWeek(),
    ]);

    $this->getJson('/api/v1/changelog')->assertOk()->assertJsonCount(0, 'data');
});

it('publishes a scheduled release once its date passes, with no job having run', function () {
    $release = makeRelease([
        'title' => 'Scheduled',
        'status' => ReleaseStatus::Scheduled,
        'released_at' => now()->addDay(),
    ]);

    $this->getJson('/api/v1/changelog')->assertJsonCount(0, 'data');

    $this->travel(2)->days();

    $this->getJson('/api/v1/changelog')->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/changelog/{$release->slug}")->assertOk();
});

it('404s a draft release addressed directly by slug', function () {
    $release = makeRelease(['status' => ReleaseStatus::Draft, 'released_at' => null]);

    $this->getJson("/api/v1/changelog/{$release->slug}")->assertNotFound();
});

it('narrows the entries inside a release when filtering by type', function () {
    // A release kept because one entry matched must not then render the ones that
    // did not — otherwise "show me the fixes" shows everything.
    makeRelease([], [
        ['type' => ChangeType::Added, 'title' => 'Something new'],
        ['type' => ChangeType::Security, 'title' => 'Something hardened'],
    ]);

    $response = $this->getJson('/api/v1/changelog?filter[type]=security')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.items.*.title'))->toBe(['Something hardened']);
});

it('searches the entries, not only the release title', function () {
    makeRelease(['title' => 'Housekeeping'], [
        ['type' => ChangeType::Added, 'title' => 'CSV export for every tool'],
    ]);
    makeRelease(['title' => 'Unrelated']);

    $response = $this->getJson('/api/v1/changelog?q=CSV')->assertOk();

    expect($response->json('data.*.title'))->toBe(['Housekeeping']);
});

it('offers only the years that have published releases', function () {
    makeRelease(['released_at' => now()->subDay()]);
    makeRelease(['status' => ReleaseStatus::Draft, 'released_at' => null]);

    $response = $this->getJson('/api/v1/changelog/meta')->assertOk();

    expect($response->json('data.years'))->toHaveCount(1)
        ->and($response->json('data.years.0.year'))->toBe((int) now()->year)
        ->and($response->json('data.types'))->toHaveCount(count(ChangeType::cases()));
});

// ── Admin ────────────────────────────────────────────────────────────────────

it('creates a release with its entries in the submitted order', function () {
    $response = $this->actingAs(staff('editor'))
        ->postJson('/api/v1/admin/changelog', [
            'title' => 'Ship it',
            'version' => '4.2.0',
            'status' => 'published',
            'items' => [
                ['type' => 'fixed', 'title' => 'Second'],
                ['type' => 'added', 'title' => 'First'],
            ],
        ])
        ->assertCreated();

    expect($response->json('data.items.*.title'))->toBe(['Second', 'First'])
        // Derived from the version, not the title: the version is the guessable URL
        // and it stops moving before the wording does.
        ->and($response->json('data.slug'))->toBe('v4-2-0')
        ->and($response->json('data.is_live'))->toBeTrue();
});

it('dates a published release today when no date was given', function () {
    // Without this the row saves, reports success, and is invisible forever.
    $this->actingAs(staff('editor'))
        ->postJson('/api/v1/admin/changelog', [
            'title' => 'Undated',
            'status' => 'published',
            'items' => [['type' => 'added', 'title' => 'A thing']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_live', true);
});

it('refuses to schedule a release without a future date', function () {
    $this->actingAs(staff('editor'))
        ->postJson('/api/v1/admin/changelog', [
            'title' => 'Nowhen',
            'status' => 'scheduled',
            'released_at' => now()->subDay()->toDateString(),
            'items' => [['type' => 'added', 'title' => 'A thing']],
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'error.details.released_at.0',
            'A scheduled release needs a date in the future. Publish it instead to go live now.',
        );
});

it('rejects a release whose entries are all blank', function () {
    $this->actingAs(staff('editor'))
        ->postJson('/api/v1/admin/changelog', [
            'title' => 'Empty',
            'items' => [['type' => 'added', 'title' => '   ']],
        ])
        ->assertStatus(422);
});

it('replaces the entries on update, keeping the new order', function () {
    $release = makeRelease([], [
        ['type' => ChangeType::Added, 'title' => 'Original'],
    ]);

    $response = $this->actingAs(staff('editor'))
        ->patchJson("/api/v1/admin/changelog/{$release->public_id}", [
            'items' => [
                ['type' => 'improved', 'title' => 'Replacement'],
                ['type' => 'fixed', 'title' => 'Another'],
            ],
        ])
        ->assertOk();

    expect($response->json('data.items.*.title'))->toBe(['Replacement', 'Another'])
        ->and($release->fresh()->items()->count())->toBe(2);
});

it('keeps the slug when a release is retitled', function () {
    // Someone has already linked to it.
    $release = makeRelease(['slug' => 'v9-9-9']);

    $this->actingAs(staff('editor'))
        ->patchJson("/api/v1/admin/changelog/{$release->public_id}", ['title' => 'A new name'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'v9-9-9');
});

it('publishes a future-dated release immediately when published by hand', function () {
    $release = makeRelease([
        'status' => ReleaseStatus::Draft,
        'released_at' => now()->addMonth(),
    ]);

    $this->actingAs(staff('editor'))
        ->postJson("/api/v1/admin/changelog/{$release->public_id}/publish")
        ->assertOk()
        ->assertJsonPath('data.is_live', true);

    expect($release->fresh()->released_at->isFuture())->toBeFalse();
});

it('deletes a release and its entries', function () {
    $release = makeRelease();

    $this->actingAs(staff('editor'))
        ->deleteJson("/api/v1/admin/changelog/{$release->public_id}")
        ->assertNoContent();

    expect(ChangelogRelease::query()->whereKey($release->id)->exists())->toBeFalse()
        ->and($release->items()->count())->toBe(0);
});

it('reports status counts that ignore the current filter', function () {
    makeRelease(['status' => ReleaseStatus::Published]);
    makeRelease(['status' => ReleaseStatus::Draft, 'released_at' => null]);

    $response = $this->actingAs(staff('editor'))
        ->getJson('/api/v1/admin/changelog?status=draft')
        ->assertOk();

    // One row comes back, but the tabs still show both — otherwise sitting on
    // Drafts would report "Published 0".
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('meta.counts.all'))->toBe(2)
        ->and($response->json('meta.counts.published'))->toBe(1);
});

// ── Permissions ──────────────────────────────────────────────────────────────

it('lets a restricted editor draft a release but not publish it', function () {
    $editor = staff('editor-restricted');

    $publicId = $this->actingAs($editor)
        ->postJson('/api/v1/admin/changelog', [
            'title' => 'A draft',
            'items' => [['type' => 'added', 'title' => 'A thing']],
        ])
        ->assertCreated()
        ->json('data.id');

    $release = ChangelogRelease::findByPublicId($publicId);

    expect($release->status)->toBe(ReleaseStatus::Draft);

    $this->actingAs($editor)
        ->postJson("/api/v1/admin/changelog/{$release->public_id}/publish")
        ->assertForbidden();

    $this->actingAs($editor)
        ->deleteJson("/api/v1/admin/changelog/{$release->public_id}")
        ->assertForbidden();
});

it('keeps the admin surface away from a signed-out visitor', function () {
    $this->getJson('/api/v1/admin/changelog')->assertUnauthorized();
});

it('separates on the dots in a version rather than dropping them', function () {
    // `Str::slug('4.2.0')` is `420`, which collides with `42.0`. Two releases that
    // sound nothing alike must not fight over one URL.
    $editor = staff('editor');

    $first = $this->actingAs($editor)->postJson('/api/v1/admin/changelog', [
        'title' => 'Four two zero',
        'version' => '4.2.0',
        'items' => [['type' => 'added', 'title' => 'A thing']],
    ])->assertCreated();

    $second = $this->actingAs($editor)->postJson('/api/v1/admin/changelog', [
        'title' => 'Forty-two zero',
        'version' => '42.0',
        'items' => [['type' => 'added', 'title' => 'A thing']],
    ])->assertCreated();

    expect($first->json('data.slug'))->toBe('v4-2-0')
        ->and($second->json('data.slug'))->toBe('v42-0');
});

it('404s every public changelog route when the changelog is disabled', function () {
    Setting::query()->updateOrCreate(
        ['key' => 'features.changelog_enabled'],
        ['value' => ['v' => false], 'type' => 'bool', 'group' => 'features', 'is_public' => true],
    );
    app(Settings::class)->flush();

    $release = makeRelease();

    $this->getJson('/api/v1/changelog')->assertNotFound();
    $this->getJson('/api/v1/changelog/meta')->assertNotFound();
    $this->getJson("/api/v1/changelog/{$release->slug}")->assertNotFound();

    // The admin surface is untouched: releases stay editable while the public
    // timeline is off.
    $this->actingAs(staff('editor'))->getJson('/api/v1/admin/changelog')->assertOk();
});
