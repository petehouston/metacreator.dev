<?php

declare(strict_types=1);

namespace App\Domain\Tools\Actions;

use App\Domain\Analytics\Services\FunnelRecorder;
use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\Queueable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Enums\RunStatus;
use App\Domain\Tools\Exceptions\QuotaExceeded;
use App\Domain\Tools\Exceptions\ToolAccessDenied;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Domain\Tools\Jobs\RecordToolRun;
use App\Domain\Tools\Jobs\RunToolJob;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolRun;
use App\Domain\Tools\Services\InputValidator;
use App\Domain\Tools\Services\QuotaService;
use App\Domain\Tools\Services\ToolAccessService;
use App\Domain\Tools\ToolRegistry;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The only path to executing a tool.
 *
 * Access, quota and telemetry are enforced here rather than in runners, which is
 * what makes it impossible for tool number sixty to forget a check that tool number
 * one made. Runners have no other invocation point.
 */
final readonly class RunToolAction
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolAccessService $access,
        private QuotaService $quota,
        private InputValidator $validator,
        private FunnelRecorder $funnel,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ToolAccessDenied
     * @throws QuotaExceeded
     * @throws ValidationException
     */
    public function handle(
        Tool $tool,
        array $payload,
        ?User $user,
        ?string $visitorHash = null,
        ?string $referrerSource = null,
    ): ToolRun {
        // 1. May they run this at all?
        $decision = $this->access->decide($tool, $user);

        if (! $decision->allowed) {
            // Count the wall before throwing. A denial that leaves no trace is why
            // "which premium tools do free users want?" was unanswerable (docs/15).
            $this->funnel->wall($tool->id, $decision->errorCode ?? 'tool.blocked');

            throw new ToolAccessDenied($decision);
        }

        // An allowed decision always carries a reason; this makes that guarantee
        // explicit rather than leaving the constructor to trip over a null.
        $reason = $decision->reason ?? throw new \LogicException(
            'An allowed AccessDecision must carry a reason.'
        );

        $context = new RunContext(
            tool: $tool,
            accessReason: $reason,
            user: $user,
            visitorHash: $visitorHash,
            runUlid: strtoupper((string) Str::ulid()),
            // Null for an anonymous run — and also for a member whose row was just
            // inserted, because the column defaults live in the database and are
            // not on the in-memory model until it is re-read. Both fall back here
            // rather than at the twenty call sites that read a context.
            locale: ($user instanceof User ? $user->locale : null) ?: 'en',
            timezone: ($user instanceof User ? $user->timezone : null) ?: 'UTC',
        );

        $runner = $this->registry->for($tool);

        // 2. Validate against the runner's own schema — the same definition the
        //    frontend generated its form from, so the two cannot drift.
        $input = $this->validator->validate($runner, $payload);

        // 3. A cached result costs us nothing, so it must not cost the user quota.
        $cached = $this->lookupCache($tool, $runner, $input);

        if ($cached !== null) {
            // A cache hit is still a start: counting only the runs that reached a
            // runner would make completions exceed starts on well-cached tools.
            $this->funnel->start($tool->id);

            return $this->finish($context, $input, $cached, cacheHit: true, referrerSource: $referrerSource);
        }

        // 4. Reserve budget before executing so concurrent requests cannot both pass.
        try {
            $this->quota->consume($context);
        } catch (QuotaExceeded $e) {
            $this->funnel->wall($tool->id, 'tool.quota_exceeded');

            throw $e;
        }

        $this->funnel->start($tool->id);

        if ($runner instanceof Queueable) {
            return $this->dispatchAsync($context, $input, $referrerSource);
        }

        return $this->executeInline($context, $runner, $input, $referrerSource);
    }

    private function executeInline(
        RunContext $context,
        ToolRunner $runner,
        ToolInput $input,
        ?string $referrerSource,
    ): ToolRun {
        $startedAt = hrtime(true);

        try {
            $result = $runner->run($input, $context);
        } catch (ToolExecutionException $e) {
            // An expected failure: the user gets a precise message, and the quota is
            // returned because they got nothing for it.
            $this->quota->refund($context);

            return $this->fail($context, $input, $e->errorCode, $e->getMessage(), $referrerSource);
        } catch (Throwable $e) {
            $this->quota->refund($context);

            Log::error('Tool runner crashed', [
                'tool' => $context->tool->key,
                'run' => $context->runUlid,
                'exception' => $e,
            ]);
            report($e);

            return $this->fail(
                $context, $input, 'tool.failed',
                'Something went wrong running this tool. We have been notified.',
                $referrerSource,
            );
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $this->storeCache($context->tool, $runner, $input, $result);

        return $this->finish(
            $context, $input, $result,
            cacheHit: false,
            durationMs: $durationMs,
            referrerSource: $referrerSource,
        );
    }

    private function dispatchAsync(RunContext $context, ToolInput $input, ?string $referrerSource): ToolRun
    {
        $run = ToolRun::create([
            'ulid' => $context->runUlid,
            'tool_id' => $context->tool->id,
            'tool_version' => $context->tool->version,
            'user_id' => $context->user?->id,
            'visitor_hash' => $context->visitorHash,
            'status' => RunStatus::Queued,
            'access_reason' => $context->accessReason,
            'input_hash' => $input->hash(),
            'referrer_source' => $referrerSource,
            // The result is filled in by the job; the input is known now.
            ...$this->retainedPayloads($context, $input, null),
        ]);

        RunToolJob::dispatch($run->ulid, $input->values)->onQueue('tools');

        return $run;
    }

    private function lookupCache(Tool $tool, ToolRunner $runner, ToolInput $input): ?ToolResult
    {
        if (! $runner instanceof Cacheable) {
            return null;
        }

        $payload = Cache::get($this->cacheKey($tool, $input));

        return $payload instanceof ToolResult ? $payload : null;
    }

    private function storeCache(Tool $tool, ToolRunner $runner, ToolInput $input, ToolResult $result): void
    {
        if ($runner instanceof Cacheable && $result->artifacts === []) {
            // Artifacts hold signed URLs that expire, so results carrying them are
            // never cached — a stale signed URL is worse than a recomputation.
            Cache::put($this->cacheKey($tool, $input), $result, $runner->cacheTtl());
        }
    }

    private function cacheKey(Tool $tool, ToolInput $input): string
    {
        return $tool->cacheNamespace().':'.$input->hash();
    }

    /**
     * The largest result we will keep on the run row.
     *
     * Above this, history stores the fact of the run and not its output. A member
     * who ran a bulk export gets the record; the megabyte of JSON behind it is
     * cheaper to recompute than to carry in every history query.
     */
    private const MAX_STORED_RESULT_BYTES = 64 * 1024;

    /**
     * What a signed-in member gets to keep of a run.
     *
     * Only for an authenticated actor: an anonymous run stays a hash, because there
     * is no account it could ever be shown back to and nobody who could ask us to
     * delete it.
     *
     * Results carrying artifacts are not stored, for the same reason they are not
     * cached — the URLs in them are signed and expire, so a stored copy would
     * become a page of dead links rather than a result.
     *
     * @return array{input_payload: array<string, mixed>|null, result_payload: array<string, mixed>|null}
     */
    private function retainedPayloads(RunContext $context, ToolInput $input, ?ToolResult $result): array
    {
        if ($context->user === null) {
            return ['input_payload' => null, 'result_payload' => null];
        }

        $stored = null;

        if ($result !== null && $result->artifacts === []) {
            $encoded = json_encode($result->toArray());

            if (is_string($encoded) && strlen($encoded) <= self::MAX_STORED_RESULT_BYTES) {
                $stored = $result->toArray();
            }
        }

        return ['input_payload' => $input->values, 'result_payload' => $stored];
    }

    private function finish(
        RunContext $context,
        ToolInput $input,
        ToolResult $result,
        bool $cacheHit,
        ?int $durationMs = null,
        ?string $referrerSource = null,
    ): ToolRun {
        $run = new ToolRun([
            'ulid' => $context->runUlid,
            'tool_id' => $context->tool->id,
            'tool_version' => $context->tool->version,
            'user_id' => $context->user?->id,
            'visitor_hash' => $context->visitorHash,
            'status' => RunStatus::Succeeded,
            'access_reason' => $context->accessReason,
            'input_hash' => $input->hash(),
            'result_view' => $result->view->value,
            'duration_ms' => $durationMs ?? 0,
            'cache_hit' => $cacheHit,
            'referrer_source' => $referrerSource,
            'finished_at' => now(),
            ...$this->retainedPayloads($context, $input, $result),
        ]);

        // Telemetry is written on the analytics queue: measuring the product must
        // never be something the user waits for.
        RecordToolRun::dispatch($run->attributesToArray())->onQueue('analytics');
        $this->funnel->completion($context->tool->id);

        $run->setRelation('tool', $context->tool);
        $run->result = $result;

        return $run;
    }

    private function fail(
        RunContext $context,
        ToolInput $input,
        string $errorCode,
        string $message,
        ?string $referrerSource,
    ): ToolRun {
        $run = new ToolRun([
            'ulid' => $context->runUlid,
            'tool_id' => $context->tool->id,
            'tool_version' => $context->tool->version,
            'user_id' => $context->user?->id,
            'visitor_hash' => $context->visitorHash,
            'status' => RunStatus::Failed,
            'access_reason' => $context->accessReason,
            'input_hash' => $input->hash(),
            'error_code' => $errorCode,
            'error_message' => $message,
            'referrer_source' => $referrerSource,
            'finished_at' => now(),
            // The input is kept on a failure too: "what did I send that broke it?"
            // is the whole reason to open a failed run.
            ...$this->retainedPayloads($context, $input, null),
        ]);

        RecordToolRun::dispatch($run->attributesToArray())->onQueue('analytics');

        $run->setRelation('tool', $context->tool);

        return $run;
    }
}
