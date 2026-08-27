<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\RollupDailyStats;
use App\Domain\Analytics\Data\Period;
use App\Domain\Analytics\Services\FunnelRecorder;
use App\Domain\Analytics\Services\ToolAnalytics;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tools\Enums\AccessReason;
use App\Domain\Tools\Enums\RunStatus;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolRun;
use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The dashboards read rollups, never live tables — so the rollup being *correct* is
 * the whole contract. A number on an admin screen that is quietly wrong is worse
 * than a screen that is missing: people make decisions on it.
 */
function runFor(Tool $tool, array $attributes = []): ToolRun
{
    return ToolRun::query()->create([
        'ulid' => strtoupper((string) Str::ulid()),
        'tool_id' => $tool->id,
        'tool_version' => 1,
        'status' => RunStatus::Succeeded,
        'access_reason' => AccessReason::Free,
        'input_hash' => hash('sha256', uniqid()),
        'duration_ms' => 100,
        ...$attributes,
    ]);
}

it('rolls raw runs into the daily grain, and recomputes without doubling them', function () {
    $tool = toolFixture(ToolTier::Free);

    runFor($tool, ['duration_ms' => 100]);
    runFor($tool, ['duration_ms' => 300]);
    runFor($tool, ['status' => RunStatus::Failed, 'error_code' => 'tool.failed', 'duration_ms' => 50]);

    $today = CarbonImmutable::now();

    app(RollupDailyStats::class)->handle($today, $today);
    // Twice on purpose: the rollup is a recompute, so a backfill or a retry must be
    // safe. An append would show six runs here.
    app(RollupDailyStats::class)->handle($today, $today);

    $row = DB::table('tool_run_daily_stats')->where('tool_id', $tool->id)->first();

    expect((int) $row->runs)->toBe(3)
        ->and((int) $row->succeeded)->toBe(2)
        ->and((int) $row->failed)->toBe(1)
        ->and(json_decode((string) $row->error_breakdown, true))->toBe(['tool.failed' => 1]);
});

it('reports a p95 that does not run off the end of a small sample', function () {
    $tool = toolFixture(ToolTier::Free);

    foreach ([10, 20, 30] as $duration) {
        runFor($tool, ['duration_ms' => $duration]);
    }

    $today = CarbonImmutable::now();
    app(RollupDailyStats::class)->handle($today, $today);

    $row = DB::table('tool_run_daily_stats')->where('tool_id', $tool->id)->first();

    expect((int) $row->p95_duration_ms)->toBe(30)
        ->and((int) $row->p50_duration_ms)->toBeGreaterThan(0);
});

it('separates the three walls a user can hit', function () {
    $tool = toolFixture(ToolTier::Premium);
    $recorder = app(FunnelRecorder::class);

    $recorder->wall($tool->id, 'tool.subscription_required');
    $recorder->wall($tool->id, 'tool.subscription_required');
    $recorder->wall($tool->id, 'tool.account_required');
    $recorder->wall($tool->id, 'tool.quota_exceeded');
    // Not a wall — must not be counted as one.
    $recorder->wall($tool->id, 'tool.failed');

    $row = DB::table('tool_funnel_daily')->where('tool_id', $tool->id)->first();

    expect((int) $row->paywall_hits)->toBe(2)
        ->and((int) $row->account_walls)->toBe(1)
        ->and((int) $row->quota_walls)->toBe(1);
});

it('records the wall a visitor actually hit when a run is refused', function (ToolTier $tier, int $status, string $column) {
    $tool = toolFixture($tier);

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => []])->assertStatus($status);

    expect((int) DB::table('tool_funnel_daily')->where('tool_id', $tool->id)->value($column))->toBe(1);
})->with([
    'premium tool, anonymous visitor' => [ToolTier::Premium, 402, 'paywall_hits'],
    'account tool, anonymous visitor' => [ToolTier::Account, 401, 'account_walls'],
]);

it('normalises a yearly plan into monthly recurring revenue', function () {
    $user = User::factory()->create();

    $plan = Plan::query()->create([
        'key' => 'pro_yearly_test',
        'name' => 'Pro Yearly',
        'billing_mode' => 'subscription',
        'interval' => 'year',
        'interval_count' => 1,
        'amount' => 12000,
        'currency' => 'USD',
        'is_active' => true,
    ]);

    Subscription::query()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'stripe_status' => 'active',
        'current_period_end' => now()->addYear(),
    ]);

    $response = $this->actingAs(staff('super-admin'))->getJson('/api/v1/admin/overview')->assertOk();

    $mrr = collect($response->json('data.metrics'))->firstWhere('key', 'mrr');

    // A year's cash booked as one month of recurring revenue is the classic way a
    // dashboard flatters itself. 12000/12 = 1000.
    expect((int) $mrr['value'])->toBe(1000);
});

it('fills the gaps in a series so a quiet day is a zero, not a missing point', function () {
    $tool = toolFixture(ToolTier::Free);
    runFor($tool);

    $today = CarbonImmutable::now();
    app(RollupDailyStats::class)->handle($today, $today);

    $series = app(ToolAnalytics::class)->volumeSeries(Period::ofDays(7));

    expect($series)->toHaveCount(7)
        ->and(collect($series)->pluck('date')->unique())->toHaveCount(7)
        ->and(collect($series)->last()['runs'])->toBe(1);
});

it('compares a window against the equal window before it, not a calendar month', function () {
    $period = Period::ofDays(30);
    $previous = $period->previous();

    expect($period->dates())->toHaveCount(30)
        ->and($previous->days)->toBe(30)
        ->and($previous->end->lessThan($period->start))->toBeTrue();
});

it('rejects a period the dashboards do not offer', function () {
    $this->actingAs(staff('super-admin'))
        ->getJson('/api/v1/admin/overview?period=9999')
        ->assertOk()
        // Falls back to the default rather than erroring: a bookmarked URL with a
        // stale period should still show someone their dashboard.
        ->assertJsonPath('data.period.days', 30);
});
