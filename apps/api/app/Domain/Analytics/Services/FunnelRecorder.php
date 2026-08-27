<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Jobs\IncrementFunnelCounter;

/**
 * Writes the per-tool funnel counters that `tool_funnel_daily` holds.
 *
 * Every method is fire-and-forget onto the `analytics` queue: measurement must
 * never sit in the path of the response being measured, and a failure to count a
 * view must never fail the view.
 */
final class FunnelRecorder
{
    /** Someone opened the tool's page. */
    public function view(int $toolId): void
    {
        $this->bump($toolId, 'views');
    }

    /** A run was accepted and began. */
    public function start(int $toolId): void
    {
        $this->bump($toolId, 'starts');
    }

    /** A run finished successfully. */
    public function completion(int $toolId): void
    {
        $this->bump($toolId, 'completions');
    }

    /**
     * A user was turned away, split by *which* wall they hit.
     *
     * These are three different product problems — "should be cheaper", "should not
     * need an account", "the limit is too tight" — so they are three columns rather
     * than one `blocked` counter that answers none of them.
     */
    public function wall(int $toolId, string $errorCode): void
    {
        $column = match ($errorCode) {
            'tool.subscription_required' => 'paywall_hits',
            'tool.account_required' => 'account_walls',
            'tool.quota_exceeded' => 'quota_walls',
            default => null,
        };

        if ($column !== null) {
            $this->bump($toolId, $column);
        }
    }

    /** A subscription started within the attribution window of running this tool. */
    public function upgrade(int $toolId): void
    {
        $this->bump($toolId, 'upgrades');
    }

    private function bump(int $toolId, string $column): void
    {
        IncrementFunnelCounter::dispatch($toolId, $column);
    }
}
