<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Actions\RollupDailyStats;
use App\Domain\Analytics\Data\Period;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The screen that drives roadmap decisions (docs/15).
 *
 * Reads the rollups, never the live tables — see {@see RollupDailyStats}
 * for why. Each method answers exactly one of the questions in the spec, so a panel
 * that stops being useful can be deleted without unpicking a shared query.
 */
final class ToolAnalytics
{
    private const CACHE_TTL = 120;

    public function __construct(private readonly Cache $cache) {}

    /**
     * Runs by tool, with everything a triage pass needs on one row.
     *
     * @param  array{tier?: string|null, category?: string|null, sort?: string|null}  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>, as_of: string|null}
     */
    public function byTool(Period $period, array $filters = []): array
    {
        $key = 'admin:tool-analytics:'.$period->days.':'.md5(serialize($filters));

        return $this->cache->remember($key, self::CACHE_TTL, function () use ($period, $filters): array {
            $sort = match ($filters['sort'] ?? 'runs') {
                'failure_rate' => 'failure_rate',
                'paywall_hits' => 'paywall_hits',
                'p95' => 'p95_duration_ms',
                'unique_actors' => 'unique_actors',
                default => 'runs',
            };

            $rows = DB::table('tools as t')
                ->leftJoin('tool_categories as c', 'c.id', '=', 't.category_id')
                ->leftJoinSub($this->runStats($period), 's', 's.tool_id', '=', 't.id')
                ->leftJoinSub($this->funnelStats($period), 'f', 'f.tool_id', '=', 't.id')
                ->whereNull('t.deleted_at')
                ->when(
                    ($filters['tier'] ?? null) !== null,
                    fn ($q) => $q->where('t.tier', $filters['tier'])
                )
                ->when(
                    ($filters['category'] ?? null) !== null,
                    fn ($q) => $q->where('c.slug', $filters['category'])
                )
                ->select([
                    't.ulid', 't.slug', 't.name', 't.tier', 't.status', 't.is_visible',
                    'c.name as category_name', 'c.slug as category_slug',
                ])
                ->selectRaw('COALESCE(s.runs, 0) as runs')
                ->selectRaw('COALESCE(s.unique_actors, 0) as unique_actors')
                ->selectRaw('COALESCE(s.succeeded, 0) as succeeded')
                ->selectRaw('COALESCE(s.failed, 0) as failed')
                ->selectRaw('COALESCE(s.cache_hits, 0) as cache_hits')
                ->selectRaw('COALESCE(s.p50_duration_ms, 0) as p50_duration_ms')
                ->selectRaw('COALESCE(s.p95_duration_ms, 0) as p95_duration_ms')
                ->selectRaw('COALESCE(s.comped_runs, 0) as comped_runs')
                ->selectRaw('COALESCE(f.views, 0) as views')
                ->selectRaw('COALESCE(f.starts, 0) as starts')
                ->selectRaw('COALESCE(f.paywall_hits, 0) as paywall_hits')
                ->selectRaw('COALESCE(f.account_walls, 0) as account_walls')
                ->selectRaw('COALESCE(f.quota_walls, 0) as quota_walls')
                // Computed in SQL so it can be sorted on — a failure rate ranked in
                // PHP would only rank the page you happen to be looking at.
                ->selectRaw('ROUND(COALESCE(s.failed, 0) / GREATEST(COALESCE(s.runs, 0), 1) * 100, 2) as failure_rate')
                ->orderByDesc($sort)
                ->orderBy('t.name')
                ->limit(200)
                ->get();

            /** @var list<array<string, mixed>> $mapped */
            $mapped = $rows->map(fn (object $row): array => [
                'id' => 'tl_'.$row->ulid,
                'slug' => $row->slug,
                'name' => $row->name,
                'tier' => $row->tier,
                'status' => $row->status,
                'is_visible' => (bool) $row->is_visible,
                'category' => $row->category_slug === null ? null : [
                    'slug' => $row->category_slug,
                    'name' => $row->category_name,
                ],
                'runs' => (int) $row->runs,
                'unique_actors' => (int) $row->unique_actors,
                'succeeded' => (int) $row->succeeded,
                'failed' => (int) $row->failed,
                'failure_rate' => (float) $row->failure_rate,
                'cache_hit_rate' => $row->runs > 0
                    ? round((int) $row->cache_hits / (int) $row->runs * 100, 1)
                    : 0.0,
                'p50_duration_ms' => (int) $row->p50_duration_ms,
                'p95_duration_ms' => (int) $row->p95_duration_ms,
                'comped_runs' => (int) $row->comped_runs,
                'views' => (int) $row->views,
                'starts' => (int) $row->starts,
                'paywall_hits' => (int) $row->paywall_hits,
                'account_walls' => (int) $row->account_walls,
                'quota_walls' => (int) $row->quota_walls,
                // View → start is the tool's own conversion. A tool nobody starts
                // after opening is a tool whose form or promise is wrong.
                'start_rate' => $row->views > 0
                    ? round((int) $row->starts / (int) $row->views * 100, 1)
                    : null,
            ])->values()->all();

            return [
                'as_of' => $this->asOf(),
                'rows' => $mapped,
                'totals' => $this->totals($period),
            ];
        });
    }

