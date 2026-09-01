<?php

declare(strict_types=1);

namespace App\Domain\Tools\Services;

use App\Domain\Billing\Services\BillingFeature;
use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Enums\QuotaWindow;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Exceptions\QuotaExceeded;
use App\Domain\Tools\Models\Tool;
use App\Domain\Users\Models\User;
use App\Http\Middleware\IdentifyVisitor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;

/**
 * Run budgets, held in Redis.
 *
 * This is cost control and tier differentiation — distinct from the short-window
 * rate limit that exists to stop loops and abuse. Cache hits deliberately do not
 * consume quota: they cost us nothing, and charging for them would punish the users
 * who search for the same thing twice.
 *
 * A budget is counted over every {@see QuotaWindow} at once — day, week and month —
 * and the tightest one wins. Which windows are actually in force is configuration,
 * not code: an unset window is simply not counted, so a site can run on a daily cap
 * alone (the default), on a monthly ceiling alone, or on both.
 *
 * The same shape applies twice over. A tier's budget comes from settings; a tool may
 * narrow any window further via `config.limits` for a provider that costs real money.
 * A tool cap never raises a limit — "unlimited" is a promise about the plan, not
 * permission to hammer a metered third-party API.
 */
final readonly class QuotaService
{
    /** Returned by {@see self::consume()} for an actor with no budget to spend. */
    public const UNLIMITED = -1;

    public function __construct(
        private EntitlementService $entitlements,
        private BillingFeature $billing,
    ) {}

    /**
     * Reserve one run against every enforced window, or throw.
     *
     * The increments happen before execution so that concurrent requests cannot both
     * pass the check; a failed run is refunded by {@see self::refund()}. When one
     * window rejects, the increments already made against the others are rolled
     * back — a run that never happened must not spend the month's budget.
     *
     * @throws QuotaExceeded
     */
    public function consume(RunContext $context): int
    {
        $limits = $this->limitsFor($context->user, $context->tool);
        $enforced = array_filter($limits, fn (int $limit): bool => ! $this->isUnlimited($limit));

        // An unlimited actor is not counted at all. Incrementing counters nobody
        // reads would still cost a Redis round trip on every premium run.
        if ($enforced === []) {
            return self::UNLIMITED;
        }

        $now = CarbonImmutable::now();
        $tier = $this->entitlements->accessTierFor($context->user);

        // A window configured to zero is closed, not merely exhausted. Saying so
        // before touching any counter keeps the message honest: there is no
        // "resets tomorrow" for an allowance that is zero every day.
        foreach ($enforced as $name => $limit) {
            if ($limit <= 0) {
                throw $this->wall(QuotaWindow::from($name), 0, $now, $context, $tier);
            }
        }

        /** @var list<string> $spent windows already incremented, for the rollback */
        $spent = [];

        foreach ($enforced as $name => $limit) {
            $window = QuotaWindow::from($name);
            $key = $this->keyFor($context->user, $context->visitorHash, $window, $now);

            $used = (int) Redis::incr($key);

            if ($used === 1) {
                Redis::expire($key, $window->ttlSeconds($now));
            }

            $spent[] = $name;

            if ($used > $limit) {
                $this->release($context->user, $context->visitorHash, $spent, $now);

                throw $this->wall($window, $limit, $now, $context, $tier);
            }
        }

        // What the caller gets back is the tightest remaining allowance, because
        // that is the number of runs actually left before something walls.
        $remaining = [];

        foreach ($enforced as $name => $limit) {
            $key = $this->keyFor($context->user, $context->visitorHash, QuotaWindow::from($name), $now);
            $remaining[] = max(0, $limit - (int) Redis::get($key));
        }

        return min($remaining);
    }

    /** Give back a reservation when the run never actually happened. */
    public function refund(RunContext $context): void
    {
        $limits = $this->limitsFor($context->user, $context->tool);

        $counted = array_keys(array_filter(
            $limits,
            fn (int $limit): bool => ! $this->isUnlimited($limit) && $limit > 0,
        ));

        $this->release($context->user, $context->visitorHash, $counted, CarbonImmutable::now());
    }

    /**
     * Current usage, for the plan meter and the entitlements payload.
     *
     * The headline (`limit`, `used`, `remaining`, `resets_at`) reports the **binding**
     * window — the enforced one with the least left. A meter that always quoted the
     * day would read "unlimited" on a site capped monthly, which is exactly the lie a
     * usage meter exists to prevent. `windows` carries all of them for a screen that
     * wants to show the breakdown.
     *
     * @return array{limit: int, used: int, remaining: int|null, unlimited: bool, window: string, tier: string, resets_at: string, windows: array<string, array<string, mixed>>}
     */
    public function status(?User $user, ?string $visitorHash = null, ?Tool $tool = null): array
    {
        $now = CarbonImmutable::now();
        $limits = $this->limitsFor($user, $tool);

        // The day is the fallback headline, so it is read by name rather than fished
        // back out of the map — the loop below reuses this entry when it reaches it.
        $daily = $this->windowStatus(QuotaWindow::Daily, $limits, $user, $visitorHash, $now);

        $windows = [];
        $binding = null;
        $bindingWindow = null;

        foreach (QuotaWindow::all() as $window) {
            $entry = $window === QuotaWindow::Daily
                ? $daily
                : $this->windowStatus($window, $limits, $user, $visitorHash, $now);

            $windows[$window->value] = $entry;

            // A null remaining is an unlimited window: it can never be the binding
            // one, because it never runs out.
            if ($entry['remaining'] !== null && ($binding === null || $entry['remaining'] < $binding['remaining'])) {
                $binding = $entry;
                $bindingWindow = $window;
            }
        }

        // Nothing is enforced: the day is the honest thing to name, since that is
        // the period the counters would roll over on if one were ever switched on.
        $headline = $binding ?? $daily;

        return [
            'limit' => $headline['limit'],
            'used' => $headline['used'],
            'remaining' => $headline['remaining'],
            'unlimited' => $headline['unlimited'],
            'window' => ($bindingWindow ?? QuotaWindow::Daily)->value,
            'tier' => $this->entitlements->accessTierFor($user)->value,
            'resets_at' => $headline['resets_at'],
            'windows' => $windows,
        ];
    }

    /**
     * One window's line in the meter.
     *
     * @param  array<string, int>  $limits
     * @return array{limit: int, used: int, remaining: int|null, unlimited: bool, label: string, resets_at: string}
     */
    private function windowStatus(
        QuotaWindow $window,
        array $limits,
        ?User $user,
        ?string $visitorHash,
        CarbonImmutable $now,
    ): array {
        $limit = $limits[$window->value];
        $unlimited = $this->isUnlimited($limit);
        $used = (int) (Redis::get($this->keyFor($user, $visitorHash, $window, $now)) ?? 0);

        return [
            'limit' => $limit,
            'used' => $used,
            // Null rather than a large number: "unlimited minus four" is not a
            // quantity, and a meter drawn from one would be a lie.
            'remaining' => $unlimited ? null : max(0, $limit - $used),
            'unlimited' => $unlimited,
            'label' => $window->label(),
            'resets_at' => $window->endsAt($now)->format(DATE_ATOM),
        ];
    }

    /**
     * The effective budget for one actor, per window, after the tool's own caps.
     *
     * @return array<string, int>
     */
    public function limitsFor(?User $user, ?Tool $tool = null): array
    {
        $limits = [];

        foreach (QuotaWindow::all() as $window) {
            $limits[$window->value] = $this->limitFor($user, $window, $tool);
        }

        return $limits;
    }

    public function limitFor(?User $user, QuotaWindow $window, ?Tool $tool = null): int
    {
        // The tier-derived budget is entitlement knowledge; this service only counts.
        $base = $this->entitlements->limitFor($user, $window);

        // A tool may cap itself lower than the actor's tier (expensive providers),
        // but never raise the limit above it. An unlimited actor takes the tool's
        // own cap when it has one.
        $override = $tool?->quotaOverride($window);

        if ($override === null) {
            return $base;
        }

        return $this->isUnlimited($base) ? $override : min($base, $override);
    }

    public function isUnlimited(int $limit): bool
    {
        return $limit < 0;
    }

    /** Hand back the increments made against `$windows` in this request. */
    /** @param  list<string>  $windows */
    private function release(?User $user, ?string $visitorHash, array $windows, CarbonImmutable $now): void
    {
        foreach ($windows as $name) {
            $key = $this->keyFor($user, $visitorHash, QuotaWindow::from($name), $now);

            if ((int) Redis::get($key) > 0) {
                Redis::decr($key);
            }
        }
    }

    private function wall(
        QuotaWindow $window,
        int $limit,
        CarbonImmutable $now,
        RunContext $context,
        ToolTier $tier,
    ): QuotaExceeded {
        $nextTier = $this->nextTier($tier);

        return new QuotaExceeded(
            limit: $limit,
            resetsAt: $window->endsAt($now),
            // "Upgrade" means "there is a rung above this one", not "you are unpaid":
            // an exhausted account holder on a site with no billing has nowhere to go.
            upgradeAvailable: $nextTier !== null && ! $context->isPaid(),
            tier: $tier,
            nextTier: $nextTier,
            nextTierLimit: $this->nextTierLimit($tier, $window),
            window: $window,
        );
    }

    /**
     * The tier an exhausted actor can move up to, or null at the top.
     *
     * With billing off, `account` *is* the top: offering Pro to someone who has run
     * out would send them to a pricing page that no longer exists. The wall then
     * says when the allowance resets and stops there, which is the only honest
     * answer left.
     */
    private function nextTier(ToolTier $tier): ?ToolTier
    {
        return match ($tier) {
            ToolTier::Free => ToolTier::Account,
            ToolTier::Account => $this->billing->enabled() ? ToolTier::Premium : null,
            ToolTier::Premium => null,
        };
    }

    private function nextTierLimit(ToolTier $tier, QuotaWindow $window): ?int
    {
        $next = $this->nextTier($tier);

        return $next === null ? null : $this->entitlements->limitForTier($next, $window);
    }

    /**
     * The counter one actor spends from: the account when there is one, otherwise
     * the visitor hash — an HMAC of IP and user agent under a salt that rotates
     * daily (see {@see IdentifyVisitor}). That is what makes
     * the anonymous allowance a per-IP allowance without us keeping an IP.
     *
     * The period is part of the key rather than something a scheduled job resets, so
     * a new week simply starts counting at a key nobody has written to yet.
     */
    private function keyFor(
        ?User $user,
        ?string $visitorHash,
        QuotaWindow $window,
        CarbonImmutable $now,
    ): string {
        $actor = $user !== null ? "u:{$user->id}" : 'v:'.($visitorHash ?? 'unknown');

        return "quota:runs:{$actor}:{$window->value}:{$window->periodKey($now)}";
    }
}
