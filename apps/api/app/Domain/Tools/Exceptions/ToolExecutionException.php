<?php

declare(strict_types=1);

namespace App\Domain\Tools\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An *expected* failure — bad upstream data, an unreachable URL, a video that has
 * been deleted. Reported to the user with its code and message, and not sent to
 * Sentry as a bug.
 */
class ToolExecutionException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'tool.failed',
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @param  array<string, mixed>  $details */
    public static function invalidInput(string $message, array $details = []): self
    {
        return new self($message, 'tool.invalid_input', $details);
    }

    public static function upstreamFailed(string $provider, string $message = ''): self
    {
        return new self(
            $message ?: "The {$provider} service did not respond in time. Please try again.",
            'tool.upstream_failed',
            ['provider' => $provider],
        );
    }

    public static function notFound(string $what): self
    {
        return new self("We couldn't find {$what}. Check the link and try again.", 'tool.not_found');
    }
}
