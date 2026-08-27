<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Data\Metric;
use App\Domain\Analytics\Data\Period;
use App\Domain\Tools\Enums\RunStatus;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The numbers on `/admin` — the health of the product in one screen.
 *
 * Every metric is computed for the selected window *and* the equal window before
 * it, in the same query where possible, so the delta costs nothing extra. Results
 * are cached briefly: an admin refreshing a dashboard should not be able to put
 * five aggregate scans a second onto the primary.
 */
final class OverviewMetrics
{
    private const CACHE_TTL = 120;

    public function __construct(private readonly Cache $cache) {}

    /**
     * @return array{period: array<string, mixed>, metrics: list<array<string, mixed>>}
     */
    public function headline(Period $period): array
    {
        return $this->cache->remember(
            "admin:overview:headline:{$period->days}",
            self::CACHE_TTL,
            fn (): array => [
                'period' => $period->toArray(),
                'metrics' => array_map(
                    static fn (Metric $metric): array => $metric->toArray(),
                    $this->buildMetrics($period),
                ),
            ],
        );
    }

    /** @return list<Metric> */
    private function buildMetrics(Period $period): array
    {
        $runs = $this->dailyCounts('tool_runs', $period);
        $signups = $this->dailyCounts('users', $period);
        $visitors = $this->dailyVisitors($period);
        $revenue = $this->dailyRevenue($period);

        $previous = $period->previous();

        return [
            Metric::make(
                key: 'tool_runs',
                label: 'Tool runs',
                value: $this->sum($runs, $period),
                previous: $this->countIn('tool_runs', $previous),
                series: $this->series($runs, $period),
                hint: 'Every execution, signed in or not.',
            ),

            Metric::make(
                key: 'visitors',
                label: 'Unique visitors',
                value: $this->sum($visitors, $period),
                previous: $this->uniqueVisitorsIn($previous),
                series: $this->series($visitors, $period),
                hint: 'Distinct daily actors — a rotating hash, never an IP.',
            ),

            Metric::make(
                key: 'signups',
                label: 'New accounts',
                value: $this->sum($signups, $period),
                previous: $this->countIn('users', $previous),
                series: $this->series($signups, $period),
            ),

            Metric::make(
                key: 'mrr',
                label: 'MRR',
                value: $this->currentMrr(),
                previous: null,
                format: 'currency',
                hint: 'Normalised monthly value of every active subscription.',
            ),

            Metric::make(
                key: 'revenue',
                label: 'Revenue collected',
                value: $this->sum($revenue, $period),
                previous: $this->revenueIn($previous),
                format: 'currency',
                series: $this->series($revenue, $period),
            ),

            Metric::make(
                key: 'conversion',
                label: 'Run → account',
                value: $this->conversionRate($period),
                previous: $this->conversionRate($previous),
                format: 'percent',
                hint: 'Accounts created per 100 unique visitors who ran a tool.',
            ),

            Metric::make(
                key: 'failure_rate',
                label: 'Run failure rate',
                value: $this->failureRate($period),
                previous: $this->failureRate($previous),
                format: 'percent',
                // The one metric on this screen where down is the good direction.
                higherIsBetter: false,
            ),

            Metric::make(
                key: 'open_tickets',
                label: 'Open tickets',
                value: (float) DB::table('tickets')
                    ->whereIn('status', ['open', 'pending', 'on_hold'])
                    ->count(),
                previous: null,
                higherIsBetter: false,
                hint: 'Everything not yet solved or closed, at this moment.',
            ),
        ];
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    /**
     * Daily row counts keyed by date. One grouped scan rather than one query per day.
     *
     * @return array<string, float>
     */
    private function dailyCounts(string $table, Period $period): array
    {
        return $this->keyByDate(
            DB::table($table)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->whereBetween('created_at', [$period->start, $period->end])
                ->groupBy('day')
        );
    }

    /** @return array<string, float> */
    private function dailyVisitors(Period $period): array
    {
        return $this->keyByDate(
            DB::table('tool_runs')
                // COALESCE so a signed-in run counts as its user rather than as an
                // anonymous null — otherwise every logged-in run collapses into one
                // "visitor" and the number is nonsense.
                ->selectRaw('DATE(created_at) as day, COUNT(DISTINCT COALESCE(visitor_hash, CONCAT("u", user_id))) as total')
                ->whereBetween('created_at', [$period->start, $period->end])
                ->groupBy('day')
        );
    }

    /** @return array<string, float> */
    private function dailyRevenue(Period $period): array
    {
        return $this->keyByDate(
            DB::table('invoices')
                ->selectRaw('DATE(paid_at) as day, SUM(total - amount_refunded) as total')
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$period->start, $period->end])
                ->groupBy('day')
        );
    }

    private function countIn(string $table, Period $period): float
    {
        return (float) DB::table($table)
            ->whereBetween('created_at', [$period->start, $period->end])
            ->count();
    }

    private function uniqueVisitorsIn(Period $period): float
    {
        return (float) DB::table('tool_runs')
            ->whereBetween('created_at', [$period->start, $period->end])
            ->distinct()
            ->count(DB::raw('COALESCE(visitor_hash, CONCAT("u", user_id))'));
    }

    private function revenueIn(Period $period): float
    {
        return (float) DB::table('invoices')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$period->start, $period->end])
            ->sum(DB::raw('total - amount_refunded'));
    }

    /**
     * Monthly recurring revenue in minor units.
     *
     * Yearly plans are divided by twelve rather than counted whole — booking a year's
     * cash as one month's recurring revenue is the classic way to make a dashboard
     * lie. One-time passes are excluded entirely: they do not recur.
     */
    private function currentMrr(): float
    {
        return (float) DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.stripe_status', ['active', 'trialing'])
            ->where('plans.billing_mode', 'subscription')
            ->selectRaw("SUM(CASE plans.`interval`
                WHEN 'year' THEN plans.amount / 12
                WHEN 'month' THEN plans.amount / GREATEST(plans.interval_count, 1)
                WHEN 'day' THEN plans.amount * 30 / GREATEST(plans.interval_count, 1)
                ELSE 0 END) as mrr")
            ->value('mrr');
    }

    private function conversionRate(Period $period): float
    {
        $visitors = $this->uniqueVisitorsIn($period);

        if ($visitors === 0.0) {
            return 0.0;
        }

        return round(($this->countIn('users', $period) / $visitors) * 100, 2);
    }

    private function failureRate(Period $period): float
    {
        $row = DB::table('tool_runs')
            ->whereBetween('created_at', [$period->start, $period->end])
            ->selectRaw('COUNT(*) as total, SUM(status = ?) as failed', [RunStatus::Failed->value])
            ->first();

        $total = (int) ($row->total ?? 0);

        return $total === 0 ? 0.0 : round(((int) ($row->failed ?? 0) / $total) * 100, 2);
    }

    // ── Shaping ──────────────────────────────────────────────────────────────

    /** @return array<string, float> */
    private function keyByDate(Builder $query): array
    {
        return $query->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->day => (float) $row->total])
            ->all();
    }

    /**
     * Fill the gaps. A sparkline drawn only from the days that had data compresses
     * quiet stretches and invents a trend that never happened.
     *
     * @param  array<string, float>  $counts
     * @return list<array{date: string, value: float}>
     */
    private function series(array $counts, Period $period): array
    {
        return array_map(
            static fn (string $date): array => ['date' => $date, 'value' => $counts[$date] ?? 0.0],
            $period->dates(),
        );
    }

    /** @param array<string, float> $counts */
    private function sum(array $counts, Period $period): float
    {
        return array_sum(array_map(
            static fn (string $date): float => $counts[$date] ?? 0.0,
            $period->dates(),
        ));
    }
}
