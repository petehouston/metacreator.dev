<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\AccessPass;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Answers "what may this user do right now?" from the local Stripe projection.
 *
 * Reading locally rather than calling Stripe is what stops a Stripe outage from
 * locking out paying customers (ADR 0004). Results are cached briefly so a catalog
 * page render is one lookup, not sixty.
 */
final class EntitlementService
{
    private const CACHE_TTL = 60;

    /** Statuses that still grant access — `past_due` is inside the grace window. */
    private const ACTIVE_STATUSES = ['active', 'trialing', 'past_due'];

    private const GRACE_DAYS = 3;

    /**
     * Daily run budget by actor class.
     *
     * This lives here rather than in QuotaService because it is *plan* knowledge, not
     * counting logic. Keeping it on this side also keeps the dependency one-way:
     * QuotaService depends on entitlements, never the reverse.
     */
    private const RUNS_PER_DAY = [
        'anonymous' => 10,
        'free' => 50,
        'pass' => 300,
        'pro' => 1000,
        'staff' => 100000,
    ];

    public function runsPerDayFor(?User $user): int
    {
        return match (true) {
            $user === null => self::RUNS_PER_DAY['anonymous'],
            $user->can('tools.bypass_quota') => self::RUNS_PER_DAY['staff'],
            $this->hasSubscription($user) => self::RUNS_PER_DAY['pro'],
            $this->hasActivePass($user) => self::RUNS_PER_DAY['pass'],
            default => self::RUNS_PER_DAY['free'],
        };
    }

    public function isPaid(User $user): bool
    {
        return Cache::remember(
            "entitlement:paid:{$user->id}",
            self::CACHE_TTL,
            fn () => $this->hasSubscription($user) || $this->hasActivePass($user),
        );
    }

    public function hasSubscription(User $user): bool
    {
        $subscription = $this->activeSubscription($user);

        if ($subscription === null) {
            return false;
        }

        // A past-due subscription keeps working for a few days so a failed card does
        // not instantly break someone's work mid-task.
        if ($subscription->stripe_status === 'past_due') {
            return $subscription->current_period_end?->addDays(self::GRACE_DAYS)->isFuture() ?? false;
        }

        return true;
    }

    public function hasActivePass(User $user): bool
    {
        return AccessPass::query()
            ->where('user_id', $user->id)
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function activeSubscription(User $user): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->whereIn('stripe_status', self::ACTIVE_STATUSES)
            ->latest('current_period_end')
            ->first();
    }

    /**
     * The full entitlement payload returned by `GET /account/entitlements` — the one
     * source of truth the frontend uses for gating.
     *
     * Current usage is passed in rather than fetched, so this service never needs to
     * know about Redis counters and the dependency between the two stays one-way.
     *
     * @param  array<string, mixed>  $usage  from QuotaService::status()
     * @return array<string, mixed>
     */
    public function describe(User $user, array $usage = []): array
    {
        $subscription = $this->activeSubscription($user);
        $pass = AccessPass::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        $paid = $this->isPaid($user);

        return [
            'plan' => $subscription?->plan->key ?? ($pass !== null ? 'pass_7d' : 'free'),
            'status' => match (true) {
                $subscription !== null => $subscription->stripe_status,
                $pass !== null => 'active',
                default => 'free',
            },
            'is_paid' => $paid,
            'renews_at' => $subscription?->current_period_end?->format(DATE_ATOM),
            'expires_at' => $pass?->expires_at->format(DATE_ATOM),
            'cancels_at' => $subscription?->cancel_at?->format(DATE_ATOM),
            'limits' => $this->limitsFor($user),
            'usage' => $usage,
            'tool_access' => [
                'default_tier' => $paid ? 'premium' : 'account',
                'grants' => $user->toolGrants()
                    ->active()
                    ->with('tool:id,slug')
                    ->get()
                    ->pluck('tool.slug')
                    ->filter()
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * The plan's hard limits, separated from {@see self::describe()} so that server-side
     * enforcement (run-history windowing, export gating) reads the same numbers the UI
     * is shown — rather than a second copy that can drift.
     *
     * `history_days: null` means unlimited.
     *
     * @return array{runs_per_day: int, history_days: int|null, export: bool, priority_support: bool}
     */
    public function limitsFor(User $user): array
    {
        $paid = $this->isPaid($user);

        return [
            'runs_per_day' => $this->runsPerDayFor($user),
            'history_days' => $paid ? null : 7,
            'export' => $paid,
            'priority_support' => $paid,
        ];
    }

    /** Call after any change to subscriptions, passes or grants. */
    public function forget(User $user): void
    {
        Cache::forget("entitlement:paid:{$user->id}");
    }
}
