<?php

declare(strict_types=1);

namespace App\Domain\Tools\Jobs;

use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Enums\RunStatus;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Domain\Tools\Models\ToolRun;
use App\Domain\Tools\Services\ArtifactStore;
use App\Domain\Tools\ToolRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Executes a tool that is too slow to run inside a request.
 *
 * Unique on the run ULID so a duplicated dispatch cannot run the same work twice,
 * and it always leaves the run row in a terminal state — a client polling a run must
 * never wait forever on a job that quietly died.
 */
final class RunToolJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /** @param  array<string, mixed>  $values */
    public function __construct(
        private readonly string $runUlid,
        private readonly array $values,
    ) {
        $this->onQueue('tools');
    }

    public function uniqueId(): string
    {
        return "tool-run:{$this->runUlid}";
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(ToolRegistry $registry, ArtifactStore $artifacts): void
    {
        $run = ToolRun::query()->with('tool')->where('ulid', $this->runUlid)->first();

        if ($run === null || $run->status->isTerminal()) {
            return;
        }

        $run->update(['status' => RunStatus::Running, 'started_at' => now()]);

        $runner = $registry->for($run->tool);
        $context = new RunContext(
            tool: $run->tool,
            accessReason: $run->access_reason,
            user: $run->user,
            visitorHash: $run->visitor_hash,
            runUlid: $run->ulid,
        );

        $startedAt = hrtime(true);

        try {
            $result = $runner->run(new ToolInput($this->values), $context);
        } catch (ToolExecutionException $e) {
            $this->markFailed($run, $e->errorCode, $e->getMessage());

            return;
        }

        $result = $artifacts->persist($result, $run);

        $run->update([
            'status' => RunStatus::Succeeded,
            'result_view' => $result->view->value,
            'result_ref' => $artifacts->store($run, $result),
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'finished_at' => now(),
        ]);
    }

    /**
     * Runs must always reach a terminal state, including when the job itself dies
     * (timeout, worker restart, out of memory) — otherwise a client polls forever.
     */
    public function failed(?Throwable $e): void
    {
        $run = ToolRun::query()->where('ulid', $this->runUlid)->first();

        if ($run !== null && ! $run->status->isTerminal()) {
            $this->markFailed($run, 'tool.failed', 'This run did not complete. Please try again.');
        }
    }

    private function markFailed(ToolRun $run, string $code, string $message): void
    {
        $run->update([
            'status' => RunStatus::Failed,
            'error_code' => $code,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
