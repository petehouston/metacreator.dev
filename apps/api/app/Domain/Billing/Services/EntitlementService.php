<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\AccessPass;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Settings\Settings;
use App\Domain\Tools\Enums\QuotaWindow;
use App\Domain\Tools\Enums\ToolTier;
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

    /** A budget of -1 (or any negative number) means "do not count this actor". */
    public const UNLIMITED = -1;

    /**
     * The fallback budget per access tier and window, used only when the setting is
     * missing — a fresh database before the seeder has run, or a key someone deleted.
     *
     * The live numbers are settings, not constants, because they are the pricing
     * model: raising the free allowance for a weekend must not need a deploy.
     *
     * Only the daily window has a default worth having. A weekly or monthly ceiling
     * is a cost decision an operator makes on purpose, so its absence means "not
     * counted" rather than some number we guessed on their behalf.
     *
     * @var array<string, int>
     */
    private const DEFAULT_RUNS_PER_DAY = [
        ToolTier::Free->value => 5,
        ToolTier::Account->value => 20,
        ToolTier::Premium->value => self::UNLIMITED,
    ];

    public function __construct(
        private readonly Settings $settings,
        private readonly BillingFeature $billing,
    ) {}

    /**
     * The access tier an actor is currently in.
     *
     * This is the same vocabulary as {@see ToolTier} on purpose: an actor's tier is
     * exactly the highest tool tier they may run, so one enum describes both sides
     * of the check instead of two that have to be kept in step.
     */
    public function accessTierFor(?User $user): ToolTier
    {
        return match (true) {
            $user === null => ToolTier::Free,
            $this->isPaid($user) => ToolTier::Premium,
            default => ToolTier::Account,
        };
    }

    /**
     * Run budget by actor class, for one window.
     *
     * This lives here rather than in QuotaService because it is *plan* knowledge, not
     * counting logic. Keeping it on this side also keeps the dependency one-way:
     * QuotaService depends on entitlements, never the reverse.
     */
    public function limitFor(?User $user, QuotaWindow $window): int
    {
        // Staff bypass the budget entirely — support reproducing a customer's run
        // must not be able to exhaust anything.
        if ($user?->can('tools.bypass_quota') === true) {
            return self::UNLIMITED;
        }

        return $this->limitForTier($this->accessTierFor($user), $window);
    }

    /** The configured budget for one access tier over one window. */
    public function limitForTier(ToolTier $tier, QuotaWindow $window): int
    {
        $configured = $this->settings->get($window->settingKey($tier));

        if (! is_numeric($configured)) {
            return $window === QuotaWindow::Daily
                ? self::DEFAULT_RUNS_PER_DAY[$tier->value]
                : self::UNLIMITED;
        }

        $limit = (int) $configured;

        // Anything negative is "unlimited"; normalising it here means callers only
        // ever have one sentinel to test for.
        return $limit < 0 ? self::UNLIMITED : $limit;
    }

    /**
     * Every window's budget for one tier, keyed by window.
     *
     * @return array<string, int>
     */
    public function limitsForTier(ToolTier $tier): array
    {
        $limits = [];

        foreach (QuotaWindow::all() as $window) {
            $limits[$window->value] = $this->limitForTier($tier, $window);
        }

        return $limits;
    }

    /**
     * The daily budget, kept as its own method because "runs per day" is the number
     * the plan card, the pricing page and the `limits` payload have always quoted.
     */
    public function runsPerDayFor(?User $user): int
    {
        return $this->limitFor($user, QuotaWindow::Daily);
    }

    public function runsPerDayForTier(ToolTier $tier): int
    {
        return $this->limitForTier($tier, QuotaWindow::Daily);
    }

    /**
     * Every tier's budget at once, for the screens that explain the ladder — the
     * quota wall, the pricing page and the plan meter all need to say what the
     * *next* tier is worth, not just the current one.
     *
     * Nested by window rather than flat: a ladder described only in runs-per-day
     * cannot explain a plan whose real ceiling is monthly.
     *
     * @return array<string, array<string, int>>
     */
    public function tierLimits(): array
    {
        return [
            ToolTier::Free->value => $this->limitsForTier(ToolTier::Free),
            ToolTier::Account->value => $this->limitsForTier(ToolTier::Account),
            ToolTier::Premium->value => $this->limitsForTier(ToolTier::Premium),
        ];
    }

    public function isPaid(User $user): bool
    {
        // With billing off there are no paid entitlements to have. Answering here
        // rather than at each call site means the whole ladder collapses to
        // `account` in one place: tier, limits, perks and the catalog all follow.
        // Nothing is cancelled or deleted — a dormant subscription row starts
        // granting access again the moment billing is switched back on.
        if ($this->billing->disabled()) {
            return false;
        }

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
        $billingEnabled = $this->billing->enabled();

        // Not merely unused while billing is off — reading them would put a plan
        // name and a renewal date into a payload the UI is about to render on a
        // page that no longer has any billing on it.
        $subscription = $billingEnabled ? $this->activeSubscription($user) : null;
        $pass = $billingEnabled
            ? AccessPass::query()
                ->where('user_id', $user->id)
                ->where('expires_at', '>', now())
                ->latest('expires_at')
                ->first()
            : null;

        $paid = $this->isPaid($user);

        return [
            // The switch travels with the payload the frontend already fetches, so
            // no screen needs a second request to know whether to show a price.
            'billing_enabled' => $billingEnabled,
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
            // The whole ladder, not just this rung. A quota wall that only knows
            // the current allowance can say "you are out"; it needs the next tier's
            // number to say what upgrading is actually worth.
            'access_tier' => $this->accessTierFor($user)->value,
            'tier_limits' => $this->tierLimits(),
            'usage' => $usage,
            'tool_access' => [
                'default_tier' => $paid ? 'premium' : 'account',
                // What the top of the catalog costs. With billing off nothing is
                // above `account`, so a card has no higher rung to advertise.
                'highest_tier' => $this->billing->highestTier()->value,
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
     * `runs_per_day` is kept alongside `runs` because it is the number every
     * existing surface quotes; `runs` is the same information for every window.
     *
     * @return array{runs_per_day: int, runs: array<string, int>, history_days: int|null, export: bool, priority_support: bool}
     */
    public function limitsFor(User $user): array
    {
        $paid = $this->isPaid($user);

        // Product capabilities follow the same rule as tiers: what used to cost
        // money becomes part of having an account. Keeping export behind a paywall
        // that cannot be paid would leave a permanently disabled button and no way
        // to earn it.
        $unlocked = $paid || $this->billing->disabled();

        return [
            'runs_per_day' => $this->runsPerDayFor($user),
            'runs' => $this->limitsForTier($this->accessTierFor($user)),
            'history_days' => $unlocked ? null : 7,
            'export' => $unlocked,
            // Not unlocked with the rest: this is a commitment about how fast a
            // human replies, not a switch in the product. With nobody paying,
            // nobody is at the front of the queue.
            'priority_support' => $paid,
        ];
    }

    /** Call after any change to subscriptions, passes or grants. */
    public function forget(User $user): void
    {
        Cache::forget("entitlement:paid:{$user->id}");
    }
}
