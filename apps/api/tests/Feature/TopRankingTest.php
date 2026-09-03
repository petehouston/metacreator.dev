<?php

declare(strict_types=1);

use App\Domain\TopRanking\Actions\ReorderRankingEntries;
use App\Domain\TopRanking\Actions\SyncRankingPageFromWikipedia;
use App\Domain\TopRanking\Enums\AvatarStatus;
use App\Domain\TopRanking\Enums\EntrySource;
use App\Domain\TopRanking\Enums\RankingPlatform;
use App\Domain\TopRanking\Enums\SyncStatus;
use App\Domain\TopRanking\Models\TopRankingEntry;
use App\Domain\TopRanking\Models\TopRankingPage;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * The top-ranking pages: what a visitor sees, and what a weekly unattended sync is
 * allowed to do to an editor's work.
 *
 * The reconciliation rules carry most of the weight here. A sync that runs every
 * Sunday with nobody watching is only safe if "I added this row by hand" and "do not
 * move this one" survive it — and those are exactly the guarantees that a
 * delete-and-reimport would quietly break while every test about visibility kept
 * passing.
 */
function rankingPage(array $attributes = []): TopRankingPage
{
    return TopRankingPage::query()->create([
        'slug' => 'ranking-'.uniqid(),
        'platform' => RankingPlatform::Instagram,
        'title' => 'Top accounts',
        'metric_label' => 'Followers',
        'metric_unit' => 'millions',
        'source_page' => 'List of most-followed Instagram accounts',
        'source_table' => 0,
        'row_limit' => 50,
        'is_published' => true,
        ...$attributes,
    ]);
}

function rankingEntry(TopRankingPage $page, array $attributes = []): TopRankingEntry
{
    $name = $attributes['name'] ?? 'someone';
    $handle = $attributes['handle'] ?? $name;

    return $page->entries()->create([
        'name' => $name,
        'handle' => $handle,
        'sort_order' => $attributes['sort_order'] ?? ($page->entries()->max('sort_order') + 1),
        'metric_value' => $attributes['metric_value'] ?? 100,
        'match_key' => TopRankingEntry::matchKeyFor($handle, $name),
        'source' => $attributes['source'] ?? EntrySource::Wikipedia,
        'is_pinned' => $attributes['is_pinned'] ?? false,
        'avatar_url' => $attributes['avatar_url'] ?? null,
        'avatar_status' => $attributes['avatar_status'] ?? AvatarStatus::Pending,
        'profile_url' => $attributes['profile_url'] ?? 'https://www.instagram.com/'.$handle,
    ]);
}

/** An article whose ranking table lists exactly these handles, in this order. */
function fakeArticle(array $handles): void
{
    $rows = collect($handles)
        ->map(fn (string $handle, int $index) => sprintf(
            '<tr><td><span class="plainlinks"><a rel="nofollow" class="external text" href="https://www.instagram.com/%s">@%s</a></span></td><td>Owner of %s</td><td>%d</td></tr>',
            $handle,
            $handle,
            $handle,
            100 - $index,
        ))
        ->implode('');

    $html = '<div class="mw-parser-output"><table class="wikitable sortable">'
        .'<tr><th>Username</th><th>Owner</th><th>Followers<br/>(millions)</th></tr>'
        .$rows.'</table></div>';

    // Faked at the HTTP layer rather than by swapping the client, so the client's
    // own error handling — Wikipedia answers 200 with an `error` object for a
    // missing page — stays under test instead of being stubbed past.
    Http::fake([
        'en.wikipedia.org/*' => Http::response(['parse' => ['text' => $html]]),
    ]);
}

function syncPage(TopRankingPage $page): void
{
    app(SyncRankingPageFromWikipedia::class)->handle($page);
}

// ── Public surface ───────────────────────────────────────────────────────────

it('lists published rankings for the header menu', function () {
    $shown = rankingPage(['title' => 'Shown', 'sort_order' => 1]);
    rankingEntry($shown);
    rankingPage(['title' => 'Hidden', 'is_published' => false, 'sort_order' => 2]);

    $response = $this->getJson('/api/v1/top-ranking')->assertOk();

    expect($response->json('data.*.title'))->toBe(['Shown']);
});

it('serves a page with its rows in the order they are arranged', function () {
    $page = rankingPage();
    rankingEntry($page, ['name' => 'second', 'sort_order' => 2]);
    rankingEntry($page, ['name' => 'first', 'sort_order' => 1]);

    $response = $this->getJson("/api/v1/top-ranking/{$page->slug}")->assertOk();

    expect($response->json('data.entries.*.name'))->toBe(['first', 'second'])
        ->and($response->json('data.entries.0.rank'))->toBe(1);
});

