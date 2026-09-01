<?php

declare(strict_types=1);

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Settings\Setting;
use App\Domain\Settings\Settings;
use App\Domain\Tools\Enums\QuotaWindow;
use App\Domain\Tools\Models\ToolFavorite;
use App\Domain\Tools\Models\ToolRun;
use App\Domain\Tools\Services\QuotaService;
use App\Domain\Tools\Services\TrendingTools;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Redis;

/**
 * The tier ladder, the saved list and the trending window.
 *
 * These three share a file because they share a premise: what a visitor gets is a
 * function of who they are, and an admin sets the numbers without a deploy.
 */
function setSetting(string $key, mixed $value, string $type = 'int'): void
{
    Setting::query()->updateOrCreate(
        ['key' => $key],
        ['value' => ['v' => $value], 'type' => $type, 'group' => 'tools', 'is_public' => true],
    );

    app(Settings::class)->flush();
}

/**
 * Quota lives in Redis, which — unlike the database — is not rolled back between
 * tests. Clearing the counters is what stops one test's runs from exhausting the
 * next one's allowance.
 */
beforeEach(function (): void {
    foreach (Redis::keys('quota:runs:*') as $key) {
        // Laravel's Redis facade returns keys with the connection prefix already
        // applied, and `del` re-applies it — so it has to come back off first.
        Redis::del(str_replace(config('database.redis.options.prefix', ''), '', (string) $key));
    }
});

// ── Tier limits ──────────────────────────────────────────────────────────────

it('defaults to five runs for anonymous visitors, twenty for accounts, unlimited for paid', function () {
    $entitlements = app(EntitlementService::class);

    expect($entitlements->runsPerDayFor(null))->toBe(5)
        ->and($entitlements->runsPerDayFor(User::factory()->create()))->toBe(20)
        ->and($entitlements->runsPerDayFor(subscriber()))->toBe(EntitlementService::UNLIMITED);
});

it('takes its per-tier limits from settings, so an admin can change them without a deploy', function () {
    setSetting('tools.limits.free.daily', 2);
    setSetting('tools.limits.account.daily', 3);
    setSetting('tools.limits.premium.daily', 99);

    $entitlements = app(EntitlementService::class);

    expect($entitlements->runsPerDayFor(null))->toBe(2)
        ->and($entitlements->runsPerDayFor(User::factory()->create()))->toBe(3)
        ->and($entitlements->runsPerDayFor(subscriber()))->toBe(99);
});

it('stops an anonymous visitor at the configured limit and says how to get past it', function () {
    setSetting('tools.limits.free.daily', 2);
    $tool = counterTool();

    // Distinct text per run: the word counter is cacheable, and a cache hit
    // deliberately costs no quota — which would make this test measure nothing.
    foreach (range(1, 2) as $n) {
        $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => "run number {$n}"]])
            ->assertOk();
    }

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'run number 3']])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'tool.quota_exceeded')
        ->assertJsonPath('error.details.limit', 2)
        // The two ways out: move up a tier, or wait.
        ->assertJsonPath('error.details.next_tier', 'account')
        ->assertJsonPath('error.details.upgrade_action', 'register')
        ->assertJsonPath('error.details.next_tier_limit', 20)
        ->assertJsonStructure(['error' => ['details' => ['resets_at']]]);
});

it('points an exhausted member at a subscription rather than at another account', function () {
    setSetting('tools.limits.account.daily', 1);
    $tool = counterTool();

    $this->actingAs(User::factory()->create());

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'first']])->assertOk();

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'second']])
        ->assertStatus(429)
        ->assertJsonPath('error.details.next_tier', 'premium')
        ->assertJsonPath('error.details.upgrade_action', 'subscribe')
        // Premium is unlimited by default, so there is no number to promise.
        ->assertJsonPath('error.details.next_tier_limit', null)
        ->assertJsonPath('error.details.next_tier_unlimited', true);
});

it('never walls an unlimited actor', function () {
    $tool = counterTool();
    $this->actingAs(subscriber());

    foreach (range(1, 8) as $n) {
        $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => "unlimited {$n}"]])
            ->assertOk();
    }
});

