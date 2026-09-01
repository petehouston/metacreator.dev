<?php

declare(strict_types=1);

namespace App\Domain\Tools\Services;

use App\Domain\Settings\Settings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

/**
 * What the catalog is busy with right now.
 *
 * "Popular" is a lifetime counter, so it barely moves once a tool has a few
 * thousand runs — the tool that was useful in March stays at the top forever.
 * Trending answers the other question: what are people reaching for *this week*.
 *
 * The window is a setting rather than a constant because it is a judgement call an
 * admin should be able to make without a deploy. Short reacts fast and is noisy;
 * long is steady and converges on Popular.
 *
 * The cache key carries the window it was computed for, so changing the setting is
 * already a different key — a new window takes effect on the next request without
 * anything having to remember to invalidate anything.
 *
 * Read from `tool_runs` rather than the daily rollup: the rollup only refreshes
 * today's row every fifteen minutes, and a three-day window that lags by a quarter
 * of an hour on its most important day is not worth the cheaper query.
 */
final readonly class TrendingTools
{
    private const CACHE_TTL = 600;

    private const DEFAULT_DAYS = 3;

    private const DEFAULT_MIN_RUNS = 1;

    /** Never rank more than this many — the tail is noise, not a trend. */
    private const MAX_ROWS = 100;

    public function __construct(
        private Cache $cache,
        private Settings $settings,
    ) {}

    public function days(): int
    {
        $configured = $this->settings->get('tools.trending_days');

        // Clamped rather than trusted: a window of zero days ranks nothing, and one
        // of a thousand is just Popular under another name.
        return is_numeric($configured)
            ? max(1, min(90, (int) $configured))
            : self::DEFAULT_DAYS;
    }

    public function minimumRuns(): int
    {
        $configured = $this->settings->get('tools.trending_min_runs');

        return is_numeric($configured) ? max(1, (int) $configured) : self::DEFAULT_MIN_RUNS;
    }

    /**
     * Tool slugs in trending order, most active first.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->scores());
    }

    /**
     * Slug => runs inside the window, ordered by runs descending.
     *
     * @return array<string, int>
     */
    public function scores(): array
    {
        $days = $this->days();
        $minimum = $this->minimumRuns();

        /** @var array<string, int> $scores */
        $scores = $this->cache->remember(
            "tools:trending:{$days}:{$minimum}",
            self::CACHE_TTL,
            function () use ($days, $minimum): array {
                $rows = DB::table('tool_runs as r')
                    ->join('tools as t', 't.id', '=', 'r.tool_id')
                    ->where('r.created_at', '>=', now()->subDays($days))
                    ->whereNull('t.deleted_at')
                    ->where('t.is_visible', true)
                    ->groupBy('t.slug')
                    ->havingRaw('COUNT(*) >= ?', [$minimum])
                    ->orderByDesc('runs')
                    ->orderBy('t.slug')
                    ->limit(self::MAX_ROWS)
                    ->selectRaw('t.slug, COUNT(*) as runs')
                    ->get();

                return $rows
                    ->mapWithKeys(fn (object $row): array => [(string) $row->slug => (int) $row->runs])
                    ->all();
            },
        );

        return $scores;
    }

    /**
     * The window, plus the ranking, in the shape the catalog endpoint hands out.
     *
     * @return array{days: int, minimum_runs: int, slugs: list<string>}
     */
    public function describe(): array
    {
        return [
            'days' => $this->days(),
            'minimum_runs' => $this->minimumRuns(),
            'slugs' => $this->slugs(),
        ];
    }
}
