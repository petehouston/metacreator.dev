<?php

declare(strict_types=1);

use App\Domain\Tools\Enums\AccessReason;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolCategory;
use App\Domain\Tools\Services\ToolAccessService;
use App\Domain\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Access control is the one thing in this codebase that must never be wrong: a
 * mistake here either gives away the paid product or locks out a paying customer.
 * Every actor × tier combination is asserted, including the grant expiry boundary.
 */
function toolOfTier(ToolTier $tier): Tool
{
    $category = ToolCategory::query()->firstOrCreate(
        ['slug' => 'testing'],
        ['name' => 'Testing', 'sort_order' => 0, 'is_visible' => true],
    );

    return Tool::query()->create([
        'slug' => 'tool-'.$tier->value.'-'.uniqid(),
        'key' => 'test.'.$tier->value.'.'.uniqid(),
        'category_id' => $category->id,
        'name' => 'Test tool',
        'tier' => $tier,
        'status' => 'published',
        'is_visible' => true,
    ]);
}

it('lets anonymous visitors run free tools only', function (ToolTier $tier, bool $allowed) {
    $decision = app(ToolAccessService::class)->decide(toolOfTier($tier), null);

    expect($decision->allowed)->toBe($allowed);
})->with([
    'free tool' => [ToolTier::Free, true],
    'account tool' => [ToolTier::Account, false],
    'premium tool' => [ToolTier::Premium, false],
]);

it('lets a free account run free and account tools but not premium', function (ToolTier $tier, bool $allowed) {
    $decision = app(ToolAccessService::class)->decide(toolOfTier($tier), User::factory()->create());

    expect($decision->allowed)->toBe($allowed);
})->with([
    'free tool' => [ToolTier::Free, true],
    'account tool' => [ToolTier::Account, true],
    'premium tool' => [ToolTier::Premium, false],
]);

it('lets a subscriber run every tier', function (ToolTier $tier) {
    $decision = app(ToolAccessService::class)->decide(toolOfTier($tier), subscriber());

    expect($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBe(AccessReason::Subscription);
})->with([ToolTier::Free, ToolTier::Account, ToolTier::Premium]);

it('tells an anonymous visitor to create an account, not to pay', function () {
    $decision = app(ToolAccessService::class)->decide(toolOfTier(ToolTier::Account), null);

    expect($decision->errorCode)->toBe('tool.account_required')
        ->and($decision->httpStatus())->toBe(401);
});

it('tells a signed-in free user to subscribe, not to sign up again', function () {
    $decision = app(ToolAccessService::class)->decide(toolOfTier(ToolTier::Premium), User::factory()->create());

    expect($decision->errorCode)->toBe('tool.subscription_required')
        ->and($decision->httpStatus())->toBe(402);
});

it('honours an unexpired grant on a premium tool', function () {
    $user = User::factory()->create();
    $tool = toolOfTier(ToolTier::Premium);

    $user->toolGrants()->create(['tool_id' => $tool->id, 'expires_at' => now()->addDay()]);

    $decision = app(ToolAccessService::class)->decide($tool, $user);

    expect($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBe(AccessReason::Grant);
});

it('stops honouring a grant the moment it expires', function () {
    $user = User::factory()->create();
    $tool = toolOfTier(ToolTier::Premium);

    $user->toolGrants()->create(['tool_id' => $tool->id, 'expires_at' => now()->addMinute()]);

    expect(app(ToolAccessService::class)->decide($tool, $user)->allowed)->toBeTrue();

    // The boundary is what matters: one second past expiry must deny.
    $this->travelTo(now()->addMinutes(1)->addSecond());

    expect(app(ToolAccessService::class)->decide($tool, $user)->allowed)->toBeFalse();
});

it('treats a grant with no expiry as permanent', function () {
    $user = User::factory()->create();
    $tool = toolOfTier(ToolTier::Premium);

    $user->toolGrants()->create(['tool_id' => $tool->id, 'expires_at' => null]);

    $this->travelTo(now()->addYears(5));

    expect(app(ToolAccessService::class)->decide($tool, $user)->allowed)->toBeTrue();
});

it('lets staff with the bypass permission run anything', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('support');

    $decision = app(ToolAccessService::class)->decide(toolOfTier(ToolTier::Premium), $user);

    expect($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBe(AccessReason::Admin);
});

it('refuses to run a hidden tool for anyone', function () {
    $tool = toolOfTier(ToolTier::Free);
    $tool->update(['status' => 'hidden']);

    expect(app(ToolAccessService::class)->decide($tool, subscriber())->allowed)->toBeFalse();
});

it('returns the same decisions in bulk as it does one at a time', function () {
    // The catalog page uses decideMany for performance; if it ever disagrees with
    // decide(), the UI shows a lock the API would have opened (or worse, vice versa).
    $tools = collect([ToolTier::Free, ToolTier::Account, ToolTier::Premium])
        ->map(fn (ToolTier $tier) => toolOfTier($tier));

    $service = app(ToolAccessService::class);

    foreach ([null, User::factory()->create(), subscriber()] as $actor) {
        $bulk = $service->decideMany($tools, $actor);

        foreach ($tools as $tool) {
            expect($bulk[$tool->slug])->toBe($service->decide($tool, $actor)->allowed);
        }
    }
});