    /**
     * Where premium usage actually comes from — the question "how much of this is
     * comped?" that the `access_reason` column exists to answer.
     *
     * @return list<array{reason: string, runs: int, share: float}>
     */
    public function accessReasonSplit(Period $period): array
    {
        return $this->cache->remember(
            "admin:tool-analytics:reasons:{$period->days}",
            self::CACHE_TTL,
            function () use ($period): array {
                $rows = DB::table('tool_run_daily_stats')
                    ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
                    ->groupBy('access_reason')
                    ->selectRaw('access_reason, SUM(runs) as runs')
                    ->orderByDesc('runs')
                    ->get();

                $total = max(1, (int) $rows->sum('runs'));

                /** @var list<array{reason: string, runs: int, share: float}> $split */
                $split = $rows->map(fn (object $row): array => [
                    'reason' => (string) $row->access_reason,
                    'runs' => (int) $row->runs,
                    'share' => round((int) $row->runs / $total * 100, 1),
                ])->values()->all();

                return $split;
            },
        );
    }

    /**
     * Daily run volume split by outcome — the shape that shows an incident.
     *
     * @return list<array{date: string, runs: int, failed: int}>
     */
    public function volumeSeries(Period $period): array
    {
        $byDate = $this->cache->remember(
            "admin:tool-analytics:volume:{$period->days}",
            self::CACHE_TTL,
            fn (): array => DB::table('tool_run_daily_stats')
                ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
                ->groupBy('date')
                ->selectRaw('date, SUM(runs) as runs, SUM(failed) as failed')
                ->get()
                ->mapWithKeys(fn (object $row): array => [
                    (string) $row->date => ['runs' => (int) $row->runs, 'failed' => (int) $row->failed],
                ])
                ->all(),
        );

        // Every date in the window, gaps filled: a chart drawn only from the days
        // that had traffic compresses the quiet stretches and invents a trend.
        return array_map(
            static fn (string $date): array => [
                'date' => $date,
                'runs' => $byDate[$date]['runs'] ?? 0,
                'failed' => $byDate[$date]['failed'] ?? 0,
            ],
            $period->dates(),
        );
    }

