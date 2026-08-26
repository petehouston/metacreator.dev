<?php

declare(strict_types=1);

namespace App\Domain\Tools\Enums;

use App\Domain\Tools\Models\ToolRun;

/**
 * Why a run was permitted. Persisted on every {@see ToolRun}
 * so questions like "how much premium usage comes from comped grants?" are a single
 * GROUP BY rather than an investigation.
 */
enum AccessReason: string
{
    case Free = 'free';
    case Account = 'account';
    case Subscription = 'subscription';
    case Grant = 'grant';
    case Admin = 'admin';

    /** True when the run consumed paid-tier value without paid-tier revenue. */
    public function isComped(): bool
    {
        return $this === self::Grant || $this === self::Admin;
    }
}
