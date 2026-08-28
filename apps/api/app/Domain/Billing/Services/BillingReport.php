<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Analytics\Data\Metric;
use App\Domain\Analytics\Data\Period;
use App\Domain\Analytics\Services\OverviewMetrics;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

/**
 * The billing report: revenue, subscriptions and what is driving both.
 *
 * Separate from {@see OverviewMetrics} even though
 * two numbers overlap, because the questions differ. The overview asks "is the
 * product healthy" and shows MRR next to tool runs; this asks "where is the money
 * coming from, and what is leaking" — and the answer is mostly breakdowns, not
 * headlines.
 *
 * Two rules run through every figure here:
 *
 * - **Revenue is net of refunds.** A refunded charge was not revenue. Reporting
 *   gross and refunds as separate positives makes a bad month look like a good one.
 * - **Recurring revenue is normalised to a month.** A yearly plan contributes a
 *   twelfth. Booking a year's cash as one month of MRR is the classic way to make
 *   a dashboard lie, and it lies in the flattering direction.
 *
 * Cached briefly: these are aggregate scans over the whole invoice table, and an
 * admin holding down refresh should not be able to put five of them a second onto
 * the primary.
 */
final class BillingReport
{
    private const CACHE_TTL = 180;

    /** Statuses that count as a live subscription for revenue purposes. */
    private const LIVE = ['active', 'trialing'];

    public function __construct(private readonly Cache $cache) {}

    /** @return array<string, mixed> */
    public function build(Period $period): array
    {
        return $this->cache->remember(
            "admin:billing:report:{$period->days}",
            self::CACHE_TTL,
            fn (): array => [
                'period' => $period->toArray(),
                'periods' => Period::PRESETS,
                'currency' => $this->currency(),
                'metrics' => array_map(
                    static fn (Metric $metric): array => $metric->toArray(),
                    $this->metrics($period),
                ),
                'revenue_series' => $this->revenueSeries($period),
                'subscription_series' => $this->subscriptionSeries($period),
                'by_plan' => $this->byPlan($period),
                'by_gateway' => $this->byGateway($period),
                'by_status' => $this->byStatus($period),
                'top_customers' => $this->topCustomers($period),
                'recent_refunds' => $this->recentRefunds($period),
            ],
        );
    }

    // ── Headlines ────────────────────────────────────────────────────────────

    /** @return list<Metric> */
    private function metrics(Period $period): array
    {
        $previous = $period->previous();

        $newSubs = $this->newSubscriptions($period);
        $cancelled = $this->cancellations($period);

        return [
            Metric::make(
                key: 'net_revenue',
                label: 'Net revenue',
                value: $this->netRevenue($period),
                previous: $this->netRevenue($previous),
                format: 'currency',
                series: $this->revenueSeries($period),
                hint: 'Collected minus refunded, in the window the money landed.',
            ),

            Metric::make(
                key: 'mrr',
                label: 'MRR',
                value: $this->mrr(),
                previous: null,
                format: 'currency',
                hint: 'Every live subscription normalised to a month. A yearly plan counts as a twelfth.',
            ),

            Metric::make(
                key: 'arr',
                label: 'ARR',
                value: $this->mrr() * 12,
                previous: null,
                format: 'currency',
                hint: 'MRR × 12. A run-rate, not a forecast.',
            ),

            Metric::make(
                key: 'active_subscriptions',
                label: 'Active subscriptions',
                value: (float) $this->liveSubscriptionCount(),
                previous: null,
                hint: 'Active and trialing, at this moment.',
            ),

            Metric::make(
                key: 'new_subscriptions',
                label: 'New subscriptions',
                value: (float) $newSubs,
                previous: (float) $this->newSubscriptions($previous),
            ),

            Metric::make(
                key: 'cancellations',
                label: 'Cancellations',
                value: (float) $cancelled,
                previous: (float) $this->cancellations($previous),
                higherIsBetter: false,
            ),

            Metric::make(
                key: 'churn_rate',
                label: 'Churn rate',
                value: $this->churnRate($period),
                previous: $this->churnRate($previous),
                format: 'percent',
                higherIsBetter: false,
                hint: 'Cancellations in the window over the subscriptions live at its start.',
            ),

            Metric::make(
                key: 'arpu',
                label: 'ARPU',
                value: $this->arpu(),
                previous: null,
                format: 'currency',
                hint: 'MRR divided by live subscriptions.',
            ),

            Metric::make(
                key: 'refunded',
                label: 'Refunded',
                value: $this->refunded($period),
                previous: $this->refunded($previous),
                format: 'currency',
                higherIsBetter: false,
            ),

            Metric::make(
                key: 'outstanding',
                label: 'Outstanding',
                value: $this->outstanding(),
                previous: null,
                format: 'currency',
                higherIsBetter: false,
                hint: 'Issued and never paid, all time. Dunning\'s to-do list.',
            ),
        ];
    }