    /**
     * What is broken right now, ranked by how often it happens.
     *
     * @return list<array{code: string, count: int, tools: list<string>}>
     */
    public function topErrors(Period $period, int $limit = 10): array
    {
        $rows = DB::table('tool_run_daily_stats as s')
            ->join('tools as t', 't.id', '=', 's.tool_id')
            ->whereBetween('s.date', [$period->start->toDateString(), $period->end->toDateString()])
            ->whereNotNull('s.error_breakdown')
            ->select('t.name', 's.error_breakdown')
            ->get();

        $tally = [];

        foreach ($rows as $row) {
            $breakdown = json_decode((string) $row->error_breakdown, true);

            if (! is_array($breakdown)) {
                continue;
            }

            foreach ($breakdown as $code => $count) {
                $tally[$code]['count'] = ($tally[$code]['count'] ?? 0) + (int) $count;
                $tally[$code]['tools'][(string) $row->name] = true;
            }
        }

        uasort($tally, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_map(
            static fn (string $code): array => [
                'code' => $code,
                'count' => (int) $tally[$code]['count'],
                'tools' => array_slice(array_keys($tally[$code]['tools'] ?? []), 0, 5),
            ],
            array_slice(array_keys($tally), 0, $limit),
        );
    }

    /**
     * Who is actually running the tools, ranked by volume.
     *
     * Reads `tool_runs` rather than the rollup, because the rollup aggregates
     * actors away — it can say how many unique actors a tool had, never which of
     * them ran it forty times. That distinction is the point of this panel: a
     * headline "runs" number cannot tell healthy breadth from one script.
     *
     * An anonymous actor is identified by `visitor_hash`, an HMAC of IP and user
     * agent under a salt that rotates daily (docs/21). It is shown as a short
     * fingerprint: enough to see that one visitor accounts for a spike, not enough
     * to identify them — and useless tomorrow, which is the whole design.
     *
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     totals: array{runs: int, actors: int, accounts: int, visitors: int, runs_per_actor: float}
     * }
     */
    public function actors(Period $period, ?string $toolSlug = null, int $limit = 25): array
    {
        $key = 'admin:tool-analytics:actors:'.$period->days.':'.($toolSlug ?? 'all').':'.$limit;

        return $this->cache->remember($key, self::CACHE_TTL, function () use ($period, $toolSlug, $limit): array {
            $scoped = fn () => DB::table('tool_runs as r')
                ->join('tools as t', 't.id', '=', 'r.tool_id')
                ->whereBetween('r.created_at', [$period->start, $period->end])
                ->when($toolSlug !== null, fn ($q) => $q->where('t.slug', $toolSlug));

            $rows = $scoped()
                ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
                // One group per actor: the account when there is one, the visitor
                // hash otherwise. COALESCE rather than two queries so the ranking
                // is a single ordering across both kinds.
                ->groupByRaw("COALESCE(CONCAT('u:', r.user_id), CONCAT('v:', r.visitor_hash)), u.email, u.display_name, r.user_id")
                ->selectRaw("COALESCE(CONCAT('u:', r.user_id), CONCAT('v:', r.visitor_hash)) as actor_key")
                ->selectRaw('r.user_id, u.email, u.display_name')
                ->selectRaw('COUNT(*) as runs')
                ->selectRaw('COUNT(DISTINCT r.tool_id) as tools')
                ->selectRaw('SUM(CASE WHEN r.status = ? THEN 1 ELSE 0 END) as failed', ['failed'])
                ->selectRaw('MAX(r.created_at) as last_run_at')
                ->orderByDesc('runs')
                ->limit($limit)
                ->get();

            $totals = $scoped()
                ->selectRaw('COUNT(*) as runs')
                ->selectRaw("COUNT(DISTINCT COALESCE(CONCAT('u:', r.user_id), CONCAT('v:', r.visitor_hash))) as actors")
                ->selectRaw('COUNT(DISTINCT r.user_id) as accounts')
                // Only runs with no account behind them. An authenticated run
                // still carries a visitor hash, so counting every hash would
                // count members a second time and make the split — which reads
                // as "accounts + visitors = actors" — not add up.
                ->selectRaw('COUNT(DISTINCT CASE WHEN r.user_id IS NULL THEN r.visitor_hash END) as visitors')
                ->first();

            $runs = (int) ($totals->runs ?? 0);
            $actorCount = (int) ($totals->actors ?? 0);

            /** @var list<array<string, mixed>> $mapped */
            $mapped = $rows->map(fn (object $row): array => [
                'type' => $row->user_id === null ? 'visitor' : 'user',
                // A member is named; a visitor gets the first eight characters of
                // their daily hash, which reads as an id without being one.
                'label' => $row->user_id === null
                    ? 'Visitor '.substr((string) $row->actor_key, 2, 8)
                    : ((string) ($row->display_name ?: $row->email)),
                'email' => $row->user_id === null ? null : (string) $row->email,
                'runs' => (int) $row->runs,
                'tools' => (int) $row->tools,
                'failed' => (int) $row->failed,
                'share' => $runs > 0 ? round((int) $row->runs / $runs * 100, 1) : 0.0,
                'last_run_at' => $row->last_run_at === null
                    ? null
                    : CarbonImmutable::parse($row->last_run_at)->toIso8601String(),
            ])->values()->all();

            return [
                'rows' => $mapped,
                'totals' => [
                    // Every run in the window, across everyone — the denominator
                    // the shares above are read against.
                    'runs' => $runs,
                    'actors' => $actorCount,
                    'accounts' => (int) ($totals->accounts ?? 0),
                    'visitors' => (int) ($totals->visitors ?? 0),
                    'runs_per_actor' => $actorCount > 0 ? round($runs / $actorCount, 1) : 0.0,
                ],
            ];
        });
    }

    /**
     * Visitor → run → account → paid, as counts and drop-off.
     *
     * @return list<array{step: string, label: string, count: int, retention: float|null}>
     */
    public function funnel(Period $period): array
    {
        $range = [$period->start, $period->end];

        $visitors = (int) DB::table('tool_runs')
            ->whereBetween('created_at', $range)
            ->distinct()
            ->count(DB::raw('COALESCE(visitor_hash, CONCAT("u", user_id))'));

        $ran = (int) DB::table('tool_run_daily_stats')
            ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
            ->sum('runs');

        $accounts = (int) DB::table('users')->whereBetween('created_at', $range)->count();

        $paid = (int) DB::table('subscriptions')
            ->whereBetween('created_at', $range)
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->distinct()
            ->count('user_id');

        $steps = [
            ['step' => 'visitors', 'label' => 'Unique visitors', 'count' => $visitors],
            ['step' => 'runs', 'label' => 'Tool runs', 'count' => $ran],
            ['step' => 'accounts', 'label' => 'Accounts created', 'count' => $accounts],
            ['step' => 'paid', 'label' => 'Started paying', 'count' => $paid],
        ];

        // Retention is measured against the top of the funnel, not against the step
        // before it: "3% of visitors pay" is the number the business is steered by,
        // and a chain of step-to-step ratios hides it behind arithmetic.
        $top = max(1, $visitors);

        return array_map(
            static fn (array $step, int $index): array => [
                ...$step,
                'retention' => $index === 0 ? null : round($step['count'] / $top * 100, 2),
            ],
            $steps,
            array_keys($steps),
        );
    }

    /**
     * Posts by reach, for the content team.
     *
     * @return list<array<string, mixed>>
     */
    public function content(Period $period, int $limit = 20): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = DB::table('content_daily_stats as s')
            ->join('posts as p', 'p.id', '=', 's.post_id')
            ->whereBetween('s.date', [$period->start->toDateString(), $period->end->toDateString()])
            ->whereNull('p.deleted_at')
            ->groupBy('p.id', 'p.slug', 'p.title', 'p.status', 'p.published_at')
            ->select('p.slug', 'p.title', 'p.status', 'p.published_at')
            ->selectRaw('SUM(s.views) as views')
            // `reads` is a MySQL reserved word; unquoted it is a syntax error.
            ->selectRaw('SUM(s.`reads`) as read_total')
            ->selectRaw('SUM(s.tool_clicks) as tool_clicks')
            ->selectRaw('SUM(s.newsletter_signups) as newsletter_signups')
            ->havingRaw('SUM(s.views) > 0')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'slug' => (string) $row->slug,
                'title' => (string) $row->title,
                'status' => $row->status,
                'published_at' => $row->published_at,
                'views' => (int) $row->views,
                'reads' => (int) $row->read_total,
                'read_through' => $row->views > 0
                    ? round((int) $row->read_total / (int) $row->views * 100, 1)
                    : 0.0,
                'tool_clicks' => (int) $row->tool_clicks,
                'newsletter_signups' => (int) $row->newsletter_signups,
            ])
            ->values()
            ->all();

        return $rows;
    }

    // ── Building blocks ──────────────────────────────────────────────────────

    private function runStats(Period $period): Builder
    {
        return DB::table('tool_run_daily_stats')
            ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
            ->groupBy('tool_id')
            ->selectRaw('tool_id')
            ->selectRaw('SUM(runs) as runs')
            // Uniques are summed across days, so this is "unique actors per day,
            // totalled" rather than "distinct actors over the window". The exact
            // figure is not recoverable from a daily rollup, and the summed one is
            // the right comparison between tools — which is what the column is for.
            ->selectRaw('SUM(unique_actors) as unique_actors')
            ->selectRaw('SUM(succeeded) as succeeded')
            ->selectRaw('SUM(failed) as failed')
            ->selectRaw('SUM(cache_hits) as cache_hits')
            ->selectRaw('MAX(p50_duration_ms) as p50_duration_ms')
            ->selectRaw('MAX(p95_duration_ms) as p95_duration_ms')
            ->selectRaw("SUM(CASE WHEN access_reason IN ('grant', 'admin') THEN runs ELSE 0 END) as comped_runs");
    }

    private function funnelStats(Period $period): Builder
    {
        return DB::table('tool_funnel_daily')
            ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
            ->groupBy('tool_id')
            ->selectRaw('tool_id, SUM(views) as views, SUM(starts) as starts')
            ->selectRaw('SUM(paywall_hits) as paywall_hits, SUM(account_walls) as account_walls, SUM(quota_walls) as quota_walls');
    }

    /** @return array<string, mixed> */
    private function totals(Period $period): array
    {
        $row = DB::table('tool_run_daily_stats')
            ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
            ->selectRaw('SUM(runs) as runs, SUM(failed) as failed, SUM(cache_hits) as cache_hits')
            ->first();

        $funnel = DB::table('tool_funnel_daily')
            ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
            ->selectRaw('SUM(views) as views, SUM(paywall_hits) as paywall_hits')
            ->first();

        $runs = (int) ($row->runs ?? 0);

        return [
            'runs' => $runs,
            'failed' => (int) ($row->failed ?? 0),
            'failure_rate' => $runs > 0 ? round((int) ($row->failed ?? 0) / $runs * 100, 2) : 0.0,
            'cache_hit_rate' => $runs > 0 ? round((int) ($row->cache_hits ?? 0) / $runs * 100, 1) : 0.0,
            'views' => (int) ($funnel->views ?? 0),
            'paywall_hits' => (int) ($funnel->paywall_hits ?? 0),
        ];
    }

    /**
     * When the rollup last ran. Shown in the UI so a number that looks wrong can be
     * checked against its own freshness before anyone goes looking for a bug.
     */
    private function asOf(): ?string
    {
        $latest = DB::table('tool_run_daily_stats')->max('updated_at');

        return $latest === null ? null : CarbonImmutable::parse($latest)->toIso8601String();
    }
}
