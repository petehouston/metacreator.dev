<?php

declare(strict_types=1);

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Settings\Setting;
use App\Domain\Settings\Settings;
use App\Domain\Tools\Enums\QuotaWindow;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Services\ToolAccessService;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Redis;

/**
 * The master switch for money, asserted from both sides.
 *
 * Turning billing off is only safe if it is reversible and total: a premium tool
 * must become reachable with nothing more than an account, and turning the switch
 * back on must re-lock it without touching a single row. Both halves are asserted
 * here, because a half-applied switch is worse than no switch — it leaves the paid
 * catalog visible with no way to buy it, or gives it away permanently.
 */
function disableBilling(bool $disabled = true): void
{
    Setting::query()->updateOrCreate(
        ['key' => 'features.billing_enabled'],
        ['value' => ['v' => ! $disabled], 'type' => 'bool', 'group' => 'features', 'is_public' => true],
    );

    app(Settings::class)->flush();
}

/** A run budget, set the way an admin would. Local: `setSetting` is file-scoped. */
function setRunLimit(string $key, int $value): void
{
    Setting::query()->updateOrCreate(
        ['key' => $key],
        ['value' => ['v' => $value], 'type' => 'int', 'group' => 'tools', 'is_public' => true],
    );

    app(Settings::class)->flush();
}

/** Quota counters live in Redis, which is not rolled back between tests. */
beforeEach(function (): void {
    Redis::flushdb();
});

it('gates a premium tool at account level while billing is off', function () {
    $tool = toolFixture(ToolTier::Premium);
    $access = app(ToolAccessService::class);

    disableBilling();

    // A signed-in visitor gets in; an anonymous one still does not — the tool became
    // "Account Required", not free.
    expect($access->allows($tool, User::factory()->create()))->toBeTrue()
        ->and($access->allows($tool, null))->toBeFalse();
});

it('tells an anonymous visitor to create an account, not to subscribe', function () {
    $tool = toolFixture(ToolTier::Premium);

    disableBilling();

    $decision = app(ToolAccessService::class)->decide($tool, null);

    expect($decision->allowed)->toBeFalse()
        ->and($decision->errorCode)->toBe('tool.account_required')
        ->and($decision->requiredTier)->toBe(ToolTier::Account);
});

it('leaves free and account tools exactly as they were', function (ToolTier $tier, bool $anonymous) {
    $tool = toolFixture($tier);

    disableBilling();

    $access = app(ToolAccessService::class);

    expect($access->allows($tool, null))->toBe($anonymous)
        ->and($access->allows($tool, User::factory()->create()))->toBeTrue();
})->with([
    'free tool' => [ToolTier::Free, true],
    'account tool' => [ToolTier::Account, false],
]);

it('re-locks the premium catalog when billing is switched back on', function () {
    $tool = toolFixture(ToolTier::Premium);
    $user = User::factory()->create();
    $access = app(ToolAccessService::class);

    disableBilling();
    expect($access->allows($tool, $user))->toBeTrue();

    disableBilling(false);

    // The row never changed, so the paywall comes back on its own.
    expect($tool->refresh()->tier)->toBe(ToolTier::Premium)
        ->and($access->allows($tool, $user))->toBeFalse();
});

it('serves the downgraded tier through the public catalog', function () {
    $tool = toolFixture(ToolTier::Premium);

    disableBilling();

    $this->getJson("/api/v1/catalog/tools/{$tool->slug}")
        ->assertOk()
        ->assertJsonPath('data.tier.value', 'account')
        ->assertJsonPath('data.tier.label', 'Account Required')
        ->assertJsonPath('data.access.required_tier', 'account');
});

it('filters a downgraded tool under account, and finds nothing under premium', function () {
    $tool = toolFixture(ToolTier::Premium);

    disableBilling();

    $this->getJson('/api/v1/catalog/tools?filter[tier]=account')
        ->assertOk()
        ->assertJsonPath('data.0.slug', $tool->slug);

    $this->getJson('/api/v1/catalog/tools?filter[tier]=premium')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('stops publishing gateway settings, but keeps the flag that hides them', function () {
    disableBilling();

    $response = $this->getJson('/api/v1/settings')->assertOk();

    $keys = array_keys($response->json('data'));

    expect($keys)->toContain('features.billing_enabled')
        ->and(array_filter($keys, fn (string $key): bool => str_starts_with($key, 'payments.')))->toBeEmpty();
});

it('reports a subscriber as unpaid without cancelling anything', function () {
    $user = subscriber();
    $entitlements = app(EntitlementService::class);

    disableBilling();
    $entitlements->forget($user);

    expect($entitlements->isPaid($user))->toBeFalse()
        ->and($entitlements->accessTierFor($user))->toBe(ToolTier::Account);

    disableBilling(false);
    $entitlements->forget($user);

    // The subscription row was never touched, so access returns intact.
    expect($entitlements->isPaid($user))->toBeTrue();
});

it('hands every signed-in user the perks that used to cost money', function () {
    $user = User::factory()->create();

    disableBilling();

    $limits = app(EntitlementService::class)->limitsFor($user);

    // Leaving export behind a paywall nobody can pay would be a permanently
    // disabled button with no way to earn it.
    expect($limits['history_days'])->toBeNull()
        ->and($limits['export'])->toBeTrue();
});

it('drops billing from the entitlements payload the dashboard renders against', function () {
    $user = subscriber();

    disableBilling();
    app(EntitlementService::class)->forget($user);

    $this->actingAs($user)
        ->getJson('/api/v1/account/entitlements')
        ->assertOk()
        ->assertJsonPath('data.billing_enabled', false)
        ->assertJsonPath('data.plan', 'free')
        ->assertJsonPath('data.is_paid', false)
        ->assertJsonPath('data.renews_at', null)
        ->assertJsonPath('data.tool_access.highest_tier', 'account');
});

it('stops offering Pro at the quota wall when there is no Pro to buy', function () {
    $user = User::factory()->create();

    setRunLimit('tools.limits.account.daily', 1);
    disableBilling();

    $tool = counterTool(ToolTier::Free);

    $this->actingAs($user)
        ->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'one two three']])
        ->assertSuccessful();

    $this->actingAs($user)
        ->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'four five six']])
        ->assertStatus(429)
        ->assertJsonPath('error.details.next_tier', null)
        ->assertJsonPath('error.details.upgrade_available', false)
        ->assertJsonPath('error.details.upgrade_action', null);
});

it('still offers an account to an exhausted anonymous visitor', function () {
    setRunLimit('tools.limits.free.daily', 0);
    disableBilling();

    $tool = counterTool(ToolTier::Free);

    // The free → account rung survives; it is only the paid rung above it that goes.
    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'one two']])
        ->assertStatus(429)
        ->assertJsonPath('error.details.next_tier', ToolTier::Account->value)
        ->assertJsonPath('error.details.upgrade_action', 'register');
});

it('keeps the premium tier intact when billing is left on', function () {
    $tool = toolFixture(ToolTier::Premium);

    expect(app(ToolAccessService::class)->allows($tool, User::factory()->create()))->toBeFalse();

    $this->getJson("/api/v1/catalog/tools/{$tool->slug}")
        ->assertOk()
        ->assertJsonPath('data.tier.value', 'premium');

    expect(app(EntitlementService::class)->limitForTier(ToolTier::Premium, QuotaWindow::Daily))
        ->toBe(EntitlementService::UNLIMITED);
});
