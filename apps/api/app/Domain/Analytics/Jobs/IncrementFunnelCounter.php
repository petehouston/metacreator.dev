<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * One atomic increment of a `tool_funnel_daily` counter.
 *
 * The upsert-then-increment is a single statement so that concurrent workers
 * counting the same tool on the same day cannot lose each other's writes — the
 * read-modify-write a naive `firstOrCreate` + `increment` would produce loses
 * counts precisely when traffic is high enough for the number to matter.
 */
final class IncrementFunnelCounter implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 10;

    /** @var list<string> Column allow-list — this value reaches SQL as an identifier. */
    private const COLUMNS = [
        'views', 'starts', 'completions', 'paywall_hits', 'account_walls', 'quota_walls', 'upgrades',
    ];

    public function __construct(
        private readonly int $toolId,
        private readonly string $column,
        private readonly int $by = 1,
    ) {
        $this->onQueue('analytics');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(): void
    {
        if (! in_array($this->column, self::COLUMNS, true)) {
            return;
        }

        $now = now();

        DB::statement(
            sprintf(
                'INSERT INTO tool_funnel_daily (`date`, tool_id, `%1$s`, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `%1$s` = `%1$s` + VALUES(`%1$s`), updated_at = VALUES(updated_at)',
                $this->column,
            ),
            [$now->toDateString(), $this->toolId, $this->by, $now, $now],
        );
    }
}
