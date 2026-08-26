<?php

declare(strict_types=1);

namespace App\Domain\Tools\Contracts;

/**
 * Declares the external providers a runner depends on.
 *
 * Declaring them makes three things automatic: the shared quota budget, the circuit
 * breaker, and the catalog marking the tool "degraded" when a provider is down —
 * so a broken upstream shows an honest message instead of a spinner.
 */
interface UsesProvider
{
    /** @return list<string> Provider keys, e.g. ['youtube', 'openai']. */
    public function providers(): array;
}
