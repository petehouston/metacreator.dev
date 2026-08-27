<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Tools\Enums\RunStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Folds raw telemetry into the daily rollups the admin dashboards read.
 *
 * The dashboards never touch `tool_runs` directly (docs/15). Two reasons, and the
 * second is the one that bites: raw runs are pruned at 90 days, so a year-long
 * chart read from them would silently flatten to zero for its first nine months.
 *
 * The rollup is a recompute, not an append — running it twice for the same day
 * produces the same rows, which is what makes a backfill safe and a partial failure
 * harmless.
 */
final class RollupDailyStats
{
    /** @return array{days: int, tool_rows: int, billing_rows: int, content_rows: int} */
    public function handle(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $toolRows = 0;
        $billingRows = 0;
        $contentRows = 0;

        for ($date = $from->startOfDay(); $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $toolRows += $this->rollUpToolRuns($date);
            $billingRows += $this->rollUpBilling($date);
            $contentRows += $this->rollUpContent($date);
        }

        return [
            'days' => (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1,
            'tool_rows' => $toolRows,
            'billing_rows' => $billingRows,
            'content_rows' => $contentRows,
        ];
    }

    /**
     * Grain: tool × tier × access_reason. The tier is read from `tools` rather than
     * stored on the run, so re-tiering a tool re-labels its history — which is what
     * an admin comparing "premium usage" across a pricing change actually wants.
     */
    private function rollUpToolRuns(CarbonImmutable $date): int
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $groups = DB::table('tool_runs as r')
            ->join('tools as t', 't.id', '=', 'r.tool_id')
            ->whereBetween('r.created_at', [$start, $end])
            ->groupBy('r.tool_id', 't.tier', 'r.access_reason')
            ->selectRaw('r.tool_id, t.tier, r.access_reason')
            ->selectRaw('COUNT(*) as runs')
            ->selectRaw('COUNT(DISTINCT COALESCE(r.visitor_hash, CONCAT("u", r.user_id))) as unique_actors')
            ->selectRaw('SUM(r.status = ?) as succeeded', [RunStatus::Succeeded->value])
            ->selectRaw('SUM(r.status = ?) as failed', [RunStatus::Failed->value])
            ->selectRaw('SUM(r.cache_hit = 1) as cache_hits')
            ->get();

        $written = 0;

        foreach ($groups as $group) {
            DB::table('tool_run_daily_stats')->updateOrInsert(
                [
                    'date' => $date->toDateString(),
                    'tool_id' => $group->tool_id,
                    'tier' => $group->tier,
                    'access_reason' => $group->access_reason,
                ],
                [
                    'runs' => (int) $group->runs,
                    'unique_actors' => (int) $group->unique_actors,
                    'succeeded' => (int) $group->succeeded,
                    'failed' => (int) $group->failed,
                    'cache_hits' => (int) $group->cache_hits,
                    ...$this->percentiles($start, $end, (int) $group->tool_id, (string) $group->access_reason),
                    'error_breakdown' => json_encode(
                        $this->errorBreakdown($start, $end, (int) $group->tool_id, (string) $group->access_reason)
                    ),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $written++;
        }

        return $written;
    }

    /**
     * Percentiles by offset rather than by a window function.
     *
     * MySQL has no `PERCENTILE_CONT`, and the usual workarounds (a self-join, or
     * `NTILE` over the whole partition) read the group many times over. Two indexed
     * `LIMIT 1 OFFSET n` reads over an ordered set are cheaper and exact.
     *
     * @return array{p50_duration_ms: int, p95_duration_ms: int}
     */
    private function percentiles(CarbonImmutable $start, CarbonImmutable $end, int $toolId, string $reason): array
    {
        $base = fn () => DB::table('tool_runs')
            ->whereBetween('created_at', [$start, $end])
            ->where('tool_id', $toolId)
            ->where('access_reason', $reason)
            ->whereNotNull('duration_ms')
            ->where('duration_ms', '>', 0)
            ->orderBy('duration_ms');

        $count = $base()->count();

        if ($count === 0) {
            return ['p50_duration_ms' => 0, 'p95_duration_ms' => 0];
        }

        return [
            'p50_duration_ms' => (int) $base()
                ->offset((int) floor($count * 0.50))
                ->limit(1)
                ->value('duration_ms'),
            'p95_duration_ms' => (int) $base()
                // `min(..., $count - 1)` so a 95th percentile never runs off the end
                // of a small group and returns null.
                ->offset(min($count - 1, (int) floor($count * 0.95)))
                ->limit(1)
                ->value('duration_ms'),
        ];
    }

    /** @return array<string, int> */
    private function errorBreakdown(CarbonImmutable $start, CarbonImmutable $end, int $toolId, string $reason): array
    {
        return DB::table('tool_runs')
            ->whereBetween('created_at', [$start, $end])
            ->where('tool_id', $toolId)
            ->where('access_reason', $reason)
            ->whereNotNull('error_code')
            ->groupBy('error_code')
            ->selectRaw('error_code, COUNT(*) as total')
            ->pluck('total', 'error_code')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * A snapshot, not a sum: MRR and active counts are point-in-time facts, so a day
     * that is recomputed later reflects the subscriptions that were active *then*
     * only to the extent the rows still say so. Same-day accuracy is what matters.
     */
    private function rollUpBilling(CarbonImmutable $date): int
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $mrr = (int) DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.stripe_status', ['active', 'trialing'])
            ->where('plans.billing_mode', 'subscription')
            ->where('subscriptions.created_at', '<=', $end)
            ->selectRaw("SUM(CASE plans.`interval`
                WHEN 'year' THEN plans.amount / 12
                WHEN 'month' THEN plans.amount / GREATEST(plans.interval_count, 1)
                WHEN 'day' THEN plans.amount * 30 / GREATEST(plans.interval_count, 1)
                ELSE 0 END) as mrr")
            ->value('mrr');

        $activeByPlan = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.stripe_status', ['active', 'trialing'])
            ->groupBy('plans.key')
            ->selectRaw('plans.key as plan_key, COUNT(*) as total')
            ->pluck('total', 'plan_key')
            ->all();

        DB::table('billing_daily_stats')->updateOrInsert(
            ['date' => $date->toDateString()],
            [
                'mrr' => $mrr,
                'arr' => $mrr * 12,
                'active_by_plan' => json_encode($activeByPlan),
                'new_subscriptions' => DB::table('subscriptions')->whereBetween('created_at', [$start, $end])->count(),
                'cancellations' => DB::table('subscriptions')->whereBetween('ends_at', [$start, $end])->count(),
                'passes_sold' => DB::table('access_passes')
                    ->where('source', 'purchase')
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
                'pass_upgrades' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return 1;
    }

    /**
     * Post views are counted on the posts table as a running total, so the daily
     * grain is the delta against yesterday's snapshot rather than a scan of an
     * event table we do not keep.
     */
    private function rollUpContent(CarbonImmutable $date): int
    {
        $previous = DB::table('content_daily_stats')
            ->where('date', $date->subDay()->toDateString())
            ->pluck('views_cumulative', 'post_id')
            ->all();

        $written = 0;

        DB::table('posts')
            ->whereNull('deleted_at')
            ->select('id', 'view_count')
            ->orderBy('id')
            ->chunk(500, function ($posts) use ($date, $previous, &$written): void {
                foreach ($posts as $post) {
                    $total = (int) $post->view_count;
                    $baseline = $previous[$post->id] ?? null;

                    DB::table('content_daily_stats')->updateOrInsert(
                        ['date' => $date->toDateString(), 'post_id' => $post->id],
                        [
                            // No baseline means this post's first rolled-up day: count
                            // nothing rather than attributing its entire lifetime view
                            // count to today and inventing a spike.
                            'views' => $baseline === null ? 0 : max(0, $total - (int) $baseline),
                            'views_cumulative' => $total,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ],
                    );

                    $written++;
                }
            });

        return $written;
    }
}