it('reports an unlimited allowance as unlimited rather than as a large number', function () {
    $this->actingAs(subscriber());

    $this->getJson('/api/v1/account/entitlements')
        ->assertOk()
        ->assertJsonPath('data.usage.unlimited', true)
        ->assertJsonPath('data.usage.remaining', null)
        ->assertJsonPath('data.access_tier', 'premium')
        ->assertJsonPath('data.tier_limits.free.daily', 5)
        // Weekly and monthly are off until somebody turns them on: a fresh install
        // should not acquire a ceiling nobody asked for.
        ->assertJsonPath('data.tier_limits.free.monthly', -1);
});

// ── Windows ──────────────────────────────────────────────────────────────────

it('enforces a weekly ceiling even when the day still has runs left', function () {
    setSetting('tools.limits.free.daily', 50);
    setSetting('tools.limits.free.weekly', 2);

    $tool = counterTool();

    foreach (range(1, 2) as $n) {
        $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => "week {$n}"]])
            ->assertOk();
    }

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'week 3']])
        ->assertStatus(429)
        ->assertJsonPath('error.details.window', 'weekly')
        ->assertJsonPath('error.details.limit', 2)
        // The wait is a week away, so the copy must not say "tomorrow" — coming
        // back tomorrow would land straight back on this wall.
        ->assertJsonPath('error.message', "You've used all 2 runs for this week. "
            .'A free account raises the limit — or come back next week.');
});

it('walls on the tightest window, whichever one that is', function () {
    setSetting('tools.limits.free.daily', 1);
    setSetting('tools.limits.free.monthly', 100);

    $tool = counterTool();

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'first']])->assertOk();

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'second']])
        ->assertStatus(429)
        ->assertJsonPath('error.details.window', 'daily');
});

it('does not spend a wider window on a run the narrow one refused', function () {
    setSetting('tools.limits.account.daily', 1);
    setSetting('tools.limits.account.monthly', 10);

    $tool = counterTool();
    $this->actingAs(User::factory()->create());

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'first']])->assertOk();
    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'second']])
        ->assertStatus(429);

    // One run happened, so exactly one may be charged to the month. A refused run
    // that still cost a month's budget would silently shrink the allowance every
    // time somebody hit the daily wall.
    $this->getJson('/api/v1/account/entitlements')
        ->assertOk()
        ->assertJsonPath('data.usage.windows.monthly.used', 1)
        ->assertJsonPath('data.usage.windows.monthly.remaining', 9);
});

it('reports the binding window rather than always the day', function () {
    setSetting('tools.limits.account.daily', -1);
    setSetting('tools.limits.account.monthly', 30);

    $this->actingAs(User::factory()->create());

    $this->getJson('/api/v1/account/entitlements')
        ->assertOk()
        // A meter that read "unlimited" here would be lying: the month is what
        // actually stops the next run.
        ->assertJsonPath('data.usage.window', 'monthly')
        ->assertJsonPath('data.usage.unlimited', false)
        ->assertJsonPath('data.usage.limit', 30)
        ->assertJsonPath('data.usage.windows.daily.unlimited', true);
});

// ── Per-tool caps ────────────────────────────────────────────────────────────

it('lets one tool cap itself below the tier without touching the others', function () {
    setSetting('tools.limits.free.daily', 20);

    $capped = counterTool();
    $capped->update(['config' => ['limits' => ['daily' => 1]]]);

    $open = counterTool(key: 'fixture.open.'.uniqid());

    $this->postJson("/api/v1/tools/{$capped->slug}/run", ['input' => ['text' => 'first']])->assertOk();

    $this->postJson("/api/v1/tools/{$capped->slug}/run", ['input' => ['text' => 'second']])
        ->assertStatus(429)
        ->assertJsonPath('error.details.limit', 1);

    // A cap belongs to the row that declares it. Its neighbour still resolves to
    // the tier's own allowance — a stricter tool must not narrow the catalog.
    expect(app(QuotaService::class)->limitFor(null, QuotaWindow::Daily, $open))->toBe(20)
        ->and(app(QuotaService::class)->limitFor(null, QuotaWindow::Daily, $capped))->toBe(1);
});

it('lets a tool cap a window the global settings leave uncounted', function () {
    setSetting('tools.limits.premium.daily', -1);

    $tool = counterTool();
    $tool->update(['config' => ['limits' => ['monthly' => 1]]]);

    // "Unlimited" is a promise about the plan, not permission to hammer a metered
    // provider — a paid actor still meets the tool's own ceiling.
    $this->actingAs(subscriber());

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'first']])->assertOk();

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'second']])
        ->assertStatus(429)
        ->assertJsonPath('error.details.window', 'monthly')
        ->assertJsonPath('error.details.limit', 1);
});