it('404s an unpublished page rather than serving it unlisted', function () {
    $page = rankingPage(['is_published' => false]);
    rankingEntry($page);

    $this->getJson("/api/v1/top-ranking/{$page->slug}")->assertNotFound();
});

it('404s a page that has never synced, instead of showing an empty table', function () {
    // A confident heading over nothing reads as a broken product, not as pending work.
    $page = rankingPage();

    $this->getJson("/api/v1/top-ranking/{$page->slug}")->assertNotFound();
});

it('withholds an avatar link whose signature has expired', function () {
    // Meta and TikTok sign their CDN URLs. Handing a dead link to the browser draws
    // a torn-image icon; withholding it draws the monogram, which looks deliberate.
    $page = rankingPage();
    rankingEntry($page, [
        'avatar_url' => 'https://scontent.cdninstagram.com/a.jpg',
        'avatar_status' => AvatarStatus::Ok,
    ])->update(['avatar_expires_at' => now()->subDay()]);

    $response = $this->getJson("/api/v1/top-ranking/{$page->slug}")->assertOk();

    expect($response->json('data.entries.0.avatar_url'))->toBeNull()
        ->and($response->json('data.entries.0.initials'))->not->toBeEmpty();
});

// ── Sync reconciliation ──────────────────────────────────────────────────────

it('imports the article and numbers the rows from one', function () {
    $page = rankingPage(['row_limit' => 3]);
    fakeArticle(['alpha', 'bravo', 'charlie']);

    syncPage($page);

    expect($page->entries()->pluck('handle')->all())->toBe(['alpha', 'bravo', 'charlie'])
        ->and($page->entries()->pluck('sort_order')->all())->toBe([1, 2, 3])
        ->and($page->fresh()->sync_status)->toBe(SyncStatus::Ok);
});

it('updates a matched row without touching its avatar', function () {
    // The expensive part of this feature is the pictures. A sync that discarded them
    // every week would spend fifty HTTP requests per page to relearn what it knew.
    $page = rankingPage();
    $entry = rankingEntry($page, [
        'handle' => 'alpha',
        'metric_value' => 1,
        'avatar_url' => 'https://cdn.example.com/alpha.jpg',
        'avatar_status' => AvatarStatus::Ok,
    ]);

    fakeArticle(['alpha']);
    syncPage($page);

    expect($entry->fresh()->metric_value)->toEqual(100)
        ->and($entry->fresh()->avatar_url)->toBe('https://cdn.example.com/alpha.jpg')
        ->and($entry->fresh()->avatar_status)->toBe(AvatarStatus::Ok);
});

it('drops an imported row the article no longer lists', function () {
    $page = rankingPage();
    rankingEntry($page, ['handle' => 'gone']);

    fakeArticle(['alpha']);
    syncPage($page);

    expect($page->entries()->pluck('handle')->all())->toBe(['alpha']);
});

it('never removes a row an editor added by hand', function () {
    $page = rankingPage();
    rankingEntry($page, ['handle' => 'handmade', 'source' => EntrySource::Manual]);

    fakeArticle(['alpha', 'bravo']);
    syncPage($page);

    // Kept, and placed after the ranked rows rather than silently at the top.
    expect($page->entries()->pluck('handle')->all())->toBe(['alpha', 'bravo', 'handmade']);
});

it('leaves a pinned row exactly where it was put', function () {
    // The release valve that makes an unattended weekly job safe on a curated page.
    $page = rankingPage(['row_limit' => 3]);
    rankingEntry($page, ['handle' => 'bravo', 'sort_order' => 1, 'is_pinned' => true]);

    fakeArticle(['alpha', 'bravo', 'charlie']);
    syncPage($page);

    expect($page->entries()->pluck('handle')->all())->toBe(['bravo', 'alpha', 'charlie']);
});

it('keeps a pinned row the article has dropped', function () {
    $page = rankingPage();
    rankingEntry($page, ['handle' => 'veteran', 'sort_order' => 1, 'is_pinned' => true]);

    fakeArticle(['alpha']);
    syncPage($page);

    expect($page->entries()->pluck('handle')->all())->toContain('veteran');
});

