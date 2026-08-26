<?php

declare(strict_types=1);

namespace App\Domain\Tools\Data;

use App\Domain\Tools\Enums\AccessReason;
use App\Domain\Tools\Enums\ToolTier;

/**
 * The outcome of an access check.
 *
 * A denial carries everything the frontend needs to render the right prompt — which
 * tier is missing, and which error code to show — so the paywall is never guessed at
 * client-side.
 */
final readonly class AccessDecision
{
    private function __construct(
        public bool $allowed,
        public ?AccessReason $reason = null,
        public ?ToolTier $requiredTier = null,
        public ?string $errorCode = null,
        public ?string $message = null,
    ) {}

    public static function allow(AccessReason $reason): self
    {
        return new self(true, $reason);
    }

    public static function needsAccount(ToolTier $required): self
    {
        return new self(
            false,
            requiredTier: $required,
            errorCode: 'tool.account_required',
            message: 'Create a free account to use this tool.',
        );
    }

    public static function needsSubscription(ToolTier $required): self
    {
        return new self(
            false,
            requiredTier: $required,
            errorCode: 'tool.subscription_required',
            message: 'This tool is included with a Pro subscription.',
        );
    }

    public static function unavailable(string $message = 'This tool is not available right now.'): self
    {
        return new self(false, errorCode: 'tool.unavailable', message: $message);
    }

    public function httpStatus(): int
    {
        return match ($this->errorCode) {
            'tool.account_required' => 401,
            'tool.subscription_required' => 402,
            'tool.unavailable' => 503,
            default => 403,
        };
    }
}