it('still honours the pre-window runs_per_day key on an untouched catalog row', function () {
    setSetting('tools.limits.free.daily', 20);

    $tool = counterTool();
    $tool->update(['config' => ['runs_per_day' => 1]]);

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'first']])->assertOk();

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'second']])
        ->assertStatus(429)
        ->assertJsonPath('error.details.limit', 1);
});

// ── Favourites ───────────────────────────────────────────────────────────────

it('lets a member save and unsave a tool, idempotently', function () {
    $user = User::factory()->create();
    $tool = counterTool();

    $this->actingAs($user);

    $this->putJson("/api/v1/account/favorites/{$tool->slug}")->assertOk();
    // Twice is the same one row, not a duplicate and not an error.
    $this->putJson("/api/v1/account/favorites/{$tool->slug}")->assertOk();

    expect(ToolFavorite::query()->where('user_id', $user->id)->count())->toBe(1);

    $this->getJson('/api/v1/account/favorites')
        ->assertOk()
        // The payload already names its own `data` key, so it is not wrapped a
        // second time — the envelope matches every other list endpoint.
        ->assertJsonPath('meta.slugs', [$tool->slug]);

    $this->deleteJson("/api/v1/account/favorites/{$tool->slug}")->assertOk();
    $this->deleteJson("/api/v1/account/favorites/{$tool->slug}")->assertOk();

    expect(ToolFavorite::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('refuses favourites to anonymous visitors', function () {
    $tool = counterTool();

    $this->getJson('/api/v1/account/favorites')->assertUnauthorized();
    $this->putJson("/api/v1/account/favorites/{$tool->slug}")->assertUnauthorized();
});

it('hands the catalog the caller’s own saved slugs and nobody else’s', function () {
    $mine = User::factory()->create();
    $tool = counterTool();
    $other = counterTool(key: 'fixture.other.'.uniqid());

    ToolFavorite::query()->create(['user_id' => $mine->id, 'tool_id' => $tool->id]);
    ToolFavorite::query()->create([
        'user_id' => User::factory()->create()->id,
        'tool_id' => $other->id,
    ]);

    $this->getJson('/api/v1/catalog/tools')->assertOk()->assertJsonPath('meta.favorites', []);

    $this->actingAs($mine)
        ->getJson('/api/v1/catalog/tools')
        ->assertOk()
        ->assertJsonPath('meta.favorites', [$tool->slug]);
});

// ── Trending ─────────────────────────────────────────────────────────────────

it('ranks trending by runs inside the configured window and forgets what fell out of it', function () {
    setSetting('tools.trending_days', 3);
    setSetting('tools.trending_min_runs', 1);

    $hot = counterTool();
    $stale = counterTool(key: 'fixture.stale.'.uniqid());

    ToolRun::factory()->count(4)->create(['tool_id' => $hot->id, 'created_at' => now()->subDay()]);
    // Outside a three-day window: lifetime-popular, not trending.
    ToolRun::factory()->count(50)->create(['tool_id' => $stale->id, 'created_at' => now()->subDays(10)]);

    expect(app(TrendingTools::class)->slugs())->toBe([$hot->slug]);
});

it('lets an admin widen the trending window', function () {
    setSetting('tools.trending_days', 30);
    setSetting('tools.trending_min_runs', 1);

    $tool = counterTool();
    ToolRun::factory()->count(2)->create(['tool_id' => $tool->id, 'created_at' => now()->subDays(10)]);

    $this->getJson('/api/v1/catalog/tools/trending')
        ->assertOk()
        ->assertJsonPath('data.days', 30)
        ->assertJsonPath('data.slugs', [$tool->slug]);
});

it('ignores a tool that has not cleared the minimum run count', function () {
    setSetting('tools.trending_days', 3);
    setSetting('tools.trending_min_runs', 5);

    $tool = counterTool();
    ToolRun::factory()->count(4)->create(['tool_id' => $tool->id, 'created_at' => now()->subHour()]);

    expect(app(TrendingTools::class)->slugs())->toBe([]);
});