it('forgets an avatar when the account moves to a different URL', function () {
    // A changed profile URL means the stored picture is a picture of something else.
    $page = rankingPage();
    $entry = rankingEntry($page, [
        'handle' => 'alpha',
        'profile_url' => 'https://www.instagram.com/old-address',
        'avatar_url' => 'https://cdn.example.com/alpha.jpg',
        'avatar_status' => AvatarStatus::Ok,
    ]);

    fakeArticle(['alpha']);
    syncPage($page);

    expect($entry->fresh()->avatar_url)->toBeNull()
        ->and($entry->fresh()->avatar_status)->toBe(AvatarStatus::Pending);
});

it('records the failure and does not move the synced-at stamp', function () {
    // A failed run that refreshed the timestamp would report the page as current
    // while serving a year-old ranking — the one lie this feature must not tell.
    $page = rankingPage(['synced_at' => now()->subWeek()]);
    $stamp = $page->synced_at;

    // Exactly what the action API returns for an article that has been renamed:
    // HTTP 200, with the failure in the body.
    Http::fake([
        'en.wikipedia.org/*' => Http::response(['error' => ['code' => 'missingtitle']]),
    ]);

    syncPage($page);

    expect($page->fresh()->sync_status)->toBe(SyncStatus::Failed)
        ->and($page->fresh()->sync_message)->toContain('no Wikipedia article')
        ->and($page->fresh()->synced_at->timestamp)->toBe($stamp->timestamp);
});

it('is idempotent', function () {
    $page = rankingPage(['row_limit' => 2]);
    fakeArticle(['alpha', 'bravo']);

    syncPage($page);
    syncPage($page);

    expect($page->entries()->count())->toBe(2);
});

// ── Reordering ───────────────────────────────────────────────────────────────

it('applies an arrangement and appends anything the payload did not mention', function () {
    $page = rankingPage();
    $a = rankingEntry($page, ['name' => 'a', 'sort_order' => 1]);
    $b = rankingEntry($page, ['name' => 'b', 'sort_order' => 2]);
    $c = rankingEntry($page, ['name' => 'c', 'sort_order' => 3]);

    app(ReorderRankingEntries::class)->handle($page, [$c->id, $a->id]);

    expect($page->entries()->pluck('name')->all())->toBe(['c', 'a', 'b'])
        ->and($page->entries()->pluck('sort_order')->all())->toBe([1, 2, 3]);
});

// ── Admin access ─────────────────────────────────────────────────────────────

it('requires a permission for every admin ranking route', function () {
    $page = rankingPage();
    $entry = rankingEntry($page);
    $nobody = User::factory()->create();

    $this->actingAs($nobody)->getJson('/api/v1/admin/top-rankings')->assertForbidden();
    $this->actingAs($nobody)->postJson("/api/v1/admin/top-rankings/{$page->public_id}/sync")->assertForbidden();
    $this->actingAs($nobody)
        ->deleteJson("/api/v1/admin/top-rankings/{$page->public_id}/entries/{$entry->id}")
        ->assertForbidden();
});

it('lets an editor manage rows but not make the server crawl the web', function () {
    // `sync` is deliberately not `update`: reaching out to Wikipedia and seven social
    // platforms on demand is a different authority from fixing a misspelled name.
    $page = rankingPage();
    $editor = staff('editor-restricted');

    $this->actingAs($editor)->getJson('/api/v1/admin/top-rankings')->assertOk();
    $this->actingAs($editor)
        ->postJson("/api/v1/admin/top-rankings/{$page->public_id}/entries", ['name' => 'By hand'])
        ->assertCreated();
    $this->actingAs($editor)
        ->postJson("/api/v1/admin/top-rankings/{$page->public_id}/sync")
        ->assertForbidden();
});

it('marks a hand-added row as manual so the next sync spares it', function () {
    $page = rankingPage();

    $this->actingAs(staff('editor'))
        ->postJson("/api/v1/admin/top-rankings/{$page->public_id}/entries", [
            'name' => 'Added by hand',
            'handle' => '@byhand',
        ])
        ->assertCreated()
        ->assertJsonPath('data.source', 'manual');

    // The leading @ is stripped, so the row's key matches what the article writes.
    expect($page->entries()->first()->handle)->toBe('byhand');
});

it('refuses a page whose rows would come from an http avatar', function () {
    // An http image on an https page is blocked by the browser, so storing one
    // produces a row that silently shows nothing.
    $page = rankingPage();
    $entry = rankingEntry($page);

    $this->actingAs(staff('editor'))
        ->patchJson("/api/v1/admin/top-rankings/{$page->public_id}/entries/{$entry->id}", [
            'avatar_url' => 'http://insecure.example.com/a.jpg',
        ])
        ->assertStatus(422);
});

