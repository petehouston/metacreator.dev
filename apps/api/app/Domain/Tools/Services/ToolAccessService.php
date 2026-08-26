<?php

declare(strict_types=1);

namespace App\Domain\Tools\Services;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Tools\Data\AccessDecision;
use App\Domain\Tools\Enums\AccessReason;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolGrant;
use App\Domain\Users\Models\User;

/**
 * The single place that decides whether an actor may run a tool.
 *
 * Both the API and the UI read from this — the frontend never gates anything it has
 * not been told about, and the reason for every allowed run is persisted so comped
 * usage stays visible in reporting.
 */
final readonly class ToolAccessService
{
    public function __construct(private EntitlementService $entitlements) {}

    /**
     * Resolution order is deliberate; the first match wins (see docs/06).
     */
    public function decide(Tool $tool, ?User $user): AccessDecision
    {
        if (! $tool->isRunnable()) {
            return AccessDecision::unavailable('This tool is temporarily unavailable.');
        }

        // 1. Staff bypass — lets support reproduce a customer's run without buying a plan.
        if ($user?->can('tools.bypass_access')) {
            return AccessDecision::allow(AccessReason::Admin);
        }

        // 2. An explicit, unexpired grant beats everything below it.
        if ($user !== null && $this->hasActiveGrant($user, $tool)) {
            return AccessDecision::allow(AccessReason::Grant);
        }

        // 3. Paid access covers every tier.
        if ($user !== null && $this->entitlements->isPaid($user)) {
            return AccessDecision::allow(AccessReason::Subscription);
        }

        // 4. Authenticated actors cover `account` and `free`.
        if ($user !== null && $tool->tier !== ToolTier::Premium) {
            return AccessDecision::allow(
                $tool->tier === ToolTier::Free ? AccessReason::Free : AccessReason::Account
            );
        }

        // 5. Anonymous actors cover `free` only.
        if ($user === null && $tool->tier === ToolTier::Free) {
            return AccessDecision::allow(AccessReason::Free);
        }

        // 6. Denied — say precisely what is missing.
        return $tool->tier === ToolTier::Premium
            ? AccessDecision::needsSubscription($tool->tier)
            : AccessDecision::needsAccount($tool->tier);
    }

    public function allows(Tool $tool, ?User $user): bool
    {
        return $this->decide($tool, $user)->allowed;
    }

    /**
     * Bulk decision for catalog listings.
     *
     * Answering "can I use this?" for 60 cards must not be 60 entitlement lookups,
     * so the paid check and the grant set are each resolved once.
     *
     * @param  iterable<Tool>  $tools
     * @return array<string, bool> keyed by tool slug
     */
    public function decideMany(iterable $tools, ?User $user): array
    {
        $isPaid = $user !== null && $this->entitlements->isPaid($user);
        $isStaff = (bool) $user?->can('tools.bypass_access');
        $grantedToolIds = $user === null ? [] : $this->activeGrantToolIds($user);

        $result = [];

        foreach ($tools as $tool) {
            $result[$tool->slug] = match (true) {
                ! $tool->isRunnable() => false,
                $isStaff, $isPaid => true,
                in_array($tool->id, $grantedToolIds, true) => true,
                $user !== null => $tool->tier !== ToolTier::Premium,
                default => $tool->tier === ToolTier::Free,
            };
        }

        return $result;
    }

    private function hasActiveGrant(User $user, Tool $tool): bool
    {
        return ToolGrant::query()
            ->where('user_id', $user->id)
            ->where('tool_id', $tool->id)
            ->active()
            ->exists();
    }

    /** @return array<int, int> */
    private function activeGrantToolIds(User $user): array
    {
        return ToolGrant::query()
            ->where('user_id', $user->id)
            ->active()
            ->pluck('tool_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
