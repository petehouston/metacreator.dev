<?php

declare(strict_types=1);

namespace App\Domain\Tools\Data;

use App\Domain\Tools\Enums\AccessReason;
use App\Domain\Tools\Models\Tool;
use App\Domain\Users\Models\User;

/**
 * Everything a runner may know about *who* is running it and *why* it was allowed.
 *
 * Runners use this to size their own output (a Pro user gets 50 suggestions where a
 * free visitor gets 10) — never to decide whether they may run at all. That decision
 * was already made before the runner was reached.
 */
final readonly class RunContext
{
    public function __construct(
        public Tool $tool,
        public AccessReason $accessReason,
        public ?User $user = null,
        public ?string $visitorHash = null,
        public string $runUlid = '',
        public string $locale = 'en',
        public string $timezone = 'UTC',
    ) {}

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    /** True when the actor is on a paid plan (or has been granted equivalent access). */
    public function isPaid(): bool
    {
        return in_array(
            $this->accessReason,
            [AccessReason::Subscription, AccessReason::Grant, AccessReason::Admin],
            true,
        );
    }

    /**
     * Pick a limit appropriate to the actor's tier.
     *
     * Used by generators to be genuinely more useful for paying users rather than
     * artificially crippled for everyone else.
     */
    public function scaled(int $free, int $account, int $paid): int
    {
        return match (true) {
            $this->isPaid() => $paid,
            $this->isAuthenticated() => $account,
            default => $free,
        };
    }
}
