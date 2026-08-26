<?php

declare(strict_types=1);

namespace App\Domain\Tools\Contracts;

/**
 * Marks a runner as too slow to run inside a request.
 *
 * The action dispatches it to the `tools` queue and returns a `queued` run that the
 * client polls. Implement this for anything that touches media, calls more than two
 * external services, or has ever taken longer than a second in practice.
 */
interface Queueable
{
    /** Hard timeout in seconds for the queued job. */
    public function timeout(): int;
}