    // ── Series ───────────────────────────────────────────────────────────────

    /** @return list<array{date: string, value: float}> */
    private function revenueSeries(Period $period): array
    {
        $rows = DB::table('invoices')
            ->selectRaw('DATE(paid_at) as day, SUM(total - amount_refunded) as total')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$period->start, $period->end])
            ->groupBy('day')
            ->pluck('total', 'day');

        return array_map(
            static fn (string $date): array => [
                'date' => $date,
                'value' => (float) ($rows[$date] ?? 0),
            ],
            $period->dates(),
        );
    }

    /**
     * New versus cancelled, per day.
     *
     * Both on one series so the chart can show them against each other — the shape
     * that matters is whether the two lines are converging, and two separate charts
     * make that comparison an act of memory.
     *
     * @return list<array{date: string, new: float, cancelled: float}>
     */
    private function subscriptionSeries(Period $period): array
    {
        $created = DB::table('subscriptions')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereBetween('created_at', [$period->start, $period->end])
            ->groupBy('day')
            ->pluck('total', 'day');

        $ended = DB::table('subscriptions')
            ->selectRaw('DATE(ends_at) as day, COUNT(*) as total')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$period->start, $period->end])
            ->groupBy('day')
            ->pluck('total', 'day');

        return array_map(
            static fn (string $date): array => [
                'date' => $date,
                'new' => (float) ($created[$date] ?? 0),
                'cancelled' => (float) ($ended[$date] ?? 0),
            ],
            $period->dates(),
        );
    }

    // ── Breakdowns ───────────────────────────────────────────────────────────

    /**
     * Revenue and subscriber count per plan.
     *
     * Left-joined from `plans` so a plan that sold nothing still appears as a zero
     * row. A plan missing from the table reads as "no data"; a zero reads as "this
     * is not selling", and only one of those is true.
     *
     * @return list<array<string, mixed>>
     */
    private function byPlan(Period $period): array
    {
        $revenue = DB::table('invoices')
            ->selectRaw('plan_id, SUM(total - amount_refunded) as total, COUNT(*) as invoices')
            ->whereNotNull('paid_at')
            ->whereNotNull('plan_id')
            ->whereBetween('paid_at', [$period->start, $period->end])
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        $live = DB::table('subscriptions')
            ->selectRaw('plan_id, COUNT(*) as total')
            ->whereIn('stripe_status', self::LIVE)
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        $plans = DB::table('plans')
            ->select('id', 'key', 'name', 'amount', 'currency', 'interval', 'billing_mode', 'is_active')
            ->orderBy('sort_order')
            ->get();

        $total = (float) $revenue->sum('total');

        return array_values($plans
            ->map(function (object $plan) use ($revenue, $live, $total): array {
                $planRevenue = (float) ($revenue[$plan->id]->total ?? 0);

                return [
                    'id' => (int) $plan->id,
                    'key' => $plan->key,
                    'name' => $plan->name,
                    'interval' => $plan->interval,
                    'billing_mode' => $plan->billing_mode,
                    'is_active' => (bool) $plan->is_active,
                    'revenue' => $planRevenue,
                    'invoices' => (int) ($revenue[$plan->id]->invoices ?? 0),
                    'active_subscriptions' => (int) ($live[$plan->id] ?? 0),
                    'share' => $total > 0 ? round(($planRevenue / $total) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function byGateway(Period $period): array
    {
        $rows = DB::table('invoices')
            ->selectRaw('gateway, SUM(total - amount_refunded) as total, COUNT(*) as invoices')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$period->start, $period->end])
            ->groupBy('gateway')
            ->get();

        $total = (float) $rows->sum('total');

        return array_values($rows
            ->map(static fn (object $row): array => [
                'gateway' => (string) ($row->gateway ?? 'unknown'),
                'revenue' => (float) $row->total,
                'invoices' => (int) $row->invoices,
                'share' => $total > 0 ? round(((float) $row->total / $total) * 100, 1) : 0.0,
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all());
    }

    /**
     * Invoice counts and value by status, over the window they were issued in.
     *
     * @return list<array<string, mixed>>
     */
    private function byStatus(Period $period): array
    {
        return array_values(DB::table('invoices')
            ->selectRaw('status, COUNT(*) as invoices, SUM(total) as total')
            ->whereBetween('issued_at', [$period->start, $period->end])
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(static fn (object $row): array => [
                'status' => (string) $row->status,
                'invoices' => (int) $row->invoices,
                'total' => (float) $row->total,
            ])
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function topCustomers(Period $period): array
    {
        return array_values(DB::table('invoices')
            ->join('users', 'users.id', '=', 'invoices.user_id')
            ->selectRaw('users.ulid, users.email, users.name, users.display_name,
                SUM(invoices.total - invoices.amount_refunded) as total,
                COUNT(*) as invoices')
            ->whereNotNull('invoices.paid_at')
            ->whereBetween('invoices.paid_at', [$period->start, $period->end])
            ->groupBy('users.id', 'users.ulid', 'users.email', 'users.name', 'users.display_name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(static fn (object $row): array => [
                'id' => $row->ulid,
                'email' => $row->email,
                'display_name' => $row->display_name ?: $row->name,
                'revenue' => (float) $row->total,
                'invoices' => (int) $row->invoices,
            ])
            ->all());
    }

    /**
     * The refunds themselves, not just the total.
     *
     * A refund total with no rows behind it is a number nobody can act on: the
     * useful question is always "which ones, and why".
     *
     * @return list<array<string, mixed>>
     */
    private function recentRefunds(Period $period): array
    {
        return array_values(DB::table('invoices')
            ->leftJoin('users', 'users.id', '=', 'invoices.user_id')
            ->select(
                'invoices.id', 'invoices.number', 'invoices.currency',
                'invoices.amount_refunded', 'invoices.refunded_at',
                'invoices.refund_reason', 'users.email',
            )
            ->where('invoices.amount_refunded', '>', 0)
            ->where(fn ($q) => $q
                ->whereBetween('invoices.refunded_at', [$period->start, $period->end])
                ->orWhereNull('invoices.refunded_at'))
            ->orderByDesc('invoices.refunded_at')
            ->limit(8)
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'number' => $row->number,
                'email' => $row->email,
                'amount' => (float) $row->amount_refunded,
                'currency' => $row->currency,
                'refunded_at' => $row->refunded_at,
                'reason' => $row->refund_reason,
            ])
            ->all());
    }

    // ── Figures ──────────────────────────────────────────────────────────────

    private function netRevenue(Period $period): float
    {
        return (float) DB::table('invoices')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$period->start, $period->end])
            ->sum(DB::raw('total - amount_refunded'));
    }

    private function refunded(Period $period): float
    {
        return (float) DB::table('invoices')
            ->where('amount_refunded', '>', 0)
            ->whereBetween(DB::raw('COALESCE(refunded_at, paid_at, issued_at)'), [$period->start, $period->end])
            ->sum('amount_refunded');
    }

    /** Issued and never paid, all time — an ageing debt does not stop being owed. */
    private function outstanding(): float
    {
        return (float) DB::table('invoices')
            ->whereNull('paid_at')
            ->whereNotIn('status', ['void', 'uncollectible'])
            ->sum('total');
    }

    private function mrr(): float
    {
        return (float) DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.stripe_status', self::LIVE)
            ->where('plans.billing_mode', 'subscription')
            ->selectRaw("SUM(CASE plans.`interval`
                WHEN 'year' THEN plans.amount / 12
                WHEN 'month' THEN plans.amount / GREATEST(plans.interval_count, 1)
                WHEN 'week' THEN plans.amount * 52 / 12 / GREATEST(plans.interval_count, 1)
                WHEN 'day' THEN plans.amount * 30 / GREATEST(plans.interval_count, 1)
                ELSE 0 END) as mrr")
            ->value('mrr');
    }

    private function arpu(): float
    {
        $live = $this->liveSubscriptionCount();

        return $live === 0 ? 0.0 : round($this->mrr() / $live, 2);
    }

    private function liveSubscriptionCount(): int
    {
        return DB::table('subscriptions')->whereIn('stripe_status', self::LIVE)->count();
    }

    private function newSubscriptions(Period $period): int
    {
        return DB::table('subscriptions')
            ->whereBetween('created_at', [$period->start, $period->end])
            ->count();
    }

    private function cancellations(Period $period): int
    {
        return DB::table('subscriptions')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$period->start, $period->end])
            ->count();
    }

    /**
     * Cancellations over the base that could have cancelled.
     *
     * The denominator is what was live when the window opened, not what is live now:
     * dividing by today's count means a month of growth quietly deflates churn.
     */
    private function churnRate(Period $period): float
    {
        $base = DB::table('subscriptions')
            ->where('created_at', '<', $period->start)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $period->start))
            ->count();

        return $base === 0 ? 0.0 : round(($this->cancellations($period) / $base) * 100, 1);
    }

    /** The currency the books are kept in. One deployment, one currency (docs/12). */
    private function currency(): string
    {
        return (string) (DB::table('invoices')->value('currency')
            ?? DB::table('plans')->value('currency')
            ?? 'USD');
    }
}
