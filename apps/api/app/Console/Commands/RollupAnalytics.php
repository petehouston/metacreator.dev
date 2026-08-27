<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Analytics\Actions\RollupDailyStats;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Recomputes the daily rollups the admin dashboards read.
 *
 * Scheduled every fifteen minutes for the current day (so "today" on the dashboard
 * is never more than a quarter of an hour stale) and once after midnight for the
 * day that just closed. `--days` backfills, which is what to reach for after a
 * restore or a schema change.
 */
final class RollupAnalytics extends Command
{
    protected $signature = 'analytics:rollup
                            {--days=1 : How many days back to recompute, inclusive of today}
                            {--date= : Recompute one specific day (Y-m-d) instead}';

    protected $description = 'Fold raw telemetry into the daily analytics rollups';

    public function handle(RollupDailyStats $rollup): int
    {
        $today = CarbonImmutable::now()->startOfDay();

        if (is_string($date = $this->option('date')) && $date !== '') {
            $from = $to = CarbonImmutable::parse($date)->startOfDay();
        } else {
            $days = max(1, (int) $this->option('days'));
            $from = $today->subDays($days - 1);
            $to = $today;
        }

        $result = $rollup->handle($from, $to);

        $this->components->info(sprintf(
            'Rolled up %d day(s): %d tool rows, %d billing rows, %d content rows.',
            $result['days'],
            $result['tool_rows'],
            $result['billing_rows'],
            $result['content_rows'],
        ));

        return self::SUCCESS;
    }
}
