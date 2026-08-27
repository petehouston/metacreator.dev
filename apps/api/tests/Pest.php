<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SyncRolesAndPermissions;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Settings\Settings;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolCategory;
use App\Domain\Users\Models\MagicLink;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

// Feature and Architecture tests touch the database; Tools tests exercise pure
// runner logic and are kept fast by staying out of it.
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Architecture');
pest()->extend(TestCase::class)->in('Tools', 'Unit');

/**
 * The array cache store lives for the whole process, so it outlives the per-test
 * database rollback. Without this, a test that writes a setting leaves the cached
 * settings map behind and the *next* test reads a value whose row no longer exists.
 */
pest()->beforeEach(function (): void {
    app(Settings::class)->flush();
})->in('Feature', 'Architecture');

/**
 * Give a user paid access without going anywhere near Stripe.
 *
 * Tests that need a subscriber should not be coupled to the billing projection's
 * shape; when that changes, this helper changes once.
 */
function subscriber(): User
{
    $user = User::factory()->create();
    $plan = Plan::query()->firstOrCreate(
        ['key' => 'pro_monthly'],
        ['name' => 'Pro Monthly', 'billing_mode' => 'subscription', 'interval' => 'month',
            'amount' => 1900, 'currency' => 'USD', 'is_active' => true],
    );

    Subscription::query()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'stripe_id' => 'sub_test_'.$user->id,
        'stripe_status' => 'active',
        'current_period_start' => now()->subDay(),
        'current_period_end' => now()->addMonth(),
    ]);

    return $user;
}

/**
 * Issue a magic link and hand back the plaintext token.
 *
 * Only the hash is stored, so a test that needs to *use* a link has to generate it
 * the same way the action does rather than reading it back out of the database.
 */
function issueMagicLinkFor(string $email): string
{
    $token = Str::random(64);

    MagicLink::query()->create([
        'email' => $email,
        'token_hash' => MagicLink::hash($token),
        'intent' => 'login',
        'expires_at' => now()->addMinutes(15),
    ]);

    return $token;
}

/**
 * A staff member holding exactly one role.
 *
 * Tests assert what a role *cannot* do at least as often as what it can, so the
 * helper deliberately grants one role and nothing else — a helper that quietly
 * added `admin` would make every negative assertion vacuous.
 */
function staff(string $role): User
{
    app(SyncRolesAndPermissions::class)->handle();

    return tap(User::factory()->create(), fn (User $user) => $user->assignRole($role));
}

/**
 * A published tool of a given tier, with a category to hang off.
 *
 * Shared rather than duplicated per file: several suites need one, and two
 * fixtures that drift apart produce tests that pass against different products.
 */
function toolFixture(ToolTier $tier = ToolTier::Premium): Tool
{
    $category = ToolCategory::query()->firstOrCreate(
        ['slug' => 'admin-fixtures'],
        ['name' => 'Fixtures', 'sort_order' => 0, 'is_visible' => true],
    );

    return Tool::query()->create([
        'slug' => 'fixture-'.uniqid(),
        'key' => 'fixture.'.uniqid(),
        'category_id' => $category->id,
        'name' => 'Fixture tool',
        'tier' => $tier,
        'status' => 'published',
        'is_visible' => true,
    ]);
}