it('trusts a pasted avatar immediately rather than sending the editor to a second button', function () {
    $page = rankingPage();
    $entry = rankingEntry($page);

    $this->actingAs(staff('editor'))
        ->patchJson("/api/v1/admin/top-rankings/{$page->public_id}/entries/{$entry->id}", [
            'avatar_url' => 'https://cdn.example.com/a.jpg',
        ])
        ->assertOk()
        ->assertJsonPath('data.avatar_status', 'ok')
        ->assertJsonPath('data.avatar_source', 'manual');
});

it('will not let one page address another page\'s row', function () {
    $page = rankingPage();
    $other = rankingPage();
    $entry = rankingEntry($other);

    $this->actingAs(staff('editor'))
        ->deleteJson("/api/v1/admin/top-rankings/{$page->public_id}/entries/{$entry->id}")
        ->assertNotFound();
});

it('reports a run that found far fewer rows than the page asks for as partial', function () {
    // The failure that actually happens is not an outage — it is an article that
    // gained a column, so half the rows stopped parsing. A run like that must not
    // report success, and must not report failure either: the rows it got are real.
    $page = rankingPage(['row_limit' => 50]);
    fakeArticle(['alpha', 'bravo']);

    syncPage($page);

    expect($page->fresh()->sync_status)->toBe(SyncStatus::Partial)
        ->and($page->fresh()->sync_message)->toContain('2 of the 50');
});

// ── SEO ──────────────────────────────────────────────────────────────────────

it('stores SEO overrides in the shared seo_meta row, not on the page', function () {
    // The same polymorphic row every other entity uses. Two half-systems for one
    // job is how a share preview ends up right on articles and a grey box here.
    $page = rankingPage(['title' => 'Top accounts', 'intro' => 'The biggest ones.']);

    $this->actingAs(staff('editor'))
        ->patchJson("/api/v1/admin/top-rankings/{$page->public_id}", [
            'seo' => [
                'title' => 'A hand-written title',
                'focus_keyword' => 'most followed accounts',
                'robots' => 'noindex,follow',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.seo.title', 'A hand-written title')
        ->assertJsonPath('data.seo.robots', 'noindex,follow');

    expect($page->fresh()->seo)->not->toBeNull()
        ->and($page->fresh()->seo->focus_keyword)->toBe('most followed accounts');
});

it('falls back to the page title and intro when nothing is stored', function () {
    // Resolved server-side so `generateMetadata` reads one value per field rather
    // than re-deriving a fallback chain the API also has an opinion about.
    $page = rankingPage(['title' => 'Top accounts', 'intro' => 'The biggest ones.']);
    rankingEntry($page);

    $this->getJson("/api/v1/top-ranking/{$page->slug}")
        ->assertOk()
        ->assertJsonPath('data.seo.title', 'Top accounts')
        ->assertJsonPath('data.seo.description', 'The biggest ones.')
        ->assertJsonPath('data.seo.robots', 'index,follow')
        // The keys the frontend tests for must exist even when nothing is stored,
        // or `seo?.og_image_url` is undefined and the site-wide card is never used.
        ->assertJsonPath('data.seo.og_image_url', null);
});

it('does not turn a cleared SEO field into an empty string', function () {
    // An empty string stored is how every `?? fallback` downstream stops firing,
    // and the page starts publishing a blank meta title.
    $page = rankingPage(['title' => 'Top accounts']);
    rankingEntry($page);

    $this->actingAs(staff('editor'))
        ->patchJson("/api/v1/admin/top-rankings/{$page->public_id}", ['seo' => ['title' => '']])
        ->assertOk();

    expect($page->fresh()->seo->title)->toBeNull();

    $this->getJson("/api/v1/top-ranking/{$page->slug}")
        ->assertOk()
        ->assertJsonPath('data.seo.title', 'Top accounts');
});

it('expires the public page when only its SEO row changes', function () {
    // The SEO row is a sibling: writing it does not touch the page, so its observer
    // never fires and the cached page would keep serving the old meta tags.
    $page = rankingPage();
    $before = $page->updated_at;

    $this->travel(1)->minute();

    $this->actingAs(staff('editor'))
        ->patchJson("/api/v1/admin/top-rankings/{$page->public_id}", ['seo' => ['title' => 'New']])
        ->assertOk();

    expect($page->fresh()->updated_at->greaterThan($before))->toBeTrue();
});
