<?php

declare(strict_types=1);

namespace App\Domain\Tools\Services;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Exceptions\QuotaExceeded;
use App\Domain\Tools\Models\Tool;
use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;

/**
 * Rolling 24-hour run budget, held in Redis.
 *
 * This is cost control and tier differentiation — distinct from the short-window
 * rate limit that exists to stop loops and abuse. Cache hits deliberately do not
 * consume quota: they cost us nothing, and charging for them would punish the users
 * who search for the same thing twice.
 */
final readonly class QuotaService
{
    public function __construct(private EntitlementService $entitlements) {}

    /**
     * Reserve one run, or throw.
     *
     * The increment happens before execution so that concurrent requests cannot both
     * pass the check; a failed run is refunded by {@see self::refund()}.
     *
     * @throws QuotaExceeded
     */
    public function consume(RunContext $context): int
    {
        $limit = $this->limitFor($context->user, $context->tool);
        $key = $this->key($context);

        $used = (int) Redis::incr($key);

        if ($used === 1) {
            Redis::expire($key, 86400);
        }

        if ($used > $limit) {
            Redis::decr($key);

            throw new QuotaExceeded(
                limit: $limit,
                resetsAt: $this->resetsAt($key),
                upgradeAvailable: ! $context->isPaid(),
            );
        }

        return $limit - $used;
    }

    /** Give back a reservation when the run never actually happened. */
    public function refund(RunContext $context): void
    {
        $key = $this->key($context);

        if ((int) Redis::get($key) > 0) {
            Redis::decr($key);
        }
    }

    /** @return array{limit: int, used: int, remaining: int, resets_at: string} */
    public function status(?User $user, ?string $visitorHash = null): array
    {
        $limit = $this->limitFor($user);
        $key = $this->keyFor($user, $visitorHash);
        $used = (int) (Redis::get($key) ?? 0);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'resets_at' => $this->resetsAt($key)->format(DATE_ATOM),
        ];
    }

    public function limitFor(?User $user, ?Tool $tool = null): int
    {
        // The plan-derived budget is entitlement knowledge; this service only counts.
        $base = $this->entitlements->runsPerDayFor($user);

        // A tool may cap itself lower than the actor's plan (expensive providers),
        // but never raise the limit above it.
        $override = $tool?->quotaOverride();

        return $override !== null ? min($base, $override) : $base;
    }

    private function key(RunContext $context): string
    {
        return $this->keyFor($context->user, $context->visitorHash);
    }

    private function keyFor(?User $user, ?string $visitorHash): string
    {
        $actor = $user !== null ? "u:{$user->id}" : 'v:'.($visitorHash ?? 'unknown');

        return "quota:runs:{$actor}:".CarbonImmutable::now()->format('Y-m-d');
    }

    private function resetsAt(string $key): CarbonImmutable
    {
        $ttl = (int) Redis::ttl($key);

        return $ttl > 0
            ? CarbonImmutable::now()->addSeconds($ttl)
            : CarbonImmutable::tomorrow();
    }
}
