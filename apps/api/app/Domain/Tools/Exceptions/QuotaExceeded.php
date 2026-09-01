<?php

declare(strict_types=1);

namespace App\Domain\Tools\Exceptions;

use App\Domain\Tools\Enums\QuotaWindow;
use App\Domain\Tools\Enums\ToolTier;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The actor has spent their allowance for one window.
 *
 * Which window ran out is part of the payload, because "come back tomorrow" and
 * "come back next month" are very different answers and the wall has no business
 * guessing between them.
 *
 * The payload deliberately carries both ways out — the next tier up, and when the
 * current one resets — because those are the only two things the person can do, and
 * a wall that names neither is just a dead end. This is the most important
 * conversion surface in the product (docs/08).
 */
final class QuotaExceeded extends RuntimeException
{
    public function __construct(
        public readonly int $limit,
        public readonly \DateTimeInterface $resetsAt,
        public readonly bool $upgradeAvailable = true,
        public readonly ?ToolTier $tier = null,
        public readonly ?ToolTier $nextTier = null,
        public readonly ?int $nextTierLimit = null,
        public readonly QuotaWindow $window = QuotaWindow::Daily,
    ) {
        parent::__construct("{$window->label()} limit of {$limit} runs reached.");
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'tool.quota_exceeded',
                'message' => $this->headline(),
                'status' => 429,
                'details' => [
                    'limit' => $this->limit,
                    'window' => $this->window->value,
                    'window_label' => $this->window->label(),
                    'resets_at' => $this->resetsAt->format(DATE_ATOM),
                    'upgrade_available' => $this->upgradeAvailable,
                    'tier' => $this->tier?->value,
                    'next_tier' => $this->nextTier?->value,
                    // Null when there is no tier above, or when the tier above is
                    // itself unlimited — the frontend renders those differently.
                    'next_tier_limit' => $this->nextTierLimit !== null && $this->nextTierLimit >= 0
                        ? $this->nextTierLimit
                        : null,
                    'next_tier_unlimited' => $this->nextTierLimit !== null && $this->nextTierLimit < 0,
                    'upgrade_action' => $this->upgradeAction(),
                ],
            ],
        ], 429, ['Retry-After' => (string) max(1, $this->resetsAt->getTimestamp() - time())]);
    }

    /**
     * What the person should do next, as a machine-readable verb.
     *
     * The frontend needs to choose between a "Create a free account" button and a
     * "See plans" button; deciding that from the tier here means the two surfaces
     * cannot disagree about which one an exhausted visitor is shown.
     */
    private function upgradeAction(): ?string
    {
        return match ($this->nextTier) {
            ToolTier::Account => 'register',
            ToolTier::Premium => 'subscribe',
            default => null,
        };
    }

    private function headline(): string
    {
        if ($this->limit === 0) {
            return 'Runs are not available on your current plan.';
        }

        $period = $this->window->period();
        $used = "You've used all {$this->limit} runs for {$period}.";
        // "come back tomorrow" is only true of a day. A monthly ceiling that told
        // someone to try again tomorrow would send them straight back into the wall.
        $wait = match ($this->window) {
            QuotaWindow::Daily => 'come back tomorrow',
            QuotaWindow::Weekly => 'come back next week',
            QuotaWindow::Monthly => 'come back next month',
        };

        return match ($this->nextTier) {
            ToolTier::Account => "{$used} A free account raises the limit — or {$wait}.",
            ToolTier::Premium => "{$used} Pro removes the limit — or {$wait}.",
            default => "{$used} Your allowance resets — {$wait}.",
        };
    }
}
