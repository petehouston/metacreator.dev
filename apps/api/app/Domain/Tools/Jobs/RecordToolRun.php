<?php

declare(strict_types=1);

namespace App\Domain\Tools\Jobs;

use App\Domain\Tools\Enums\RunStatus;
use App\Domain\Tools\Models\ToolRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Persists telemetry off the request path.
 *
 * Idempotent on the run ULID, so a retried job cannot double-count a run — which
 * matters because these rows feed the numbers the product is steered by.
 */
final class RecordToolRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    /** @param  array<string, mixed>  $attributes */
    public function __construct(private readonly array $attributes)
    {
        $this->onQueue('analytics');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(): void
    {
        $attributes = $this->attributes;
        $ulid = $attributes['ulid'] ?? null;

        if ($ulid === null) {
            return;
        }

        ToolRun::query()->updateOrCreate(['ulid' => $ulid], $attributes);

        $this->updateToolCounters((int) $attributes['tool_id'], $attributes);
    }

    /**
     * Keep the denormalised counters on `tools` current.
     *
     * The rolling average is computed in SQL so concurrent workers cannot clobber
     * each other with a read-modify-write.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function updateToolCounters(int $toolId, array $attributes): void
    {
        $succeeded = ($attributes['status'] ?? null) === RunStatus::Succeeded
            || ($attributes['status'] ?? null) === RunStatus::Succeeded->value;

        $duration = (int) ($attributes['duration_ms'] ?? 0);

        // One atomic statement with bound parameters.
        //
        // Computed in SQL rather than read-modify-write in PHP, because several
        // workers record runs for the same tool concurrently and a round trip
        // through PHP would let them clobber each other's averages.
        DB::update(
            'UPDATE tools
                SET run_count = run_count + 1,
                    avg_duration_ms = CASE
                        WHEN ? > 0 THEN ROUND(((avg_duration_ms * run_count) + ?) / (run_count + 1))
                        ELSE avg_duration_ms
                    END,
                    success_rate = ROUND(((success_rate * run_count) + ?) / (run_count + 1), 2)
              WHERE id = ?',
            [$duration, $duration, $succeeded ? 100 : 0, $toolId],
        );
    }
}
